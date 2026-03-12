<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar Super Admin (acesso total ao sistema, sem empresa)
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@sistema-cred.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
                'is_super_admin' => true,
                'empresa_id' => null,
            ]
        );

        // Criar usuário administrador
        $admin = User::updateOrCreate(
            ['email' => 'admin@sistema-cred.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );

        // Atribuir papel de administrador
        $roleAdmin = Role::where('name', 'administrador')->first();
        if ($roleAdmin && !$admin->roles->contains($roleAdmin->id)) {
            $admin->roles()->attach($roleAdmin->id);
        }

        // Criar usuário gestor de exemplo
        $gestor = User::updateOrCreate(
            ['email' => 'gestor@sistema-cred.com'],
            [
                'name' => 'Gestor Exemplo',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );

        $roleGestor = Role::where('name', 'gestor')->first();
        if ($roleGestor && !$gestor->roles->contains($roleGestor->id)) {
            $gestor->roles()->attach($roleGestor->id);
        }

        // Criar usuário consultor de exemplo
        $consultor = User::updateOrCreate(
            ['email' => 'consultor@sistema-cred.com'],
            [
                'name' => 'Consultor Exemplo',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );

        $roleConsultor = Role::where('name', 'consultor')->first();
        if ($roleConsultor && !$consultor->roles->contains($roleConsultor->id)) {
            $consultor->roles()->attach($roleConsultor->id);
        }

        $this->command->info('Usuários criados com sucesso!');
        $this->command->info('Super Admin: superadmin@sistema-cred.com / 12345678');
        $this->command->info('Admin: admin@sistema-cred.com / 12345678');
        $this->command->info('Gestor: gestor@sistema-cred.com / 12345678');
        $this->command->info('Consultor: consultor@sistema-cred.com / 12345678');
    }
}
