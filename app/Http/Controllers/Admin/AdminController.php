<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\Admin;
use App\Models\Role;
use App\Services\AdminAuditService;
use App\Services\AdminAuthorityService;
use App\Services\AdminAvatarService;
use App\Services\AdminPrivateSearch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminAuthorityService $authority,
        private readonly AdminAuditService $audit,
        private readonly AdminAvatarService $avatars,
    ) {
    }

    public function index(Request $request)
    {
        if ($request->query->has('search')) {
            return redirect()->route('admin.index');
        }

        $title = data_get($request->Lang, 'Admin', 'Administrators');
        $search = app(AdminPrivateSearch::class)->current($request, 'admins');
        $actor = $this->actor();
        $roles = $this->authority->assignableRoles($actor);
        $admins = Admin::query()
            ->with('roleModel')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('mobile', 'like', '%' . $search . '%');
                });
            })
            ->paginate(8);

        $admins->getCollection()->each(function (Admin $admin) use ($actor): void {
            $admin->setAttribute('can_be_managed', $this->authority->canManageAdmin($actor, $admin));
        });

        return view('admin.admin.index')->with(compact('title', 'admins', 'search', 'roles'));
    }

    public function create()
    {
        return redirect()->route('admin.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'integer', Rule::exists('roles', 'id')],
            'name' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9._-]+\z/', Rule::unique('admins', 'username')],
            'email' => ['nullable', 'email', 'max:50', Rule::unique('admins', 'email')],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'max:2048'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ]);

        $data['username'] = Str::lower(trim($data['username']));
        $data['email'] = isset($data['email']) ? Str::lower(trim($data['email'])) : null;
        abort_if(
            Admin::query()->whereRaw('LOWER(username) = ?', [$data['username']])->exists(),
            422,
            'That administrator username is already in use.'
        );
        abort_if(
            $data['email'] && Admin::query()->whereRaw('LOWER(email) = ?', [$data['email']])->exists(),
            422,
            'That administrator email address is already in use.'
        );

        $actor = $this->actor();
        $role = Role::query()->findOrFail($data['role']);
        $this->authority->assertCanAssignRole($actor, $role);
        $avatar = $request->hasFile('image') ? $this->avatars->store($request->file('image')) : null;
        $temporaryPassword = $this->temporaryPassword();

        try {
            $admin = DB::transaction(function () use ($actor, $data, $role, $avatar, $temporaryPassword): Admin {
                $lockedRole = $this->authority->lockRoleForMutation($role->id);
                $this->authority->assertCanAssignRole($actor, $lockedRole);

                $admin = Admin::query()->create([
                    'role' => (string) $lockedRole->id,
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'mobile' => $data['mobile'] ?? null,
                    'image' => $avatar,
                    'status' => 0,
                    'password' => Hash::make($temporaryPassword),
                    'must_change_password' => true,
                    'password_changed_at' => now(),
                ]);

                $this->audit->record($actor, 'admin.created', $admin, [
                    'role_id' => $lockedRole->id,
                    'status' => false,
                    'must_change_password' => true,
                    'avatar_set' => $avatar !== null,
                ]);

                return $admin;
            });
        } catch (UniqueConstraintViolationException) {
            $this->avatars->delete($avatar);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => 'That username or email address was registered by another request. Refresh and try again.',
            ]);
        } catch (\Throwable $exception) {
            $this->avatars->delete($avatar);
            throw $exception;
        }

        return back()->with([
            'temporary_password' => $temporaryPassword,
            'temporary_password_admin' => $admin->username,
            'message' => 'Administrator created with a one-time temporary password. Activate the account when it is ready for use.',
            'alert-type' => 'success',
        ]);
    }

    public function show($id = null, Request $request = null)
    {
        return redirect()->route('admin.index');
    }

    public function edit($id = null, Request $request = null)
    {
        $admin = Admin::query()->with('roleModel')->findOrFail($id);
        $this->authority->assertCanManageAdmin($this->actor(), $admin);

        if ($admin->image) {
            $admin->setAttribute('path', route('admin.image', $admin->image));
        }

        return response(['data' => $admin], 200);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', Rule::exists('admins', 'id')],
            'role' => ['required', 'integer', Rule::exists('roles', 'id')],
            'name' => ['required', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'max:2048'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ]);

        $actor = $this->actor();
        $candidate = Admin::query()->with('roleModel')->findOrFail($data['id']);
        $role = Role::query()->findOrFail($data['role']);
        $this->authority->assertCanManageAdmin($actor, $candidate);
        $this->authority->assertCanAssignRole($actor, $role);
        $newAvatar = $request->hasFile('image') ? $this->avatars->store($request->file('image')) : null;
        $oldAvatar = null;

        try {
            DB::transaction(function () use ($actor, $data, $role, $newAvatar, &$oldAvatar): void {
                $admin = $this->authority->lockAdminForMutation($data['id']);
                $this->authority->assertCanManageAdmin($actor, $admin);
                $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id);
                $this->authority->assertCanAssignRole($actor, $lockedRole);
                $this->authority->ensureActiveOwnerRemains($admin, $lockedRole);

                $before = [
                    'role_id' => (int) $admin->role,
                    'name' => $admin->name,
                    'mobile' => $admin->mobile,
                    'address' => $admin->address,
                    'avatar_set' => (bool) $admin->image,
                ];
                $oldAvatar = $admin->image;
                $admin->forceFill([
                    'role' => (string) $lockedRole->id,
                    'name' => $data['name'],
                    'address' => $data['address'] ?? null,
                    'mobile' => $data['mobile'] ?? null,
                    'image' => $newAvatar ?? $admin->image,
                ])->save();

                $this->audit->record($actor, 'admin.updated', $admin, [
                    'before' => $before,
                    'after' => [
                        'role_id' => (int) $admin->role,
                        'name' => $admin->name,
                        'mobile' => $admin->mobile,
                        'address' => $admin->address,
                        'avatar_set' => (bool) $admin->image,
                    ],
                ]);
            });
        } catch (\Throwable $exception) {
            $this->avatars->delete($newAvatar);
            throw $exception;
        }

        if ($newAvatar !== null && $oldAvatar !== $newAvatar) {
            $this->avatars->delete($oldAvatar);
        }

        return back()->with([
            'message' => data_get($request->Lang, 'Common.Form.UpdatedSuccessfully', 'Administrator updated successfully.'),
            'alert-type' => 'success',
        ]);
    }

    public function status($id, Request $request)
    {
        $actor = $this->actor();
        abort_if((int) $actor->id === (int) $id, 422, 'You cannot disable your own administrator account.');

        $admin = DB::transaction(function () use ($actor, $id): Admin {
            $admin = $this->authority->lockAdminForMutation($id);
            $this->authority->assertCanManageAdmin($actor, $admin);
            $nextStatus = !(bool) $admin->status;
            $this->authority->ensureActiveOwnerRemains($admin, replacementStatus: $nextStatus);

            $changes = ['status' => ['before' => (bool) $admin->status, 'after' => $nextStatus]];
            $values = ['status' => $nextStatus];
            if (!$nextStatus) {
                $values['auth_version'] = (int) $admin->auth_version + 1;
                $values['remember_token'] = Str::random(60);
            }
            $admin->forceFill($values)->save();
            $this->audit->record($actor, 'admin.status_changed', $admin, $changes);

            return $admin;
        });

        return response([
            'message' => $admin->status
                ? data_get($request->Lang, 'Common.Form.PublishSuccessfully', 'Administrator activated successfully.')
                : data_get($request->Lang, 'Common.Form.UnpublishSuccessfully', 'Administrator disabled successfully.'),
            'status' => (bool) $admin->status,
        ], 200);
    }

    public function destroy($id = null, Request $request = null)
    {
        $actor = $this->actor();
        abort_if((int) $actor->id === (int) $id, 422, 'You cannot delete your own administrator account.');
        $avatar = null;

        DB::transaction(function () use ($actor, $id, &$avatar): void {
            $admin = $this->authority->lockAdminForMutation($id);
            $this->authority->assertCanManageAdmin($actor, $admin);
            $this->authority->ensureActiveOwnerRemains($admin, deleting: true);
            $avatar = $admin->image;
            $admin->delete();
            $this->audit->record($actor, 'admin.deleted', $admin, [
                'role_id' => (int) $admin->role,
                'status' => (bool) $admin->status,
            ]);
        });

        $this->avatars->delete($avatar);

        return response([
            'message' => data_get($request?->Lang, 'Common.Form.DeleteSuccessfully', 'Administrator deleted successfully.'),
        ], 200);
    }

    public function passwordAuthEdit(Request $request)
    {
        $title = data_get($request->Lang, 'ChangePassword', 'Change password');
        $users = $this->actor();

        return view('admin.admin.change_password')->with(compact('title', 'users'));
    }

    public function passwordAuthChange(Request $request)
    {
        $actor = $this->actor();
        $rules = [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
        if (!$actor->must_change_password) {
            $rules['current_password'] = ['required', 'string'];
        }
        $data = $request->validate($rules);

        if (!$actor->must_change_password && !Hash::check($data['current_password'], $actor->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $nextAuthVersion = DB::transaction(function () use ($actor, $data): int {
            $admin = Admin::query()->lockForUpdate()->findOrFail($actor->id);
            $nextAuthVersion = (int) $admin->auth_version + 1;
            $admin->forceFill([
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
                'password_changed_at' => now(),
                'auth_version' => $nextAuthVersion,
                'remember_token' => Str::random(60),
            ])->save();
            $this->audit->record($admin, 'admin.password_changed', $admin, [
                'auth_version_incremented' => true,
                'must_change_password' => false,
            ]);

            return $nextAuthVersion;
        });

        $request->session()->put(Admin::SESSION_AUTH_VERSION, $nextAuthVersion);
        $request->session()->regenerate();

        return back()->with([
            'message' => data_get($request->Lang, 'Common.Form.PasswordChangedSuccessfully', 'Password changed successfully.'),
            'alert-type' => 'success',
        ]);
    }

    public function confirmResetPassword($id, Request $request)
    {
        $actor = $this->actor();
        abort_if((int) $actor->id === (int) $id, 422, 'Use Change Password to update your own password.');
        $admin = Admin::query()->with('roleModel')->findOrFail($id);
        $this->authority->assertCanManageAdmin($actor, $admin);
        $title = 'Reset administrator password';

        return view('admin.admin.reset_password')->with(compact('title', 'admin'));
    }

    public function resetPassword($id, Request $request)
    {
        $actor = $this->actor();
        abort_if((int) $actor->id === (int) $id, 422, 'Use Change Password to update your own password.');
        $temporaryPassword = $this->temporaryPassword();

        DB::transaction(function () use ($actor, $id, $temporaryPassword): void {
            $admin = $this->authority->lockAdminForMutation($id);
            $this->authority->assertCanManageAdmin($actor, $admin);
            $admin->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => now(),
                'auth_version' => (int) $admin->auth_version + 1,
                'remember_token' => Str::random(60),
            ])->save();
            $this->audit->record($actor, 'admin.password_reset', $admin, [
                'auth_version_incremented' => true,
                'must_change_password' => true,
            ]);
        });

        return back()->with([
            'temporary_password' => $temporaryPassword,
            'message' => 'A one-time temporary password was generated. The administrator must change it after signing in.',
            'alert-type' => 'success',
        ]);
    }

    public function image($path = null)
    {
        abort_unless($this->avatars->isSupportedStoredName($path), 404);
        $imageOwner = Admin::query()->where('image', $path)->firstOrFail();
        $actor = $this->actor();
        abort_unless(
            (int) $actor->id === (int) $imageOwner->id
                || app(Permission::class)->allows($actor, 'admin.index'),
            403
        );
        $image = $this->avatars->read($path);

        return response($image['bytes'], 200, [
            'Content-Type' => $image['mime'],
            'Content-Disposition' => 'inline; filename="administrator-avatar"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function actor(): Admin
    {
        return Auth::guard('admin')->user() ?? abort(401);
    }

    private function temporaryPassword(): string
    {
        return Str::random(20) . '!9aA';
    }
}
