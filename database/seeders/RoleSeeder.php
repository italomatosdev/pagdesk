<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'administrador',
                'display_name' => 'Administrador',
                'description' => 'Acesso total ao sistema. Pode aprovar exceções, gerenciar operações e usuários.',
            ],
            [
                'name' => 'gestor',
                'display_name' => 'Gestor',
                'description' => 'Pode visualizar empréstimos, parcelas, pagamentos e conferir prestações de contas.',
            ],
            [
                'name' => 'consultor',
                'display_name' => 'Consultor',
                'description' => 'Pode criar clientes, empréstimos, ver cobranças do dia, registrar pagamentos e criar prestações de contas.',
            ],
            [
                'name' => 'cliente',
                'display_name' => 'Cliente',
                'description' => 'Papel para clientes (sem acesso ao sistema nesta fase).',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }

        $this->command->info('Papéis criados com sucesso!');
    }
}
