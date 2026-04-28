<?php

namespace Tests\Feature;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class AdiantamentoValorPagamentoTest extends TestCase
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

    private function criarEmprestimo1xParcelaFutura(): Parcela
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725',
            'nome' => 'Cliente Adiantamento',
            'empresa_id' => $this->empresa->id,
        ]);

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 600,
            'numero_parcelas' => 1,
            'frequencia' => 'mensal',
            'data_inicio' => Carbon::parse('2026-04-25'),
            'status' => 'ativo',
            'tipo' => 'dinheiro',
        ]);

        LiberacaoEmprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $emprestimo->id,
            'consultor_id' => $this->userConsultor->id,
            'valor_liberado' => 600,
            'status' => 'pago_ao_cliente',
            'pago_ao_cliente_em' => now(),
            'empresa_id' => $this->empresa->id,
        ]);

        return Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $emprestimo->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 780,
            'valor_juros' => 180,
            'valor_amortizacao' => 600,
            'valor_pago' => 0,
            'data_vencimento' => Carbon::parse('2026-05-25'),
            'status' => 'pendente',
            'dias_atraso' => 0,
        ]);
    }

    /** @test */
    public function consultor_registra_adiantamento_parcial_e_depois_quita_com_pagamento_normal(): void
    {
        Carbon::setTestNow('2026-04-27');
        $parcela = $this->criarEmprestimo1xParcelaFutura();
        $this->assertTrue($parcela->fresh()->podeAdiantarValor());

        $this->actingAs($this->userConsultor)->post(route('pagamentos.store'), [
            'parcela_id' => $parcela->id,
            'valor' => 200,
            'metodo' => 'dinheiro',
            'data_pagamento' => '2026-04-27',
            'tipo_juros' => 'nenhum',
            'adiantamento_valor' => '1',
        ])->assertRedirect(route('emprestimos.show', $parcela->emprestimo_id))
            ->assertSessionHas('success');

        $parcela->refresh();
        $this->assertEquals(200.0, (float) $parcela->valor_pago);
        $this->assertSame('pendente', $parcela->status);

        $this->actingAs($this->userConsultor)->post(route('pagamentos.store'), [
            'parcela_id' => $parcela->id,
            'valor' => 580,
            'metodo' => 'pix',
            'data_pagamento' => '2026-05-25',
            'tipo_juros' => 'nenhum',
        ])->assertRedirect(route('emprestimos.show', $parcela->emprestimo_id));

        $parcela->refresh();
        $this->assertEquals(780.0, (float) $parcela->valor_pago);
        $this->assertSame('paga', $parcela->status);
        Carbon::setTestNow();
    }

    /** @test */
    public function parcela_pode_adiantar_valor_retorna_false_quando_atrasada(): void
    {
        Carbon::setTestNow('2026-06-01');
        $parcela = $this->criarEmprestimo1xParcelaFutura();
        $parcela->update(['data_vencimento' => Carbon::parse('2026-05-10')]);
        $this->assertTrue($parcela->fresh()->isAtrasada());
        $this->assertFalse($parcela->fresh()->podeAdiantarValor());
        Carbon::setTestNow();
    }
}
