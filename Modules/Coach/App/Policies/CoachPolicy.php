<?php

namespace Modules\Coach\App\Policies;

use App\Models\User;
use Modules\Coach\App\Models\Coach;
use Illuminate\Auth\Access\HandlesAuthorization;

class CoachPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_coach');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Coach $coach): bool
    {
        return $user->can('view_coach');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_coach');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Coach $coach): bool
    {
        return $user->can('update_coach');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Coach $coach): bool
    {
        return $user->can('delete_coach');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_coach');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Coach $coach): bool
    {
        return $user->can('force_delete_coach');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_coach');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Coach $coach): bool
    {
        return $user->can('restore_coach');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_coach');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Coach $coach): bool
    {
        return $user->can('replicate_coach');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_coach');
    }
}
