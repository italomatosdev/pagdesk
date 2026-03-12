<?php

namespace Tests\Unit\Services;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Services\ClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class ClienteServiceTest extends TestCase
{
    use RefreshDatabase;
    use SetupEmpresaOperacaoUser;

    protected ClienteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->setupEmpresaOperacaoUser();
        $this->service = app(ClienteService::class);
    }

    /** @test */
    public function cadastra_cliente_com_cpf_valido(): void
    {
        $this->actingAs($this->userConsultor);

        $dados = [
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725', // CPF válido
            'nome' => 'Cliente Teste',
            'empresa_id' => $this->empresa->id,
        ];

        $cliente = $this->service->cadastrar($dados);

        $this->assertInstanceOf(Cliente::class, $cliente);
        $this->assertDatabaseHas('clientes', [
            'documento' => '52998224725',
            'nome' => 'Cliente Teste',
            'empresa_id' => $this->empresa->id,
        ]);
    }

    /** @test */
    public function cadastra_cliente_sem_empresa_id_usa_empresa_do_usuario(): void
    {
        $this->actingAs($this->userConsultor);

        $dados = [
            'tipo_pessoa' => 'fisica',
            'documento' => '11144477735', // CPF válido (comum em testes)
            'nome' => 'Cliente Sem Empresa',
        ];

        $cliente = $this->service->cadastrar($dados);

        $this->assertEquals($this->userConsultor->empresa_id, $cliente->empresa_id);
    }

    /** @test */
    public function nao_cadastra_cliente_com_cpf_duplicado(): void
    {
        $this->actingAs($this->userConsultor);

        Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725',
            'nome' => 'Existente',
            'empresa_id' => $this->empresa->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cliente já cadastrado');

        $this->service->cadastrar([
            'tipo_pessoa' => 'fisica',
            'documento' => '52998224725',
            'nome' => 'Duplicado',
            'empresa_id' => $this->empresa->id,
        ]);
    }

    /** @test */
    public function nao_cadastra_cliente_com_cpf_invalido(): void
    {
        $this->actingAs($this->userConsultor);

        $this->expectException(ValidationException::class);

        $this->service->cadastrar([
            'tipo_pessoa' => 'fisica',
            'documento' => '11111111111', // CPF inválido (dígitos iguais)
            'nome' => 'Cliente Inválido',
            'empresa_id' => $this->empresa->id,
        ]);
    }
}
