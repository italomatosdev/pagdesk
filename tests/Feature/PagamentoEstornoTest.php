<?php

namespace Tests\Feature;

use App\Models\Scopes\EmpresaScope;
use App\Models\User;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Role;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class PagamentoEstornoTest extends TestCase
{
    use RefreshDatabase;
    use SetupEmpresaOperacaoUser;

    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->setupEmpresaOperacaoUser();

        $roleGestor = Role::where('name', 'gestor')->first();
        $this->gestor = User::create([
            'name' => 'Gestor Estorno',
            'email' => 'gestor-estorno@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->gestor->roles()->attach($roleGestor->id);
        $this->gestor->operacoes()->attach($this->operacao->id, ['role' => 'gestor']);
    }

    /**
     * @return array{0: Emprestimo, 1: Parcela, 2: Pagamento, 3: CashLedgerEntry}
     */
    private function criarEmprestimoParcelaPagamentoELedger(?Carbon $dataMovimentacao = null): array
    {
        $dataMovimentacao = $dataMovimentacao ?? Carbon::today();

        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725',
            'nome' => 'Cliente Estorno',
            'empresa_id' => $this->empresa->id,
        ]);

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 500,
            'numero_parcelas' => 1,
            'frequencia' => 'mensal',
            'data_inicio' => now(),
            'status' => 'ativo',
            'tipo' => 'dinheiro',
        ]);

        LiberacaoEmprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $emprestimo->id,
            'consultor_id' => $this->userConsultor->id,
            'valor_liberado' => 500,
            'status' => 'pago_ao_cliente',
            'pago_ao_cliente_em' => now(),
            'empresa_id' => $this->empresa->id,
        ]);

        $parcela = Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $emprestimo->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 500,
            'valor_pago' => 500,
            'data_vencimento' => $dataMovimentacao,
            'data_pagamento' => $dataMovimentacao,
            'status' => 'paga',
            'dias_atraso' => 0,
        ]);

        $pagamento = Pagamento::create([
            'parcela_id' => $parcela->id,
            'consultor_id' => $this->userConsultor->id,
            'valor' => 500,
            'metodo' => 'dinheiro',
            'data_pagamento' => $dataMovimentacao->format('Y-m-d'),
        ]);

        $ledger = CashLedgerEntry::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'consultor_id' => $this->userConsultor->id,
            'pagamento_id' => $pagamento->id,
            'tipo' => 'entrada',
            'origem' => 'automatica',
            'valor' => 500,
            'descricao' => 'Pagamento teste',
            'data_movimentacao' => $dataMovimentacao->format('Y-m-d'),
            'referencia_tipo' => 'pagamento_parcela',
            'referencia_id' => $parcela->id,
            'empresa_id' => $this->empresa->id,
        ]);

        return [$emprestimo, $parcela, $pagamento, $ledger];
    }

    /** @test */
    public function gestor_pode_estornar_quando_lancamento_nao_esta_consolidado_e_saldo_reflete_saida(): void
    {
        [$emprestimo, $parcela, $pagamento] = $this->criarEmprestimoParcelaPagamentoELedger(Carbon::parse('2026-01-10'));

        $cash = app(CashService::class);
        $saldoAntes = $cash->calcularSaldo($this->userConsultor->id, $this->operacao->id);
        $this->assertEquals(500.0, $saldoAntes);

        $response = $this->actingAs($this->gestor)->post(
            route('pagamentos.estorno', $pagamento->id),
            ['motivo' => 'Correção de lançamento duplicado.', '_token' => csrf_token()]
        );

        $response->assertRedirect(route('emprestimos.show', $emprestimo->id));
        $response->assertSessionHas('success');

        $parcela->refresh();
        $this->assertEquals(0, (float) $parcela->valor_pago);
        $this->assertEquals('pendente', $parcela->status);

        $pagamento->refresh();
        $this->assertNotNull($pagamento->estornado_em);
        $this->assertSame('Correção de lançamento duplicado.', $pagamento->estorno_motivo);

        $saldoDepois = $cash->calcularSaldo($this->userConsultor->id, $this->operacao->id);
        $this->assertEquals(0.0, $saldoDepois);
    }

    /** @test */
    public function estorno_e_bloqueado_quando_lancamento_ja_entrou_em_fechamento_concluido(): void
    {
        $dataMov = Carbon::parse('2026-02-08');
        [$emprestimo, $parcela, $pagamento, $ledger] = $this->criarEmprestimoParcelaPagamentoELedger($dataMov);

        $ledger->forceFill(['created_at' => Carbon::parse('2026-02-08 10:00:00')])->save();

        Settlement::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'consultor_id' => $this->userConsultor->id,
            'criado_por' => $this->gestor->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-02-10',
            'valor_total' => 100.00,
            'empresa_id' => $this->empresa->id,
            'status' => 'concluido',
            'recebido_por' => $this->gestor->id,
            'recebido_em' => Carbon::parse('2026-02-10 18:00:00'),
        ]);

        $response = $this->actingAs($this->gestor)->post(
            route('pagamentos.estorno', $pagamento->id),
            ['motivo' => 'Tentativa após fechamento.', '_token' => csrf_token()]
        );

        $response->assertSessionHasErrors('pagamento');
        $pagamento->refresh();
        $this->assertNull($pagamento->estornado_em);
    }

    /** @test */
    public function consultor_nao_autorizado_recebe_403(): void
    {
        [$emprestimo, $parcela, $pagamento] = $this->criarEmprestimoParcelaPagamentoELedger();

        $response = $this->actingAs($this->userConsultor)->post(
            route('pagamentos.estorno', $pagamento->id),
            ['motivo' => 'X', '_token' => csrf_token()]
        );

        $response->assertForbidden();
    }

    /** @test */
    public function movimento_no_ultimo_dia_apos_recebido_em_permite_estorno(): void
    {
        $fim = Carbon::parse('2026-02-10');
        [$emprestimo, $parcela, $pagamento, $ledger] = $this->criarEmprestimoParcelaPagamentoELedger($fim);

        $ledger->forceFill([
            'data_movimentacao' => $fim->format('Y-m-d'),
            'created_at' => $fim->copy()->setTime(18, 0, 0),
        ])->save();

        Settlement::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'consultor_id' => $this->userConsultor->id,
            'criado_por' => $this->gestor->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-02-10',
            'valor_total' => 100.00,
            'empresa_id' => $this->empresa->id,
            'status' => 'concluido',
            'recebido_por' => $this->gestor->id,
            'recebido_em' => $fim->copy()->setTime(12, 0, 0),
        ]);

        $response = $this->actingAs($this->gestor)->post(
            route('pagamentos.estorno', $pagamento->id),
            ['motivo' => 'Movimento posterior ao recebimento do fechamento.', '_token' => csrf_token()]
        );

        $response->assertRedirect(route('emprestimos.show', $emprestimo->id));
        $pagamento->refresh();
        $this->assertNotNull($pagamento->estornado_em);
    }
}
