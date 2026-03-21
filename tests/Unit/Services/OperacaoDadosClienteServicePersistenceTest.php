<?php

namespace Tests\Unit\Services;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Core\Services\OperacaoDadosClienteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

/**
 * Exige MySQL com schema já migrado (phpunit.xml: DB_DATABASE=cred).
 *
 * DatabaseTransactions = rollback por teste; não roda migrate:fresh (não zera o cred).
 *
 * Rodar no host: ./vendor/bin/phpunit tests/Unit/Services/OperacaoDadosClienteServicePersistenceTest.php
 * (phpunit.xml define DB_HOST=127.0.0.1 para não usar host.docker.internal do .env, que só funciona dentro do Docker.)
 */
#[Group('database')]
class OperacaoDadosClienteServicePersistenceTest extends TestCase
{
    use DatabaseTransactions;
    use SetupEmpresaOperacaoUser;

    private OperacaoDadosClienteService $service;

    private static int $documentoSequencial = 10000000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->setupEmpresaOperacaoUser();
        $this->service = app(OperacaoDadosClienteService::class);
    }

    /** @test */
    public function obter_para_operacao_retorna_null_quando_nao_existe_linha(): void
    {
        $cliente = $this->criarCliente();

        $this->assertNull(
            $this->service->obterParaOperacao($cliente->id, $this->operacao->id)
        );
    }

    /** @test */
    public function garantir_registro_cria_linha_a_partir_do_cliente(): void
    {
        $cliente = $this->criarCliente([
            'nome' => 'Nome Base',
            'telefone' => '11999998888',
            'cidade' => 'São Paulo',
        ]);

        $row = $this->service->garantirRegistro($cliente->id, $this->operacao->id);

        $this->assertInstanceOf(OperacaoDadosCliente::class, $row);
        $this->assertSame('Nome Base', $row->nome);
        $this->assertSame('11999998888', $row->telefone);
        $this->assertSame('São Paulo', $row->cidade);
        $this->assertSame($this->empresa->id, (int) $row->empresa_id);
        $this->assertDatabaseHas('operacao_dados_clientes', [
            'cliente_id' => $cliente->id,
            'operacao_id' => $this->operacao->id,
        ]);
    }

    /** @test */
    public function garantir_registro_retorna_existente_sem_duplicar(): void
    {
        $cliente = $this->criarCliente();

        $a = $this->service->garantirRegistro($cliente->id, $this->operacao->id);
        $b = $this->service->garantirRegistro($cliente->id, $this->operacao->id);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, OperacaoDadosCliente::query()
            ->where('cliente_id', $cliente->id)
            ->where('operacao_id', $this->operacao->id)
            ->count());
    }

    /** @test */
    public function salvar_ou_atualizar_cria_com_dados_mesclados(): void
    {
        $cliente = $this->criarCliente(['nome' => 'Original']);

        $row = $this->service->salvarOuAtualizar($cliente->id, $this->operacao->id, [
            'nome' => 'Na Operação',
            'email' => 'op@test.com',
        ]);

        $this->assertSame('Na Operação', $row->nome);
        $this->assertSame('op@test.com', $row->email);
    }

    /** @test */
    public function salvar_ou_atualizar_atualiza_registro_existente(): void
    {
        $cliente = $this->criarCliente(['nome' => 'Um']);

        $first = $this->service->salvarOuAtualizar($cliente->id, $this->operacao->id, [
            'telefone' => '111',
        ]);

        $second = $this->service->salvarOuAtualizar($cliente->id, $this->operacao->id, [
            'telefone' => '222',
            'cidade' => 'Campinas',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('222', $second->telefone);
        $this->assertSame('Campinas', $second->cidade);
    }

    /** @test */
    public function salvar_ou_atualizar_ignora_chaves_nao_editaveis(): void
    {
        $cliente = $this->criarCliente();

        $row = $this->service->salvarOuAtualizar($cliente->id, $this->operacao->id, [
            'nome' => 'OK',
            'cliente_id' => 99999,
            'operacao_id' => 88888,
            'documento' => 'hack',
        ]);

        $this->assertSame($cliente->id, (int) $row->cliente_id);
        $this->assertSame($this->operacao->id, (int) $row->operacao_id);
        $this->assertSame('OK', $row->nome);
    }

    /** @test */
    public function salvar_ou_atualizar_lanca_se_cliente_inexistente(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->salvarOuAtualizar(999999, $this->operacao->id, ['nome' => 'X']);
    }

    /** @test */
    public function valores_formulario_para_operacao_usa_linha_quando_existe(): void
    {
        $cliente = $this->criarCliente(['nome' => 'Cadastro Mestre']);
        $this->service->salvarOuAtualizar($cliente->id, $this->operacao->id, [
            'nome' => 'Nome Na Ficha',
            'email' => 'ficha@operacao.test',
        ]);

        $vals = $this->service->valoresFormularioParaOperacao($cliente, $this->operacao->id, $this->empresa->id);

        $this->assertSame('Nome Na Ficha', $vals['nome']);
        $this->assertSame('ficha@operacao.test', $vals['email']);
    }

    /** @test */
    public function valores_formulario_para_operacao_formata_cpf_responsavel_na_ficha(): void
    {
        $cliente = $this->criarCliente(['nome' => 'Cliente Ficha CPF']);
        OperacaoDadosCliente::query()->create([
            'cliente_id' => $cliente->id,
            'operacao_id' => $this->operacao->id,
            'empresa_id' => $this->empresa->id,
            'nome' => $cliente->nome,
            'responsavel_cpf' => '52998224725',
        ]);

        $vals = $this->service->valoresFormularioParaOperacao($cliente, $this->operacao->id, $this->empresa->id);

        $this->assertSame('529.982.247-25', $vals['responsavel_cpf']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function criarCliente(array $extra = []): Cliente
    {
        self::$documentoSequencial++;

        return Cliente::withoutGlobalScope(EmpresaScope::class)->create(array_merge([
            'tipo_pessoa' => 'fisica',
            'documento' => (string) self::$documentoSequencial,
            'nome' => 'Cliente Teste',
            'empresa_id' => $this->empresa->id,
        ], $extra));
    }
}
