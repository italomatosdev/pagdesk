<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Clientes
            ['name' => 'criar_cliente', 'display_name' => 'Criar Cliente'],
            ['name' => 'editar_cliente', 'display_name' => 'Editar Cliente'],
            ['name' => 'visualizar_cliente', 'display_name' => 'Visualizar Cliente'],
            ['name' => 'vincular_cliente_operacao', 'display_name' => 'Vincular Cliente a Operação'],
            ['name' => 'alterar_limite_credito', 'display_name' => 'Alterar Limite de Crédito'],

            // Empréstimos
            ['name' => 'criar_emprestimo', 'display_name' => 'Criar Empréstimo'],
            ['name' => 'visualizar_emprestimo', 'display_name' => 'Visualizar Empréstimo'],
            ['name' => 'aprovar_emprestimo', 'display_name' => 'Aprovar Empréstimo'],
            ['name' => 'rejeitar_emprestimo', 'display_name' => 'Rejeitar Empréstimo'],

            // Pagamentos
            ['name' => 'registrar_pagamento', 'display_name' => 'Registrar Pagamento'],
            ['name' => 'visualizar_pagamento', 'display_name' => 'Visualizar Pagamento'],

            // Cobranças
            ['name' => 'ver_cobrancas_dia', 'display_name' => 'Ver Cobranças do Dia'],

            // Caixa
            ['name' => 'ver_caixa', 'display_name' => 'Ver Movimentações de Caixa'],
            ['name' => 'criar_settlement', 'display_name' => 'Criar Prestação de Contas'],
            ['name' => 'conferir_settlement', 'display_name' => 'Conferir Prestação de Contas'],
            ['name' => 'validar_settlement', 'display_name' => 'Validar Prestação de Contas'],

            // Operações
            ['name' => 'gerenciar_operacoes', 'display_name' => 'Gerenciar Operações'],

            // Usuários
            ['name' => 'gerenciar_usuarios', 'display_name' => 'Gerenciar Usuários'],
            ['name' => 'gerenciar_permissoes', 'display_name' => 'Gerenciar Permissões'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Atribuir permissões aos papéis
        $this->atribuirPermissoes();

        $this->command->info('Permissões criadas e atribuídas com sucesso!');
    }

    private function atribuirPermissoes(): void
    {
        $admin = Role::where('name', 'administrador')->first();
        $gestor = Role::where('name', 'gestor')->first();
        $consultor = Role::where('name', 'consultor')->first();

        // Administrador: todas as permissões
        $admin->permissions()->sync(Permission::pluck('id'));

        // Gestor: visualização e conferência
        $gestor->permissions()->sync(Permission::whereIn('name', [
            'visualizar_cliente',
            'visualizar_emprestimo',
            'visualizar_pagamento',
            'ver_cobrancas_dia',
            'ver_caixa',
            'conferir_settlement',
        ])->pluck('id'));

        // Consultor: operações do dia a dia
        $consultor->permissions()->sync(Permission::whereIn('name', [
            'criar_cliente',
            'editar_cliente',
            'visualizar_cliente',
            'vincular_cliente_operacao',
            'criar_emprestimo',
            'visualizar_emprestimo',
            'registrar_pagamento',
            'ver_cobrancas_dia',
            'ver_caixa',
            'criar_settlement',
        ])->pluck('id'));
    }
}
