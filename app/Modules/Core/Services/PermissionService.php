<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Traits\Auditable;
use App\Models\User;

class PermissionService
{
    use Auditable;

    /**
     * Atribuir papel a usuário
     *
     * @param int $userId
     * @param string $roleName
     * @return User
     */
    public function atribuirPapel(int $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);
        $role = Role::where('name', $roleName)->firstOrFail();

        if (!$user->roles->contains($role->id)) {
            $user->roles()->attach($role->id);

            // Auditoria
            self::auditar('atribuir_papel', $user, null, ['role' => $roleName]);
        }

        return $user->fresh();
    }

    /**
     * Remover papel de usuário
     *
     * @param int $userId
     * @param string $roleName
     * @return User
     */
    public function removerPapel(int $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);
        $role = Role::where('name', $roleName)->firstOrFail();

        $user->roles()->detach($role->id);

        // Auditoria
        self::auditar('remover_papel', $user, ['role' => $roleName], null);

        return $user->fresh();
    }

    /**
     * Atribuir permissão a papel
     *
     * @param string $roleName
     * @param string $permissionName
     * @return Role
     */
    public function atribuirPermissao(string $roleName, string $permissionName): Role
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $permission = Permission::where('name', $permissionName)->firstOrFail();

        if (!$role->permissions->contains($permission->id)) {
            $role->permissions()->attach($permission->id);

            // Auditoria
            self::auditar('atribuir_permissao', $role, null, ['permission' => $permissionName]);
        }

        return $role->fresh();
    }

    /**
     * Remover permissão de papel
     *
     * @param string $roleName
     * @param string $permissionName
     * @return Role
     */
    public function removerPermissao(string $roleName, string $permissionName): Role
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $permission = Permission::where('name', $permissionName)->firstOrFail();

        $role->permissions()->detach($permission->id);

        // Auditoria
        self::auditar('remover_permissao', $role, ['permission' => $permissionName], null);

        return $role->fresh();
    }
}
