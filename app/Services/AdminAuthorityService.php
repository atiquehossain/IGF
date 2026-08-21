<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminAuthorityService
{
    public const OWNER_RANK = 0;

    public function actorRole(Admin $actor): ?Role
    {
        return $actor->relationLoaded('roleModel')
            ? $actor->roleModel
            : Role::query()->whereKey($actor->role)->where('status', 1)->first();
    }

    public function isOwner(Admin $actor): bool
    {
        $role = $this->actorRole($actor);

        return $role !== null && (bool) $role->status && (bool) $role->is_owner;
    }

    public function assertOwner(Admin $actor): void
    {
        abort_unless(
            $this->isOwner($actor),
            403,
            'Only a deployment owner may change the permission schema.'
        );
    }

    /**
     * Prevent delegated role managers from granting capabilities they do not
     * currently hold. The actor role is re-read under a database lock so a
     * concurrent revocation cannot race a permission grant.
     *
     * @param Collection<int, int> $menuIds
     * @param Collection<int, int> $actionIds
     */
    public function assertCanDelegatePermissions(Admin $actor, Collection $menuIds, Collection $actionIds): array
    {
        $actorRole = Role::query()->lockForUpdate()->find($actor->role);
        abort_unless($actorRole && (bool) $actorRole->status, 403, 'Your administrator role is no longer active.');

        if ((bool) $actorRole->is_owner) {
            return [null, null];
        }

        $ownedMenuIds = $this->ids($actorRole->permission);
        $ownedActionIds = $this->ids($actorRole->actionPermission);

        $activeMenuIds = \App\Models\AuthMenu::query()
            ->whereIn('id', $ownedMenuIds)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $activeActionIds = \App\Models\MenuAction::query()
            ->whereIn('id', $ownedActionIds)
            ->where('status', 1)
            ->whereHas('authMenu', fn (Builder $query) => $query->where('status', 1))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        abort_unless(
            $menuIds->diff($activeMenuIds)->isEmpty() && $actionIds->diff($activeActionIds)->isEmpty(),
            403,
            'You may only delegate active permissions that your own role currently holds.'
        );

        return [$activeMenuIds, $activeActionIds];
    }

    public function canManageAdmin(Admin $actor, Admin $target): bool
    {
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            return false;
        }

        $actorRole = $this->actorRole($actor);
        $targetRole = $target->relationLoaded('roleModel')
            ? $target->roleModel
            : Role::query()->whereKey($target->role)->first();

        return $actorRole !== null
            && $targetRole !== null
            && (bool) $actorRole->status
            && (int) $actorRole->security_rank < (int) $targetRole->security_rank;
    }

    public function assertCanManageAdmin(Admin $actor, Admin $target): void
    {
        abort_unless(
            $this->canManageAdmin($actor, $target),
            403,
            'You may only manage administrators with a lower authority rank than your own.'
        );
    }

    public function canManageRole(Admin $actor, Role $target): bool
    {
        $actorRole = $this->actorRole($actor);

        return $actorRole !== null
            && (bool) $actorRole->status
            && !(bool) $target->is_owner
            && (int) $actorRole->security_rank < (int) $target->security_rank;
    }

    public function assertCanManageRole(Admin $actor, Role $target): void
    {
        abort_unless(
            $this->canManageRole($actor, $target),
            403,
            'The reserved owner role and roles at or above your authority rank cannot be changed.'
        );
    }

    public function assertCanAssignRole(Admin $actor, Role $role): void
    {
        $actorRole = $this->actorRole($actor);

        abort_unless(
            $actorRole
                && (bool) $role->status
                && !(bool) $role->is_owner
                && (int) $actorRole->security_rank < (int) $role->security_rank,
            422,
            'Choose an active role with a lower authority rank than your own.'
        );
    }

    public function assignableRoles(Admin $actor): Collection
    {
        $actorRole = $this->actorRole($actor);
        if (!$actorRole) {
            return collect();
        }

        return Role::query()
            ->where('status', 1)
            ->where('is_owner', false)
            ->where('security_rank', '>', (int) $actorRole->security_rank)
            ->orderBy('security_rank')
            ->orderBy('name')
            ->get();
    }

    public function lockAdminForMutation(int|string $id): Admin
    {
        $this->lockOwnerRoles();

        return Admin::query()->with('roleModel')->lockForUpdate()->findOrFail($id);
    }

    public function lockRoleForMutation(int|string $id): Role
    {
        $this->lockOwnerRoles();

        return Role::query()->lockForUpdate()->findOrFail($id);
    }

    public function ensureActiveOwnerRemains(
        Admin $target,
        ?Role $replacementRole = null,
        ?bool $replacementStatus = null,
        bool $deleting = false
    ): void {
        $targetRole = $target->roleModel ?: Role::query()->whereKey($target->role)->first();
        $losesActiveOwnership = (bool) $target->status
            && (bool) $targetRole?->is_owner
            && ($deleting
                || $replacementStatus === false
                || ($replacementRole !== null && !(bool) $replacementRole->is_owner));

        if (!$losesActiveOwnership) {
            return;
        }

        $ownerRoleIds = Role::query()->where('is_owner', true)->lockForUpdate()->pluck('id');
        $activeOwnerCount = Admin::query()
            ->where('status', 1)
            ->whereIn('role', $ownerRoleIds->map(fn ($id) => (string) $id))
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        abort_if($activeOwnerCount <= 1, 409, 'The final active deployment owner cannot be disabled, deleted, or demoted.');
    }

    private function lockOwnerRoles(): void
    {
        Role::query()->where('is_owner', true)->lockForUpdate()->get();
    }

    /** @return Collection<int, int> */
    private function ids(?string $value): Collection
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($id) => filter_var(trim($id), FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
