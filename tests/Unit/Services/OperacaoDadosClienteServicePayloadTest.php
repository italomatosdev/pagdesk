<?php

namespace Tests\Unit\Services;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Services\OperacaoDadosClienteService;
use Tests\TestCase;

/**
 * Testes sem acesso ao banco (apenas modelo em memória + serviço).
 *
 * Rodar no host: ./vendor/bin/phpunit tests/Unit/Services/OperacaoDadosClienteServicePayloadTest.php
 * (o container app local não inclui PHPUnit — vendor da imagem é --no-dev.)
 */
class OperacaoDadosClienteServicePayloadTest extends TestCase
{
    private OperacaoDadosClienteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OperacaoDadosClienteService::class);
    }

    /** @test */
    public function payload_bruto_mapeia_atributos_do_modelo_sem_persistir(): void
    {
        $cliente = new Cliente;
        $cliente->forceFill([
            'nome' => 'João Silva',
            'telefone' => '11988887777',
            'email' => 'joao@exemplo.com',
            'cidade' => 'Campinas',
            'estado' => 'SP',
            'cep' => '13000000',
            'observacoes' => 'nota',
            'responsavel_nome' => 'Rep',
            'responsavel_cpf' => '123',
            'responsavel_rg' => 'rg',
            'responsavel_cnh' => 'cnh',
            'responsavel_cargo' => 'cargo',
            'endereco' => 'Rua A',
            'numero' => '10',
        ]);

        $payload = $this->service->payloadBrutoFromCliente($cliente, 99);

        $this->assertSame(99, $payload['empresa_id']);
        $this->assertSame('João Silva', $payload['nome']);
        $this->assertSame('11988887777', $payload['telefone']);
        $this->assertSame('joao@exemplo.com', $payload['email']);
        $this->assertSame('Campinas', $payload['cidade']);
        $this->assertSame('SP', $payload['estado']);
        $this->assertSame('13000000', $payload['cep']);
        $this->assertSame('nota', $payload['observacoes']);
        $this->assertSame('Rep', $payload['responsavel_nome']);
        $this->assertSame('123', $payload['responsavel_cpf']);
        $this->assertSame('rg', $payload['responsavel_rg']);
        $this->assertSame('cnh', $payload['responsavel_cnh']);
        $this->assertSame('cargo', $payload['responsavel_cargo']);
        $this->assertSame('Rua A', $payload['endereco']);
        $this->assertSame('10', $payload['numero']);
        $this->assertNull($payload['data_nascimento']);
    }

    /** @test */
    public function payload_bruto_usa_string_vazia_para_nome_ausente(): void
    {
        $cliente = new Cliente;
        $cliente->forceFill(['telefone' => '11900001111']);

        $payload = $this->service->payloadBrutoFromCliente($cliente, null);

        $this->assertSame('', $payload['nome']);
        $this->assertNull($payload['empresa_id']);
        $this->assertSame('11900001111', $payload['telefone']);
    }

    /** @test */
    public function valores_formulario_para_operacao_sem_linha_usa_payload_bruto(): void
    {
        $cliente = new Cliente;
        $cliente->forceFill([
            'nome' => 'Nome Base',
            'telefone' => '11987654321',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
        ]);

        $vals = $this->service->valoresFormularioParaOperacao($cliente, 999_999, 42);

        $this->assertSame('Nome Base', $vals['nome']);
        $this->assertSame('11987654321', $vals['telefone']);
        $this->assertSame('São Paulo', $vals['cidade']);
        $this->assertSame('SP', $vals['estado']);
        $this->assertNull($vals['data_nascimento']);
    }

    /** @test */
    public function payload_bruto_nao_usa_accessors_apenas_get_attributes(): void
    {
        $cliente = new Cliente;
        $cliente->forceFill(['nome' => 'Nome Bruto']);

        $payload = $this->service->payloadBrutoFromCliente($cliente, 1);

        $this->assertSame('Nome Bruto', $payload['nome']);
    }
}
