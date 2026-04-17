<?php

namespace Tests\Feature;

use App\Models\Scopes\EmpresaScope;
use App\Models\User;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Produto;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Venda;
use App\Modules\Core\Models\VendaItem;
use App\Modules\Core\Services\ProdutoCustoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class ProdutoCustoVendaTest extends TestCase
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
            'name' => 'Gestor Custo Venda',
            'email' => 'gestor-custo-venda@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->gestor->roles()->attach($roleGestor->id);
        $this->gestor->operacoes()->attach($this->operacao->id, ['role' => 'gestor']);
    }

    private function criarCliente(): Cliente
    {
        return Cliente::withoutGlobalScope(EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725',
            'nome' => 'Cliente Venda Teste',
            'empresa_id' => $this->empresa->id,
        ]);
    }

    private function criarProdutoComEstoque(bool $comCusto): Produto
    {
        $p = Produto::create([
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'nome' => 'Produto Teste '.uniqid(),
            'codigo' => 'T-'.uniqid(),
            'preco_venda' => 100,
            'unidade' => 'un',
            'estoque' => 10,
            'ativo' => true,
        ]);
        if ($comCusto) {
            app(ProdutoCustoService::class)->definirCustoVigente($p, 40.0, null, $this->gestor);
            $p->refresh();
        }

        return $p;
    }

    /** @test */
    public function venda_com_produto_sem_custo_e_barrada(): void
    {
        $cliente = $this->criarCliente();
        $produto = $this->criarProdutoComEstoque(false);
        $this->assertFalse($produto->temCustoVigenteDefinido());

        $response = $this->actingAs($this->gestor)->from(route('vendas.create'))->post(route('vendas.store'), [
            'cliente_id' => $cliente->id,
            'operacao_id' => $this->operacao->id,
            'data_venda' => now()->format('Y-m-d'),
            'valor_desconto' => '0',
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'descricao' => '',
                    'quantidade' => '1',
                    'preco_unitario_vista' => '10.00',
                    'preco_unitario_crediario' => '10.00',
                ],
            ],
            'formas' => [
                ['forma' => 'pix', 'valor' => '10.00', 'descricao' => ''],
            ],
        ]);

        $response->assertRedirect(route('vendas.create'));
        $response->assertSessionHasErrors('itens');
        $this->assertSame(0, Venda::count());
    }

    /** @test */
    public function venda_com_produto_com_custo_grava_snapshot_no_item(): void
    {
        $cliente = $this->criarCliente();
        $produto = $this->criarProdutoComEstoque(true);

        $response = $this->actingAs($this->gestor)->post(route('vendas.store'), [
            'cliente_id' => $cliente->id,
            'operacao_id' => $this->operacao->id,
            'data_venda' => now()->format('Y-m-d'),
            'valor_desconto' => '0',
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'descricao' => '',
                    'quantidade' => '2',
                    'preco_unitario_vista' => '10.00',
                    'preco_unitario_crediario' => '10.00',
                ],
            ],
            'formas' => [
                ['forma' => 'pix', 'valor' => '20.00', 'descricao' => ''],
            ],
        ]);

        $response->assertRedirect();
        $item = VendaItem::first();
        $this->assertNotNull($item);
        $this->assertSame('40.00', $item->custo_unitario_aplicado);
        $this->assertSame('80.00', $item->custo_total_aplicado);
    }

    /** @test */
    public function consultor_nao_ve_coluna_de_custo_na_listagem_de_produtos(): void
    {
        $this->operacao->update(['consultor_pode_vender' => true]);
        $this->userConsultor->operacoes()->sync([
            $this->operacao->id => ['role' => 'consultor'],
        ]);
        $this->criarProdutoComEstoque(true);

        $response = $this->actingAs($this->userConsultor)->get(route('produtos.index'));

        $response->assertOk();
        $response->assertDontSee('Preço custo', false);
    }

    /** @test */
    public function gestor_ve_coluna_de_custo_na_listagem_de_produtos(): void
    {
        $this->criarProdutoComEstoque(true);

        $response = $this->actingAs($this->gestor)->get(route('produtos.index'));

        $response->assertOk();
        $response->assertSee('Preço custo', false);
    }

    /** @test */
    public function produto_custo_service_fecha_vigencia_ao_alterar(): void
    {
        $produto = $this->criarProdutoComEstoque(false);
        $svc = app(ProdutoCustoService::class);
        $svc->definirCustoVigente($produto, 10.0, 'primeiro', $this->gestor);
        $svc->definirCustoVigente($produto->fresh(), 12.0, 'segundo', $this->gestor);

        $this->assertDatabaseCount('produto_custo_historicos', 2);
        $fechados = $produto->fresh()->custoHistoricos()->whereNotNull('valido_ate')->count();
        $this->assertSame(1, $fechados);
    }

    /** @test */
    public function gestor_pode_corrigir_custo_aplicado_em_item_de_venda(): void
    {
        $cliente = $this->criarCliente();
        $produto = $this->criarProdutoComEstoque(true);
        $venda = Venda::create([
            'cliente_id' => $cliente->id,
            'operacao_id' => $this->operacao->id,
            'user_id' => $this->gestor->id,
            'empresa_id' => $this->empresa->id,
            'data_venda' => now()->toDateString(),
            'status' => 'finalizada',
            'valor_total_bruto' => 10,
            'valor_desconto' => 0,
            'valor_total_final' => 10,
        ]);
        $item = VendaItem::create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'descricao' => null,
            'quantidade' => 1,
            'preco_unitario_vista' => 10,
            'preco_unitario_crediario' => 10,
            'subtotal_vista' => 10,
            'subtotal_crediario' => 10,
            'custo_unitario_aplicado' => null,
            'custo_total_aplicado' => null,
        ]);

        $response = $this->actingAs($this->gestor)->patch(
            route('vendas.itens.custo', ['venda' => $venda->id, 'vendaItem' => $item->id]),
            ['custo_unitario_aplicado' => '15.50']
        );

        $response->assertRedirect();
        $item->refresh();
        $this->assertSame('15.50', $item->custo_unitario_aplicado);
        $this->assertSame('15.50', $item->custo_total_aplicado);
    }
}
