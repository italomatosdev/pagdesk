<?php

namespace Tests\Unit\Models;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class EmprestimoWhereTemParcelaVencimentoDomingoTest extends TestCase
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
    public function scope_retorna_somente_emprestimos_com_parcela_vencendo_em_domingo(): void
    {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224740',
            'nome' => 'Cliente Scope Domingo',
            'empresa_id' => $this->empresa->id,
        ]);

        $baseEmp = [
            'operacao_id' => $this->operacao->id,
            'cliente_id' => $cliente->id,
            'consultor_id' => $this->userConsultor->id,
            'empresa_id' => $this->empresa->id,
            'valor_total' => 100,
            'numero_parcelas' => 1,
            'frequencia' => 'diaria',
            'data_inicio' => '2026-04-01',
            'taxa_juros' => 0,
            'status' => 'ativo',
            'tipo' => 'dinheiro',
        ];

        $semDomingo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create($baseEmp);
        Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $semDomingo->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 100,
            'valor_pago' => 0,
            'status' => 'pendente',
            'data_vencimento' => '2026-04-06',
        ]);

        $comDomingo = Emprestimo::withoutGlobalScope(EmpresaScope::class)->create(array_merge($baseEmp, [
            'valor_total' => 200,
        ]));
        Parcela::withoutGlobalScope(EmpresaScope::class)->create([
            'emprestimo_id' => $comDomingo->id,
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'valor' => 200,
            'valor_pago' => 0,
            'status' => 'pendente',
            'data_vencimento' => '2026-04-05',
        ]);

        $ids = Emprestimo::withoutGlobalScope(EmpresaScope::class)
            ->whereTemParcelaVencimentoDomingo()
            ->pluck('id')
            ->all();

        $this->assertContains($comDomingo->id, $ids);
        $this->assertNotContains($semDomingo->id, $ids);
    }
}
