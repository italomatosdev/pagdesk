<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Core\Models\Empresa;
use App\Modules\Core\Models\Operacao;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Criar empresa padrão
            $empresa = Empresa::firstOrCreate(
                ['nome' => 'Empresa Principal'],
                [
                    'razao_social' => 'Empresa Principal',
                    'status' => 'ativa',
                    'plano' => 'enterprise',
                    'data_ativacao' => now(),
                    'configuracoes' => [
                        'workflow' => [
                            'requer_aprovacao' => true,
                            'requer_liberacao' => true,
                            'aprovacao_automatica_valor_max' => 0,
                        ],
                        'operacoes' => [
                            'permite_multiplas_operacoes' => true,
                        ],
                    ],
                ]
            );

            // Criar operação padrão se não existir
            $operacao = Operacao::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'nome' => 'Operação Principal',
                ],
                [
                    'codigo' => 'OP001',
                    'descricao' => 'Operação principal da empresa',
                    'ativo' => true,
                ]
            );

            // Criar super admin se não existir
            $superAdmin = User::firstOrCreate(
                ['email' => 'superadmin@pagdesk.com'],
                [
                    'name' => 'Super Administrador',
                    'password' => Hash::make('superadmin123'),
                    'is_super_admin' => true,
                    'empresa_id' => null, // Super admin não tem empresa
                    'email_verified_at' => now(),
                ]
            );

            // Criar administrador da empresa padrão se não existir
            $adminEmpresa = User::firstOrCreate(
                ['email' => 'admin@pagdesk.com'],
                [
                    'name' => 'Administrador',
                    'password' => Hash::make('admin123'),
                    'is_super_admin' => false,
                    'empresa_id' => $empresa->id,
                    'operacao_id' => $operacao->id,
                    'email_verified_at' => now(),
                ]
            );

            // Atribuir papel de administrador ao admin da empresa
            $roleAdmin = \App\Modules\Core\Models\Role::where('name', 'administrador')->first();
            if ($roleAdmin && !$adminEmpresa->roles()->where('name', 'administrador')->exists()) {
                $adminEmpresa->roles()->attach($roleAdmin->id);
            }

            // Vincular admin à operação
            if (!$adminEmpresa->operacoes()->where('operacoes.id', $operacao->id)->exists()) {
                $adminEmpresa->operacoes()->attach($operacao->id);
            }

            $this->command->info("Empresa padrão criada: {$empresa->nome} (ID: {$empresa->id})");
            $this->command->info("Operação padrão criada: {$operacao->nome} (ID: {$operacao->id})");
            $this->command->info("Super Admin criado: {$superAdmin->email}");
            $this->command->info("Admin da empresa criado: {$adminEmpresa->email}");
        });
    }
}
