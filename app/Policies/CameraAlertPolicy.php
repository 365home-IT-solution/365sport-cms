<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CameraAlert;
use Illuminate\Auth\Access\HandlesAuthorization;

class CameraAlertPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_camera::alert');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CameraAlert $cameraAlert): bool
    {
        return $user->can('view_camera::alert');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_camera::alert');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CameraAlert $cameraAlert): bool
    {
        return $user->can('update_camera::alert');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CameraAlert $cameraAlert): bool
    {
        return $user->can('delete_camera::alert');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_camera::alert');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, CameraAlert $cameraAlert): bool
    {
        return $user->can('force_delete_camera::alert');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_camera::alert');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, CameraAlert $cameraAlert): bool
    {
        return $user->can('restore_camera::alert');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_camera::alert');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, CameraAlert $cameraAlert): bool
    {
        return $user->can('replicate_camera::alert');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_camera::alert');
    }
}
