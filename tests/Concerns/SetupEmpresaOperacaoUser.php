<?php

namespace Tests\Concerns;

use App\Models\Scopes\EmpresaScope;
use App\Models\User;
use App\Modules\Core\Models\Empresa;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Role;
use Illuminate\Support\Facades\Hash;

trait SetupEmpresaOperacaoUser
{
    protected ?Empresa $empresa = null;
    protected ?Operacao $operacao = null;
    protected ?User $userConsultor = null;

    /**
     * Cria empresa, operação e usuário consultor para testes.
     * Usar após RefreshDatabase (migrations + seeders de roles/permissions).
     */
    protected function setupEmpresaOperacaoUser(): void
    {
        $this->empresa = Empresa::create([
            'nome' => 'Empresa Teste',
            'razao_social' => 'Empresa Teste Ltda',
            'status' => 'ativa',
            'plano' => 'basico',
            'data_ativacao' => now(),
            'configuracoes' => [],
        ]);

        $this->operacao = Operacao::withoutGlobalScope(EmpresaScope::class)->create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Operação Teste',
            'codigo' => 'OPTEST',
            'descricao' => 'Operação para testes',
            'ativo' => true,
        ]);

        $roleConsultor = Role::where('name', 'consultor')->first();
        if (! $roleConsultor) {
            Role::create(['name' => 'consultor', 'display_name' => 'Consultor', 'description' => 'Consultor']);
            $roleConsultor = Role::where('name', 'consultor')->first();
        }

        $this->userConsultor = User::create([
            'name' => 'Consultor Teste',
            'email' => 'consultor-teste@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->userConsultor->roles()->attach($roleConsultor->id);
        $this->userConsultor->operacoes()->attach($this->operacao->id);
    }
}
