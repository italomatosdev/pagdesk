<?php

namespace Tests\Unit\Services;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\PagamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class PagamentoServiceTest extends TestCase
{
    use RefreshDatabase;
    use SetupEmpresaOperacaoUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->setupEmpresaOperacaoUser();
    }

    /** @test */
    public function nao_registra_pagamento_quando_emprestimo_nao_esta_ativo(): void
    {
        $this->actingAs($this->userConsultor);

        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725',
            'nome' => 'Cliente Pagamento',
            'empresa_id' => $this->empresa->id,
        ]);

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 1000,
            'numero_parcelas' => 1,
            'frequencia' => 'mensal',
            'data_inicio' => now(),
            'status' => 'cancelado', // não ativo
            'tipo' => 'dinheiro',
        ]);

        $parcela = Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $emprestimo->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 1000,
            'valor_pago' => 0,
            'data_vencimento' => now(),
            'status' => 'pendente',
        ]);

        $service = app(PagamentoService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('empréstimo precisa estar ATIVO');

        $service->registrar([
            'parcela_id' => $parcela->id,
            'consultor_id' => $this->userConsultor->id,
            'valor' => 1000,
        ]);
    }
}
