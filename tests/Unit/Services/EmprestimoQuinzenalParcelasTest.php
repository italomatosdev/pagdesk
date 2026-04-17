<?php

namespace Tests\Unit\Services;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\CorrecaoVencimentoDomingoLegadoService;
use App\Modules\Loans\Services\EmprestimoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class EmprestimoQuinzenalParcelasTest extends TestCase
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
    public function simples_tres_parcelas_sem_deslocar_domingo_saltos_de_15_dias(): void
    {
        $cliente = $this->criarCliente('52998224801');

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 300,
            'numero_parcelas' => 3,
            'frequencia' => 'quinzenal',
            'data_inicio' => '2026-04-06',
            'taxa_juros' => 0,
            'status' => 'aprovado',
            'tipo' => 'dinheiro',
            'deslocar_vencimento_domingo' => false,
        ]);

        app(EmprestimoService::class)->gerarParcelasSimples($emprestimo);

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $this->assertCount(3, $parcelas);
        $this->assertSame('2026-04-21', $parcelas[0]->data_vencimento->format('Y-m-d'));
        $this->assertSame('2026-05-06', $parcelas[1]->data_vencimento->format('Y-m-d'));
        $this->assertSame('2026-05-21', $parcelas[2]->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function simples_primeiro_raw_cai_em_domingo_com_flag_desloca_para_segunda(): void
    {
        $cliente = $this->criarCliente('52998224802');

        // Sáb 2026-04-11 + 15 = dom 2026-04-26 → com flag vira seg 2026-04-27
        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 200,
            'numero_parcelas' => 2,
            'frequencia' => 'quinzenal',
            'data_inicio' => '2026-04-11',
            'taxa_juros' => 0,
            'status' => 'aprovado',
            'tipo' => 'dinheiro',
            'deslocar_vencimento_domingo' => true,
        ]);

        app(EmprestimoService::class)->gerarParcelasSimples($emprestimo);

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $this->assertSame('2026-04-27', $parcelas[0]->data_vencimento->format('Y-m-d'));
        $this->assertSame('2026-05-12', $parcelas[1]->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function price_e_simples_mesmas_datas_de_vencimento(): void
    {
        $cliente = $this->criarCliente('52998224803');

        $attrs = [
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 450,
            'numero_parcelas' => 3,
            'frequencia' => 'quinzenal',
            'data_inicio' => '2026-04-06',
            'taxa_juros' => 2,
            'status' => 'aprovado',
            'deslocar_vencimento_domingo' => false,
        ];

        $eSimples = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create(array_merge($attrs, [
            'tipo' => 'dinheiro',
        ]));
        app(EmprestimoService::class)->gerarParcelasSimples($eSimples);
        $datasSimples = $eSimples->parcelas()->orderBy('numero')->pluck('data_vencimento')->map(fn ($d) => $d->format('Y-m-d'))->all();

        $ePrice = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create(array_merge($attrs, [
            'tipo' => 'price',
        ]));
        app(EmprestimoService::class)->gerarParcelasPrice($ePrice);
        $datasPrice = $ePrice->parcelas()->orderBy('numero')->pluck('data_vencimento')->map(fn ($d) => $d->format('Y-m-d'))->all();

        $this->assertSame($datasSimples, $datasPrice);
    }

    /** @test */
    public function correcao_legado_avancar_cursor_quinzenal_soma_15_dias(): void
    {
        $svc = app(CorrecaoVencimentoDomingoLegadoService::class);
        $m = (new ReflectionClass($svc))->getMethod('avancarCursor');
        $m->setAccessible(true);

        $out = $m->invoke($svc, Carbon::parse('2026-04-06')->startOfDay(), 'quinzenal');
        $this->assertSame('2026-04-21', $out->format('Y-m-d'));
    }

    /** @test */
    public function correcao_legado_cursor_inicial_sem_congeladas_quinzenal_mais_15_e_sem_domingo(): void
    {
        $svc = app(CorrecaoVencimentoDomingoLegadoService::class);
        $m = (new ReflectionClass($svc))->getMethod('cursorInicial');
        $m->setAccessible(true);

        $emprestimo = new Emprestimo;
        $emprestimo->forceFill([
            'data_inicio' => '2026-04-06',
            'frequencia' => 'quinzenal',
        ]);

        $cursor = $m->invoke($svc, $emprestimo, collect([]));
        $this->assertSame('2026-04-21', $cursor->format('Y-m-d'));
    }

    private function criarCliente(string $documento): Cliente
    {
        return Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => $documento,
            'nome' => 'Cliente Quinzenal',
            'empresa_id' => $this->empresa->id,
        ]);
    }
}
