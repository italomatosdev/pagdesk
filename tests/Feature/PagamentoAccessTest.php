<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class PagamentoAccessTest extends TestCase
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
    public function visitante_nao_acessa_tela_de_registrar_pagamento(): void
    {
        $response = $this->get(route('pagamentos.create'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function consultor_acessa_tela_de_registrar_pagamento(): void
    {
        $response = $this->actingAs($this->userConsultor)->get(route('pagamentos.create'));

        $response->assertStatus(200);
        $response->assertViewIs('pagamentos.create');
    }
}
