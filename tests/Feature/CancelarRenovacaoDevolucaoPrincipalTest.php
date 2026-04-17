<?php

namespace Tests\Feature;

use App\Models\Scopes\EmpresaScope;
use App\Models\User;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Role;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class CancelarRenovacaoDevolucaoPrincipalTest extends TestCase
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
            'name' => 'Gestor Renovação',
            'email' => 'gestor-renovacao-cancel@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->gestor->roles()->attach($roleGestor->id);
        $this->gestor->operacoes()->attach($this->operacao->id, ['role' => 'gestor']);
    }

    /** @test */
    public function gestor_cancela_renovacao_sem_parcelas_pagas_registra_entrada_e_nao_altera_origem(): void
    {
        [$origem, $renovado] = $this->criarOrigemFinalizadoERenovadoAtivo();

        $statusOrigemAntes = $origem->fresh()->status;
        $valorRenovado = (float) $renovado->valor_total;

        $response = $this->actingAs($this->gestor)->post(
            route('emprestimos.cancelar-renovacao-devolucao-principal', $renovado->id),
            [
                '_token' => csrf_token(),
                'motivo_cancelamento' => 'Cancelamento de teste com motivo suficiente.',
                'confirmacao_devolucao_principal' => '1',
            ]
        );

        $response->assertRedirect(route('emprestimos.show', $renovado->id));

        $origem->refresh();
        $renovado->refresh();

        $this->assertSame($statusOrigemAntes, $origem->status);
        $this->assertTrue($renovado->isCancelado());

        $this->assertDatabaseHas('cash_ledger_entries', [
            'operacao_id' => $this->operacao->id,
            'consultor_id' => $this->userConsultor->id,
            'tipo' => 'entrada',
            'referencia_tipo' => 'devolucao_principal_cancelamento_renovacao',
            'referencia_id' => $renovado->id,
        ]);

        $entry = CashLedgerEntry::withoutGlobalScope(EmpresaScope::class)
            ->where('referencia_tipo', 'devolucao_principal_cancelamento_renovacao')
            ->where('referencia_id', $renovado->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertEquals($valorRenovado, (float) $entry->valor);
    }

    /** @test */
    public function validacao_falha_sem_checkbox_de_confirmacao(): void
    {
        [, $renovado] = $this->criarOrigemFinalizadoERenovadoAtivo();

        $response = $this->actingAs($this->gestor)->from(route('emprestimos.show', $renovado->id))->post(
            route('emprestimos.cancelar-renovacao-devolucao-principal', $renovado->id),
            [
                '_token' => csrf_token(),
                'motivo_cancelamento' => 'Cancelamento de teste com motivo suficiente.',
            ]
        );

        $response->assertRedirect(route('emprestimos.show', $renovado->id));
        $response->assertSessionHasErrors('confirmacao_devolucao_principal');

        $this->assertFalse($renovado->fresh()->isCancelado());
    }

    /** @test */
    public function nao_permite_quando_renovado_tem_parcela_paga(): void
    {
        [, $renovado] = $this->criarOrigemFinalizadoERenovadoAtivo(comPagamentoNaRenovacao: true);

        $response = $this->actingAs($this->gestor)->from(route('emprestimos.show', $renovado->id))->post(
            route('emprestimos.cancelar-renovacao-devolucao-principal', $renovado->id),
            [
                '_token' => csrf_token(),
                'motivo_cancelamento' => 'Cancelamento de teste com motivo suficiente.',
                'confirmacao_devolucao_principal' => '1',
            ]
        );

        $response->assertRedirect(route('emprestimos.show', $renovado->id));
        $response->assertSessionHasErrors('emprestimo');

        $this->assertFalse($renovado->fresh()->isCancelado());
    }

    /**
     * @return array{0: Emprestimo, 1: Emprestimo}
     */
    private function criarOrigemFinalizadoERenovadoAtivo(bool $comPagamentoNaRenovacao = false): array
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224799',
            'nome' => 'Cliente Renovação Cancel',
            'empresa_id' => $this->empresa->id,
        ]);

        $origem = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 10000,
            'numero_parcelas' => 1,
            'frequencia' => 'mensal',
            'data_inicio' => now(),
            'status' => 'finalizado',
            'tipo' => 'dinheiro',
            'emprestimo_origem_id' => null,
        ]);

        Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $origem->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 12000,
            'valor_pago' => 2000,
            'data_vencimento' => now()->addMonth(),
            'data_pagamento' => now(),
            'status' => 'paga',
            'dias_atraso' => 0,
        ]);

        $renovado = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 10000,
            'numero_parcelas' => 1,
            'frequencia' => 'mensal',
            'data_inicio' => now(),
            'status' => 'ativo',
            'tipo' => 'dinheiro',
            'emprestimo_origem_id' => $origem->id,
        ]);

        $parcelaRenovada = Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $renovado->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 12000,
            'valor_pago' => 0,
            'data_vencimento' => now()->addMonth(),
            'data_pagamento' => null,
            'status' => 'pendente',
            'dias_atraso' => 0,
        ]);

        if ($comPagamentoNaRenovacao) {
            Pagamento::create([
                'parcela_id' => $parcelaRenovada->id,
                'consultor_id' => $this->userConsultor->id,
                'valor' => 100,
                'metodo' => 'dinheiro',
                'data_pagamento' => now(),
            ]);
        }

        return [$origem, $renovado];
    }
}
