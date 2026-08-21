<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Services\AdminAuthorityService;
use App\Support\AdminPermissionSynchronizer;
use Database\Seeders\AdminPermissionRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminAuthorityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionRegistrySeeder::class);
    }

    public function test_fresh_schema_has_one_reserved_owner_role_and_an_append_only_audit_ledger(): void
    {
        $ownerRole = Role::query()->where('is_owner', true)->sole();

        $this->assertSame(0, $ownerRole->security_rank);
        $this->assertTrue((bool) $ownerRole->status);
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('admin_audit_events'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumns('roles', ['security_rank', 'is_owner']));
    }

    public function test_legacy_owner_selection_is_single_deterministic_and_matches_authority_migration(): void
    {
        $lowerIdRole = Role::query()->create([
            'name' => 'Super Admin',
            'security_rank' => 200,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'order_by' => 200,
            'status' => 1,
        ]);
        $earliestAdminRole = Role::query()->create([
            'name' => '  SUPER ADMIN  ',
            'security_rank' => 100,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'order_by' => 100,
            'status' => 1,
        ]);

        Admin::query()->create([
            'name' => 'Unrecoverable Legacy Administrator',
            'username' => '   ',
            'email' => 'unrecoverable-legacy-admin@example.test',
            'role' => (string) $lowerIdRole->id,
            'status' => 1,
            'password' => 'not-a-password-hash',
            'must_change_password' => false,
        ]);
        $this->admin($earliestAdminRole, 'earliest-named-admin');
        $this->admin($lowerIdRole, 'later-named-admin');

        $legacyResolver = new \ReflectionMethod(AdminPermissionSynchronizer::class, 'legacyOwnerRoleId');
        $legacyResolver->setAccessible(true);
        $legacyRoleId = $legacyResolver->invoke(app(AdminPermissionSynchronizer::class));

        $migration = require database_path('migrations/2026_08_21_100000_harden_admin_authority.php');
        $migrationResolver = new \ReflectionMethod($migration, 'resolveRecoverableOwnerRoleId');
        $migrationResolver->setAccessible(true);
        $migrationRoleId = $migrationResolver->invoke($migration);

        $this->assertSame($earliestAdminRole->id, $legacyRoleId);
        $this->assertSame($legacyRoleId, $migrationRoleId);
    }

    public function test_permission_synchronization_preserves_existing_disabled_admin_metadata(): void
    {
        $menu = AuthMenu::query()->where('link', 'page.index')->firstOrFail();
        $action = MenuAction::query()->where('link', 'page.create')->firstOrFail();
        $menu->update([
            'name' => 'Owner-defined page label',
            'parent_id' => null,
            'order_by' => 987,
            'status' => 0,
        ]);
        $action->update([
            'name' => 'Owner-defined create label',
            'type' => 8,
            'order_by' => 654,
            'status' => 0,
        ]);

        app(AdminPermissionSynchronizer::class)->synchronize();

        $menu->refresh();
        $action->refresh();
        $this->assertSame('Owner-defined page label', $menu->name);
        $this->assertNull($menu->parent_id);
        $this->assertSame(987, (int) $menu->order_by);
        $this->assertFalse((bool) $menu->status);
        $this->assertSame('Owner-defined create label', $action->name);
        $this->assertSame(8, (int) $action->type);
        $this->assertSame(654, (int) $action->order_by);
        $this->assertFalse((bool) $action->status);
    }

    public function test_authority_hardening_is_rerunnable_and_rollback_never_erases_the_audit_ledger(): void
    {
        $partiallyMigratedRole = Role::query()->create([
            'name' => 'Partially migrated role',
            'security_rank' => 100,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'order_by' => 450,
            'status' => 1,
        ]);
        $eventId = DB::table('admin_audit_events')->insertGetId([
            'event_uuid' => (string) Str::uuid(),
            'action' => 'release.safety_probe',
            'outcome' => 'success',
            'created_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_08_21_100000_harden_admin_authority.php');

        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('admin_audit_events'));
        $this->assertDatabaseHas('admin_audit_events', ['id' => $eventId, 'action' => 'release.safety_probe']);
        $this->assertSame(550, (int) $partiallyMigratedRole->fresh()->security_rank);
        $this->assertSame(1, Role::query()->where('is_owner', true)->count());
    }

    public function test_authority_hardening_rejects_normalized_duplicate_identities_before_mutating_roles(): void
    {
        $ownerRole = Role::query()->where('is_owner', true)->sole();
        $probeRole = Role::query()->create([
            'name' => 'Preflight mutation probe',
            'security_rank' => 4321,
            'is_owner' => false,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'order_by' => 200,
            'status' => 1,
        ]);
        $password = Hash::make('Strong-Admin-Password!23');

        foreach ([
            ['username' => 'CanonicalOwner', 'email' => 'owner@example.test'],
            ['username' => ' canonicalowner ', 'email' => ' OWNER@example.test '],
        ] as $identity) {
            Admin::query()->create(array_merge($identity, [
                'name' => 'Duplicate identity preflight',
                'role' => (string) $ownerRole->id,
                'status' => 1,
                'password' => $password,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]));
        }

        $migration = require database_path('migrations/2026_08_21_100000_harden_admin_authority.php');

        try {
            $migration->up();
            $this->fail('Whitespace- and case-equivalent administrator identities must stop the migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate administrator usernames or emails', $exception->getMessage());
        }

        $this->assertSame(4321, (int) $probeRole->fresh()->security_rank);
        $this->assertSame(1, Role::query()->where('is_owner', true)->count());
    }

    public function test_reserved_owner_role_cannot_be_edited_toggled_deleted_or_have_permissions_replaced(): void
    {
        [$owner, $ownerRole] = $this->owner('reserved-owner');

        $this->asAdmin($owner)->get(route('role.edit', $ownerRole->id))->assertForbidden();
        $this->putJson(route('role.status', $ownerRole->id))->assertForbidden();
        $this->deleteJson(route('role.destroy', $ownerRole->id))->assertForbidden();
        $this->post(route('role.permission.store'), [
            'id' => $ownerRole->id,
            'permission' => [],
            'actionPermission' => [],
        ])->assertForbidden();

        $this->assertTrue($ownerRole->fresh()->is_owner);
        $this->assertSame(0, $ownerRole->fresh()->security_rank);
    }

    public function test_self_and_equal_or_higher_rank_administrator_actions_are_forbidden(): void
    {
        $managerRole = $this->role('Manager A', 100, [
            'admin.index', 'admin.edit', 'admin.status', 'admin.destroy', 'admin.reset',
        ]);
        $peerRole = $this->role('Manager B', 100);
        $lowerRole = $this->role('Editor', 200);
        $manager = $this->admin($managerRole, 'manager-a');
        $peer = $this->admin($peerRole, 'manager-b');
        $lower = $this->admin($lowerRole, 'lower-editor');

        $this->asAdmin($manager)->get(route('admin.edit', $peer->id))->assertForbidden();
        $this->putJson(route('admin.status', $peer->id))->assertForbidden();
        $this->deleteJson(route('admin.destroy', $peer->id))->assertForbidden();
        $this->post(route('admin.reset.perform', $peer->id))->assertForbidden();

        $this->putJson(route('admin.status', $manager->id))->assertUnprocessable();
        $this->deleteJson(route('admin.destroy', $manager->id))->assertUnprocessable();
        $this->post(route('admin.reset.perform', $manager->id))->assertUnprocessable();

        $this->get(route('admin.edit', $lower->id))->assertOk();
    }

    public function test_admin_creation_and_reset_generate_one_time_passwords_and_audit_without_secrets(): void
    {
        [$owner] = $this->owner('credential-owner');
        $staffRole = $this->role('Staff', 200);

        $response = $this->asAdmin($owner)->post(route('admin.store'), [
            'role' => $staffRole->id,
            'name' => 'New Staff',
            'username' => 'new-staff',
            'email' => 'new-staff@example.test',
            'mobile' => '01700000001',
        ])->assertRedirect()->assertSessionHas('temporary_password');

        $temporary = (string) $response->getSession()->get('temporary_password');
        $created = Admin::query()->where('username', 'new-staff')->firstOrFail();
        $this->assertGreaterThanOrEqual(24, strlen($temporary));
        $this->assertTrue(Hash::check($temporary, $created->password));
        $this->assertTrue($created->must_change_password);
        $this->assertFalse((bool) $created->status);
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'admin.created',
            'actor_admin_id' => $owner->id,
            'target_id' => (string) $created->id,
        ]);
        $this->assertStringNotContainsString($temporary, AdminAuditEvent::query()->get()->toJson());

        $this->post(route('admin.reset.perform', $created->id))
            ->assertRedirect()
            ->assertSessionHas('temporary_password');
        $resetTemporary = (string) session('temporary_password');
        $created->refresh();
        $this->assertTrue(Hash::check($resetTemporary, $created->password));
        $this->assertTrue($created->must_change_password);
        $this->assertSame(1, $created->auth_version);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'admin.password_reset', 'target_id' => (string) $created->id]);
        $this->assertStringNotContainsString($resetTemporary, AdminAuditEvent::query()->get()->toJson());
    }

    public function test_client_supplied_admin_password_is_rejected_instead_of_silently_used(): void
    {
        [$owner] = $this->owner('password-injection-owner');
        $staffRole = $this->role('Staff', 200);

        $this->asAdmin($owner)->from(route('admin.index'))->post(route('admin.store'), [
            'role' => $staffRole->id,
            'name' => 'Unsafe Staff',
            'username' => 'unsafe-staff',
            'password' => 'Actor-Chosen-Password!23',
            'password_confirmation' => 'Actor-Chosen-Password!23',
        ])->assertRedirect(route('admin.index'))->assertSessionHasErrors(['password', 'password_confirmation']);

        $this->assertDatabaseMissing('admins', ['username' => 'unsafe-staff']);
    }

    public function test_role_rank_is_enforced_and_successful_role_changes_are_audited(): void
    {
        [$owner] = $this->owner('role-owner');
        $managerRole = $this->role('Manager', 100, ['role.index', 'role.create', 'role.edit', 'role.status', 'role.destroy', 'role.permission', 'role.permission.edit']);
        $peerRole = $this->role('Peer manager', 100);
        $editorRole = $this->role('Editor', 200);
        $manager = $this->admin($managerRole, 'rank-manager');

        $this->asAdmin($manager)->get(route('role.edit', $peerRole->id))->assertForbidden();
        $this->put(route('role.update'), [
            'id' => $editorRole->id,
            'name' => 'Promoted too far',
            'security_rank' => 50,
        ])->assertUnprocessable();

        $this->from(route('role.index'))->post(route('role.store'), [
            'name' => 'Invalid high authority',
            'security_rank' => 100,
        ])->assertUnprocessable();

        $this->asAdmin($owner)->put(route('role.update'), [
            'id' => $editorRole->id,
            'name' => 'Senior Editor',
            'security_rank' => 150,
            'order_by' => 150,
        ])->assertRedirect();

        $this->assertDatabaseHas('roles', ['id' => $editorRole->id, 'name' => 'Senior Editor', 'security_rank' => 150]);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'role.updated', 'target_id' => (string) $editorRole->id]);
    }

    public function test_assigned_roles_cannot_be_disabled_or_deleted(): void
    {
        [$owner] = $this->owner('assigned-role-owner');
        $assignedRole = $this->role('Assigned role', 200);
        $this->admin($assignedRole, 'assigned-admin');

        $this->asAdmin($owner)->putJson(route('role.status', $assignedRole->id))->assertStatus(409);
        $this->deleteJson(route('role.destroy', $assignedRole->id))->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $assignedRole->id, 'status' => 1]);
    }

    public function test_update_status_and_delete_admin_mutations_are_audited_without_contact_details(): void
    {
        [$owner] = $this->owner('mutation-owner');
        $staffRole = $this->role('Mutable staff', 200);
        $target = $this->admin($staffRole, 'mutable-staff');

        $this->asAdmin($owner)->put(route('admin.update'), [
            'id' => $target->id,
            'role' => $staffRole->id,
            'name' => 'Updated Staff Name',
            'mobile' => '01712345678',
            'address' => 'Private staff address',
        ])->assertRedirect();
        $this->putJson(route('admin.status', $target->id))->assertOk()->assertJson(['status' => false]);
        $this->deleteJson(route('admin.destroy', $target->id))->assertOk();

        foreach (['admin.updated', 'admin.status_changed', 'admin.deleted'] as $action) {
            $this->assertDatabaseHas('admin_audit_events', [
                'action' => $action,
                'target_id' => (string) $target->id,
            ]);
        }
        $auditJson = AdminAuditEvent::query()->where('target_id', (string) $target->id)->get()->toJson();
        $this->assertStringNotContainsString('01712345678', $auditJson);
        $this->assertStringNotContainsString('Private staff address', $auditJson);
    }

    public function test_permission_status_and_delete_role_mutations_are_audited(): void
    {
        [$owner] = $this->owner('role-mutation-owner');
        $role = $this->role('Mutable role', 200);
        $menu = AuthMenu::query()->where('link', 'page.index')->firstOrFail();
        $action = MenuAction::query()->where('link', 'page.edit')->firstOrFail();

        $this->asAdmin($owner)->post(route('role.permission.store'), [
            'id' => $role->id,
            'permission' => [$menu->id],
            'actionPermission' => [$action->id],
        ])->assertRedirect();
        $this->putJson(route('role.status', $role->id))->assertOk()->assertJson(['status' => false]);
        $this->deleteJson(route('role.destroy', $role->id))->assertOk();

        foreach (['role.permissions_changed', 'role.status_changed', 'role.deleted'] as $auditAction) {
            $this->assertDatabaseHas('admin_audit_events', [
                'action' => $auditAction,
                'target_id' => (string) $role->id,
            ]);
        }
    }

    public function test_delegated_role_manager_can_only_grant_capabilities_their_role_currently_holds(): void
    {
        $roleMenu = AuthMenu::query()->where('link', 'role.index')->firstOrFail();
        $permissionEdit = MenuAction::query()->where('link', 'role.permission.edit')->firstOrFail();
        $adminMenu = AuthMenu::query()->where('link', 'admin.index')->firstOrFail();
        $adminCreate = MenuAction::query()->where('link', 'admin.create')->firstOrFail();
        $managerRole = $this->role('Delegated role manager', 100, ['role.index', 'role.permission.edit']);
        $targetRole = $this->role('Delegated target', 200);
        $manager = $this->admin($managerRole, 'delegated-role-manager');

        $this->asAdmin($manager)->post(route('role.permission.store'), [
            'id' => $targetRole->id,
            'permission' => [$roleMenu->id, $adminMenu->id],
            'actionPermission' => [$permissionEdit->id, $adminCreate->id],
        ])->assertForbidden();

        $this->assertSame('', (string) $targetRole->fresh()->permission);
        $this->assertSame('', (string) $targetRole->fresh()->actionPermission);
        $this->assertDatabaseMissing('admin_audit_events', [
            'action' => 'role.permissions_changed',
            'actor_admin_id' => $manager->id,
            'target_id' => (string) $targetRole->id,
        ]);

        $this->post(route('role.permission.store'), [
            'id' => $targetRole->id,
            'permission' => [$roleMenu->id],
            'actionPermission' => [$permissionEdit->id],
        ])->assertRedirect();

        $this->assertSame((string) $roleMenu->id, (string) $targetRole->fresh()->permission);
        $this->assertSame((string) $permissionEdit->id, (string) $targetRole->fresh()->actionPermission);
    }

    public function test_owner_continuity_rechecks_locked_rows_after_another_owner_is_removed(): void
    {
        [$firstOwner, $ownerRole] = $this->owner('first-owner');
        $secondOwner = $this->admin($ownerRole, 'second-owner');
        $authority = app(AdminAuthorityService::class);

        DB::transaction(function () use ($authority, $secondOwner): void {
            $locked = $authority->lockAdminForMutation($secondOwner->id);
            $authority->ensureActiveOwnerRemains($locked, replacementStatus: false);
            $locked->forceFill(['status' => 0])->save();
        });

        try {
            DB::transaction(function () use ($authority, $firstOwner): void {
                $locked = $authority->lockAdminForMutation($firstOwner->id);
                $authority->ensureActiveOwnerRemains($locked, replacementStatus: false);
            });
            $this->fail('The final active owner should have been protected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(1, Admin::query()->where('status', 1)->where('role', (string) $ownerRole->id)->count());
    }

    public function test_admin_avatar_is_decoded_normalized_privately_served_and_hostile_files_are_rejected(): void
    {
        Storage::fake('local');
        [$owner] = $this->owner('avatar-owner');
        $staffRole = $this->role('Avatar staff', 200);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=', true);
        $polyglot = UploadedFile::fake()->createWithContent('avatar.png', $png . '<?php echo "unsafe";');

        $this->asAdmin($owner)->post(route('admin.store'), [
            'role' => $staffRole->id,
            'name' => 'Avatar Staff',
            'username' => 'avatar-staff',
            'image' => $polyglot,
        ])->assertRedirect()->assertSessionHas('temporary_password');

        $admin = Admin::query()->where('username', 'avatar-staff')->firstOrFail();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{48}\.(?:jpg|png|webp)\z/', (string) $admin->image);
        Storage::disk('local')->assertExists('uploads/admin/' . $admin->image);
        $stored = Storage::disk('local')->get('uploads/admin/' . $admin->image);
        $storedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($stored);
        $this->assertContains($storedMime, ['image/jpeg', 'image/png', 'image/webp']);
        $this->assertStringNotContainsString('<?php', $stored);

        $this->get(route('admin.image', $admin->image))
            ->assertOk()
            ->assertHeader('Content-Type', $storedMime)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $this->get(route('admin.image', $admin->image))->headers->get('Cache-Control'));

        $restricted = $this->admin($staffRole, 'restricted-avatar-owner');
        $ownAvatar = str_repeat('b', 48) . '.' . pathinfo((string) $admin->image, PATHINFO_EXTENSION);
        Storage::disk('local')->put('uploads/admin/' . $ownAvatar, $stored);
        $restricted->update(['image' => $ownAvatar, 'must_change_password' => true]);
        $this->asAdmin($restricted)->get(route('admin.image', $ownAvatar))->assertOk();
        $this->get(route('admin.image', $admin->image))->assertForbidden();

        $this->asAdmin($owner);

        $this->get(route('admin.image', 'not-an-avatar'))->assertNotFound();
        $unreferenced = str_repeat('a', 48) . '.' . pathinfo((string) $admin->image, PATHINFO_EXTENSION);
        Storage::disk('local')->put('uploads/admin/' . $unreferenced, $stored);
        $this->get(route('admin.image', $unreferenced))->assertNotFound();

        $malware = UploadedFile::fake()->createWithContent('malware.jpg', '<?php echo "not an image";');
        $this->post(route('admin.store'), [
            'role' => $staffRole->id,
            'name' => 'Malware Staff',
            'username' => 'malware-staff',
            'image' => $malware,
        ])->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('admins', ['username' => 'malware-staff']);
    }

    public function test_audit_events_cannot_be_updated_or_deleted_through_the_model(): void
    {
        [$owner] = $this->owner('audit-owner');
        $role = $this->role('Audited role', 200);
        app(\App\Services\AdminAuditService::class)->record($owner, 'role.tested', $role, ['status' => true]);
        $event = AdminAuditEvent::query()->firstOrFail();

        try {
            $event->forceFill(['outcome' => 'failed'])->saveQuietly();
            $this->fail('An audit event update should be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            $event->fresh()->deleteQuietly();
            $this->fail('An audit event deletion should be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('admin_audit_events', ['id' => $event->id, 'outcome' => 'success']);
    }

    public function test_admin_roster_search_is_session_bound_expires_and_never_enters_urls_or_audits(): void
    {
        [$owner] = $this->owner('private-roster-owner');
        $staffRole = $this->role('Private roster staff', 200);
        for ($index = 0; $index < 9; $index++) {
            $admin = $this->admin($staffRole, 'private-roster-' . $index);
            $admin->update(['name' => 'Confidential Roster Person ' . $index]);
        }

        $response = $this->asAdmin($owner)->post(route('admin.search'), [
            'search' => 'Confidential Roster',
        ])->assertRedirect(route('admin.index'));
        $this->assertStringNotContainsString('Confidential', (string) $response->headers->get('Location'));

        $index = $this->get(route('admin.index'))->assertOk()->assertSee('Confidential Roster Person');
        $index->assertSee('method="post"', false)
            ->assertSee(route('admin.search.clear'), false)
            ->assertDontSee('search=', false)
            ->assertDontSee(rawurlencode('Confidential Roster'), false);
        $audit = AdminAuditEvent::query()->where('action', 'private_search.started')->latest('id')->firstOrFail();
        $this->assertSame('admins', $audit->context['scope']);
        $this->assertStringNotContainsString('Confidential Roster', $audit->toJson());

        $this->travel(11)->minutes();
        $this->get(route('admin.index'))->assertOk()->assertDontSee(route('admin.search.clear'), false);
        $this->travelBack();

        $this->get(route('admin.index', ['search' => 'legacy-private-value']))
            ->assertRedirect(route('admin.index'));
    }

    public function test_legacy_empty_create_and_show_routes_redirect_to_real_indexes(): void
    {
        [$owner] = $this->owner('redirect-owner');
        $role = $this->role('Redirect target role', 200);
        $admin = $this->admin($role, 'redirect-target');

        $this->asAdmin($owner)->get(route('admin.create'))->assertRedirect(route('admin.index'));
        $this->get(route('admin.show', $admin->id))->assertRedirect(route('admin.index'));
        $this->get(route('role.create'))->assertRedirect(route('role.index'));
        $this->get(route('role.show', $role->id))->assertRedirect(route('role.index'));
    }

    /** @return array{Admin, Role} */
    private function owner(string $username): array
    {
        $role = Role::query()->where('is_owner', true)->firstOrFail();
        $role->forceFill(['status' => 1, 'security_rank' => 0])->save();

        return [$this->admin($role, $username), $role];
    }

    private function role(string $name, int $rank, array $capabilities = []): Role
    {
        $menus = AuthMenu::query()->whereIn('link', $capabilities)->pluck('id');
        $actions = MenuAction::query()->whereIn('link', $capabilities)->pluck('id');

        return Role::query()->create([
            'name' => $name . '-' . Str::lower(Str::random(6)),
            'security_rank' => $rank,
            'is_owner' => false,
            'permission' => $menus->implode(','),
            'actionPermission' => $actions->implode(','),
            'serial' => '[]',
            'order_by' => $rank,
            'status' => 1,
        ]);
    }

    private function admin(Role $role, string $username): Admin
    {
        return Admin::query()->create([
            'name' => Str::headline($username),
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('Strong-Admin-Password!23'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
    }

    private function asAdmin(Admin $admin): self
    {
        $this->actingAs($admin, 'admin');
        $this->withSession([Admin::SESSION_AUTH_VERSION => (int) $admin->auth_version]);

        return $this;
    }
}
