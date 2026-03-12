<?php

namespace Tests\Unit\Services;

use App\Modules\Loans\Services\ParcelaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class ParcelaServiceTest extends TestCase
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
    public function cobrancas_do_dia_retorna_colecao(): void
    {
        $service = app(ParcelaService::class);

        $resultado = $service->cobrancasDoDia(null, null);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $resultado);
    }
}
