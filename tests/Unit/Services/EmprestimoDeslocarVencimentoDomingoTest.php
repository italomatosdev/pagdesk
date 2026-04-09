<?php

namespace Tests\Unit\Services;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\EmprestimoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class EmprestimoDeslocarVencimentoDomingoTest extends TestCase
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
    public function com_flag_true_primeiro_vencimento_domingo_vai_para_segunda_e_cursor_mantem_diaria(): void
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224726',
            'nome' => 'Cliente Vencimento',
            'empresa_id' => $this->empresa->id,
        ]);

        // Sábado 2026-03-28 + 1 dia (1ª parcela diária) = domingo 2026-03-29
        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 300,
            'numero_parcelas' => 2,
            'frequencia' => 'diaria',
            'data_inicio' => '2026-03-28',
            'taxa_juros' => 0,
            'status' => 'aprovado',
            'tipo' => 'dinheiro',
            'deslocar_vencimento_domingo' => true,
        ]);

        app(EmprestimoService::class)->gerarParcelasSimples($emprestimo);

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $this->assertCount(2, $parcelas);
        $this->assertSame('2026-03-30', $parcelas[0]->data_vencimento->format('Y-m-d'));
        $this->assertSame('2026-03-31', $parcelas[1]->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function com_flag_false_mantem_domingo(): void
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224727',
            'nome' => 'Cliente Vencimento 2',
            'empresa_id' => $this->empresa->id,
        ]);

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 300,
            'numero_parcelas' => 2,
            'frequencia' => 'diaria',
            'data_inicio' => '2026-03-28',
            'taxa_juros' => 0,
            'status' => 'aprovado',
            'tipo' => 'dinheiro',
            'deslocar_vencimento_domingo' => false,
        ]);

        app(EmprestimoService::class)->gerarParcelasSimples($emprestimo);

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $this->assertSame('2026-03-29', $parcelas[0]->data_vencimento->format('Y-m-d'));
        $this->assertSame('2026-03-30', $parcelas[1]->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function legado_null_nao_desloca_domingo(): void
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224728',
            'nome' => 'Cliente Legado',
            'empresa_id' => $this->empresa->id,
        ]);

        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 300,
            'numero_parcelas' => 2,
            'frequencia' => 'diaria',
            'data_inicio' => '2026-03-28',
            'taxa_juros' => 0,
            'status' => 'aprovado',
            'tipo' => 'dinheiro',
            // deslocar_vencimento_domingo omitido => NULL no banco
        ]);

        $this->assertNull($emprestimo->fresh()->getAttributes()['deslocar_vencimento_domingo'] ?? null);

        app(EmprestimoService::class)->gerarParcelasSimples($emprestimo);

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $this->assertSame('2026-03-29', $parcelas[0]->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function price_mensal_desloca_domingo_quando_flag_true(): void
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224729',
            'nome' => 'Cliente Price',
            'empresa_id' => $this->empresa->id,
        ]);

        // 01/02/2026 (domingo) → 1ª parcela mensal = 01/03/2026 (domingo) → com flag grava segunda 02/03/2026
        $emprestimo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 1000,
            'numero_parcelas' => 1,
            'frequencia' => 'mensal',
            'data_inicio' => '2026-02-01',
            'taxa_juros' => 1,
            'status' => 'aprovado',
            'tipo' => 'price',
            'deslocar_vencimento_domingo' => true,
        ]);

        app(EmprestimoService::class)->gerarParcelasPrice($emprestimo);

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $this->assertCount(1, $parcelas);
        $this->assertSame(Carbon::SUNDAY, Carbon::parse('2026-03-01')->dayOfWeek);
        $this->assertSame('2026-03-02', $parcelas[0]->data_vencimento->format('Y-m-d'));
    }

    /** @test */
    public function normalizar_cursor_retorna_segunda_apenas_com_flag(): void
    {
        $domingo = Carbon::parse('2026-03-29');

        $comFlag = new Emprestimo;
        $comFlag->forceFill(['deslocar_vencimento_domingo' => true]);
        $out = EmprestimoService::normalizarDataVencimentoCursor($comFlag, $domingo->copy());
        $this->assertSame('2026-03-30', $out->format('Y-m-d'));

        $semFlag = new Emprestimo;
        $semFlag->forceFill(['deslocar_vencimento_domingo' => false]);
        $out2 = EmprestimoService::normalizarDataVencimentoCursor($semFlag, $domingo->copy());
        $this->assertSame('2026-03-29', $out2->format('Y-m-d'));
    }
}
