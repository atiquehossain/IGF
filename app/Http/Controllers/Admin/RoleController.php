<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Models\Role;
use App\Services\AdminAuditService;
use App\Services\AdminAuthorityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(
        private readonly AdminAuthorityService $authority,
        private readonly AdminAuditService $audit,
    ) {
    }

    public function index(Request $request)
    {
        $title = data_get($request->Lang, 'RoleTitle', 'Administrator roles');
        $search = trim((string) $request->search);
        $actor = $this->actor();
        $actorRank = (int) $this->authority->actorRole($actor)?->security_rank;
        $roles = Role::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderBy('security_rank')
            ->orderBy('order_by')
            ->paginate(15);

        $roles->getCollection()->each(function (Role $role) use ($actor): void {
            $role->setAttribute('can_be_managed', $this->authority->canManageRole($actor, $role));
        });

        return view('admin.role.index')->with(compact('title', 'roles', 'search', 'actorRank'));
    }

    public function create()
    {
        return redirect()->route('role.index');
    }

    public function store(Request $request)
    {
        $actor = $this->actor();
        $actorRank = (int) $this->authority->actorRole($actor)?->security_rank;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'order_by' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'security_rank' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);
        $rank = (int) ($data['security_rank'] ?? min(65535, $actorRank + 100));
        abort_unless($rank > $actorRank, 422, 'A new role must have a lower authority rank than your role.');

        $role = DB::transaction(function () use ($actor, $data, $rank): Role {
            Role::query()->where('is_owner', true)->lockForUpdate()->get();
            $role = Role::query()->create([
                'name' => $data['name'],
                'order_by' => $data['order_by'] ?? $rank,
                'security_rank' => $rank,
                'is_owner' => false,
                'permission' => '',
                'actionPermission' => '',
                'serial' => '[]',
                'status' => 0,
            ]);
            $this->audit->record($actor, 'role.created', $role, [
                'security_rank' => $rank,
                'status' => false,
            ]);

            return $role;
        });

        return back()->with([
            'message' => data_get($request->Lang, 'Common.Form.AddedSuccessfully', 'Role created successfully.'),
            'alert-type' => 'success',
        ]);
    }

    public function show($id = null, Request $request = null)
    {
        return redirect()->route('role.index');
    }

    public function edit($id = null, Request $request = null)
    {
        $role = Role::query()->findOrFail($id);
        $this->authority->assertCanManageRole($this->actor(), $role);

        return response(['data' => $role->only('id', 'name', 'order_by', 'security_rank')], 200);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($request->id)],
            'order_by' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'security_rank' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);
        $actor = $this->actor();
        $actorRank = (int) $this->authority->actorRole($actor)?->security_rank;
        abort_unless((int) $data['security_rank'] > $actorRank, 422, 'A role must remain below your authority rank.');

        DB::transaction(function () use ($actor, $data): void {
            $role = $this->authority->lockRoleForMutation($data['id']);
            $this->authority->assertCanManageRole($actor, $role);
            $before = $role->only('name', 'order_by', 'security_rank');
            $role->forceFill([
                'name' => $data['name'],
                'order_by' => $data['order_by'] ?? $role->order_by,
                'security_rank' => (int) $data['security_rank'],
            ])->save();
            $this->audit->record($actor, 'role.updated', $role, [
                'before' => $before,
                'after' => $role->only('name', 'order_by', 'security_rank'),
            ]);
        });

        return back()->with([
            'message' => data_get($request->Lang, 'Common.Form.UpdatedSuccessfully', 'Role updated successfully.'),
            'alert-type' => 'success',
        ]);
    }

    public function status($id, Request $request)
    {
        $actor = $this->actor();
        $role = DB::transaction(function () use ($actor, $id): Role {
            $role = $this->authority->lockRoleForMutation($id);
            $this->authority->assertCanManageRole($actor, $role);
            $nextStatus = !(bool) $role->status;

            abort_if(
                !$nextStatus && Admin::query()->where('role', (string) $role->id)->exists(),
                409,
                'Reassign every administrator before disabling this role.'
            );

            $before = (bool) $role->status;
            $role->forceFill(['status' => $nextStatus])->save();
            $this->audit->record($actor, 'role.status_changed', $role, [
                'status' => ['before' => $before, 'after' => $nextStatus],
            ]);

            return $role;
        });

        return response([
            'message' => $role->status
                ? data_get($request->Lang, 'Common.Form.PublishSuccessfully', 'Role enabled successfully.')
                : data_get($request->Lang, 'Common.Form.UnpublishSuccessfully', 'Role disabled successfully.'),
            'status' => (bool) $role->status,
        ], 200);
    }

    public function permission($id, Request $request)
    {
        $title = data_get($request->Lang, 'RoleTitle', 'Administrator roles');
        $role = Role::query()->findOrFail($id);
        $actor = $this->actor();
        $this->authority->assertCanManageRole($actor, $role);
        $canEditRolePermissions = app(\App\Http\Middleware\Permission::class)
            ->allows($actor, 'role.permission.store');
        $authMenus = AuthMenu::query()
            ->with(['children' => fn ($query) => $query->with('menuAction')])
            ->whereNull('parent_id')
            ->where('status', 1)
            ->orderBy('order_by')
            ->get();

        return view('admin.role.permission')->with(compact('title', 'role', 'authMenus', 'canEditRolePermissions'));
    }

    public function permissionStore(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'permission' => ['nullable', 'array'],
            'permission.*' => ['integer', Rule::exists('auth_menus', 'id')],
            'actionPermission' => ['nullable', 'array'],
            'actionPermission.*' => ['integer', Rule::exists('menu_actions', 'id')],
        ]);
        $actor = $this->actor();
        $permissions = collect($data['permission'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $actions = collect($data['actionPermission'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();

        DB::transaction(function () use ($actor, $data, $permissions, $actions): void {
            $role = $this->authority->lockRoleForMutation($data['id']);
            $this->authority->assertCanManageRole($actor, $role);
            [$delegableMenus, $delegableActions] = $this->authority
                ->assertCanDelegatePermissions($actor, $permissions, $actions);
            if ($delegableMenus !== null && $delegableActions !== null) {
                // Capabilities outside the manager's own grantable set are
                // immutable to them: preserve existing assignments rather
                // than silently stripping higher-privilege configuration.
                $permissions = $permissions
                    ->merge($this->permissionIds($role->permission)->diff($delegableMenus))
                    ->unique()->sort()->values();
                $actions = $actions
                    ->merge($this->permissionIds($role->actionPermission)->diff($delegableActions))
                    ->unique()->sort()->values();
            }
            $menu = AuthMenu::query()
                ->with(['children' => fn ($query) => $query->whereIn('id', $permissions)->with('menuAction')])
                ->where('status', 1)
                ->whereNull('parent_id')
                ->whereIn('id', $permissions)
                ->orderBy('order_by')
                ->get();
            $beforePermissions = $this->permissionFingerprint($role->permission, $role->actionPermission);

            $role->forceFill([
                'permission' => $permissions->implode(','),
                'actionPermission' => $actions->implode(','),
                'serial' => $menu->toJson(),
            ])->save();
            $this->audit->record($actor, 'role.permissions_changed', $role, [
                'before' => $beforePermissions,
                'after' => $this->permissionFingerprint($role->permission, $role->actionPermission),
            ]);
        });

        return back()->with([
            'message' => data_get($request->Lang, 'Common.Form.UpdatedSuccessfully', 'Role permissions updated successfully.'),
            'alert-type' => 'success',
        ]);
    }

    public function destroy($id = null, Request $request = null)
    {
        $actor = $this->actor();

        DB::transaction(function () use ($actor, $id): void {
            $role = $this->authority->lockRoleForMutation($id);
            $this->authority->assertCanManageRole($actor, $role);
            abort_if(Admin::query()->where('role', (string) $role->id)->exists(), 409, 'Reassign every administrator before deleting this role.');
            abort_if(Role::query()->where('parent_id', $role->id)->exists(), 409, 'Reassign child roles before deleting this role.');

            $this->audit->record($actor, 'role.deleted', $role, [
                'security_rank' => (int) $role->security_rank,
                'status' => (bool) $role->status,
            ]);
            $role->delete();
        });

        return response([
            'message' => data_get($request?->Lang, 'Common.Form.DeleteSuccessfully', 'Role deleted successfully.'),
        ], 200);
    }

    private function permissionFingerprint(?string $menus, ?string $actions): array
    {
        $menuIds = collect(explode(',', (string) $menus))->filter()->sort()->values();
        $actionIds = collect(explode(',', (string) $actions))->filter()->sort()->values();

        return [
            'menu_count' => $menuIds->count(),
            'action_count' => $actionIds->count(),
            'sha256' => hash('sha256', $menuIds->implode(',') . '|' . $actionIds->implode(',')),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function permissionIds(?string $value): \Illuminate\Support\Collection
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($id) => filter_var(trim($id), FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function actor(): Admin
    {
        return Auth::guard('admin')->user() ?? abort(401);
    }
}
