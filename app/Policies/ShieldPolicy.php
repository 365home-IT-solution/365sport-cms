<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

// Policy dùng chung cho các resource Filament Shield: mỗi quyền có dạng "{hành động}_{tên resource}" (vd view_any_payroll::period).
// Lớp con chỉ khai báo tên resource trong $resource — giống hệt các policy Shield sinh sẵn nhưng không phải lặp 12 phương thức.
abstract class ShieldPolicy
{
    use HandlesAuthorization;

    /** Tên resource trong quyền Shield, vd 'payroll::period'. */
    protected string $resource;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_' . $this->resource);
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('view_' . $this->resource);
    }

    public function create(User $user): bool
    {
        return $user->can('create_' . $this->resource);
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('update_' . $this->resource);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('delete_' . $this->resource);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_' . $this->resource);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->can('force_delete_' . $this->resource);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_' . $this->resource);
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can('restore_' . $this->resource);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_' . $this->resource);
    }

    public function replicate(User $user, Model $record): bool
    {
        return $user->can('replicate_' . $this->resource);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_' . $this->resource);
    }
}
