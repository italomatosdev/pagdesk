<?php

namespace Tests\Feature;

use App\Models\Scopes\EmpresaScope;
use App\Models\User;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\CorrecaoVencimentoDomingoLegadoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class CorrecaoVencimentoDomingoLegadoTest extends TestCase
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
    public function preview_retorna_alteracoes_e_fingerprint_para_legado_com_domingo_em_aberto(): void
    {
        $emprestimo = $this->criarEmprestimoComParcelasIntercaladas();

        $this->actingAs($this->userConsultor);

        $response = $this->getJson(route('emprestimos.correcao-vencimento-domingo.preview', $emprestimo->id));

        $response->assertOk();
        $response->assertJsonStructure(['alteracoes', 'fingerprint', 'qtd_parcelas_domingo']);
        $this->assertNotEmpty($response->json('alteracoes'));
        $this->assertSame(1, $response->json('qtd_parcelas_domingo'));
    }

    /** @test */
    public function aplicar_remonta_parcelas_abertas_e_define_flag_true(): void
    {
        $emprestimo = $this->criarEmprestimoComParcelasIntercaladas();
        $svc = app(CorrecaoVencimentoDomingoLegadoService::class);
        $preview = $svc->previsualizar($emprestimo);

        $this->actingAs($this->userConsultor);

        $this->post(route('emprestimos.correcao-vencimento-domingo.aplicar', $emprestimo->id), [
            '_token' => csrf_token(),
            'fingerprint' => $preview['fingerprint'],
        ])->assertRedirect(route('emprestimos.show', $emprestimo->id));

        $emprestimo->refresh();
        $this->assertTrue((bool) $emprestimo->getAttributes()['deslocar_vencimento_domingo']);

        $p3 = Parcela::withoutGlobalScope(EmpresaScope::class)->where('emprestimo_id', $emprestimo->id)->where('numero', 3)->first();
        $p4 = Parcela::withoutGlobalScope(EmpresaScope::class)->where('emprestimo_id', $emprestimo->id)->where('numero', 4)->first();

        $this->assertSame('2026-04-06', $p3->data_vencimento->format('Y-m-d'));
        $this->assertSame('2026-04-07', $p4->data_vencimento->format('Y-m-d'));

        $p1 = Parcela::withoutGlobalScope(EmpresaScope::class)->where('emprestimo_id', $emprestimo->id)->where('numero', 1)->first();
        $this->assertSame('2026-04-03', $p1->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function preview_retorna_422_quando_flag_ja_definida(): void
    {
        $emprestimo = $this->criarEmprestimoComParcelasIntercaladas();
        $emprestimo->update(['deslocar_vencimento_domingo' => true]);

        $this->actingAs($this->userConsultor);

        $this->getJson(route('emprestimos.correcao-vencimento-domingo.preview', $emprestimo->id))
            ->assertStatus(422);
    }

    /** @test */
    public function usuario_sem_acesso_a_operacao_recebe_403_no_preview(): void
    {
        $emprestimo = $this->criarEmprestimoComParcelasIntercaladas();

        $outroUser = User::create([
            'name' => 'Outro',
            'email' => 'outro-op@test.com',
            'password' => bcrypt('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($outroUser);

        $this->getJson(route('emprestimos.correcao-vencimento-domingo.preview', $emprestimo->id))
            ->assertStatus(403);
    }

    private function criarEmprestimoComParcelasIntercaladas(): Emprestimo
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224730',
            'nome' => 'Cliente Correcao Domingo',
            'empresa_id' => $this->empresa->id,
        ]);

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 140,
            'numero_parcelas' => 4,
            'frequencia' => 'diaria',
            'data_inicio' => '2026-04-01',
            'taxa_juros' => 0,
            'status' => 'ativo',
            'tipo' => 'dinheiro',
        ]);

        $this->assertNull($emprestimo->fresh()->getAttributes()['deslocar_vencimento_domingo'] ?? null);

        $base = [
            'emprestimo_id' => $emprestimo->id,
            'empresa_id' => $this->empresa->id,
            'valor' => 35,
            'valor_pago' => 0,
            'status' => 'pendente',
        ];

        Parcela::withoutGlobalScope(EmpresaScope::class)->create(array_merge($base, [
            'numero' => 1,
            'data_vencimento' => '2026-04-03',
            'valor_pago' => 35,
            'status' => 'paga',
            'data_pagamento' => '2026-04-04',
        ]));
        Parcela::withoutGlobalScope(EmpresaScope::class)->create(array_merge($base, [
            'numero' => 2,
            'data_vencimento' => '2026-04-04',
            'valor_pago' => 35,
            'status' => 'paga',
            'data_pagamento' => '2026-04-04',
        ]));
        // 05/04/2026 = domingo
        Parcela::withoutGlobalScope(EmpresaScope::class)->create(array_merge($base, [
            'numero' => 3,
            'data_vencimento' => '2026-04-05',
            'valor_pago' => 0,
            'status' => 'atrasada',
            'dias_atraso' => 1,
        ]));
        Parcela::withoutGlobalScope(EmpresaScope::class)->create(array_merge($base, [
            'numero' => 4,
            'data_vencimento' => '2026-04-06',
            'valor_pago' => 0,
            'status' => 'pendente',
            'dias_atraso' => 0,
        ]));

        Carbon::setTestNow(Carbon::parse('2026-04-06'));

        return $emprestimo->fresh(['parcelas']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
