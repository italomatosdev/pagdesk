<?php

namespace Tests\Feature;

use App\Models\Scopes\EmpresaScope;
use App\Models\User;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Core\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class SettlementConfirmarRecebimentoIniciadorTest extends TestCase
{
    use RefreshDatabase;
    use SetupEmpresaOperacaoUser;

    private User $gestorIniciador;

    private User $outroGestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->setupEmpresaOperacaoUser();

        $roleGestor = Role::where('name', 'gestor')->first();
        $this->assertNotNull($roleGestor);

        $this->gestorIniciador = User::create([
            'name' => 'Gestor Iniciador',
            'email' => 'gestor-iniciador@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->gestorIniciador->roles()->attach($roleGestor->id);
        $this->gestorIniciador->operacoes()->attach($this->operacao->id, ['role' => 'gestor']);

        $this->outroGestor = User::create([
            'name' => 'Outro Gestor',
            'email' => 'gestor-outro@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->outroGestor->roles()->attach($roleGestor->id);
        $this->outroGestor->operacoes()->attach($this->operacao->id, ['role' => 'gestor']);
    }

    /** @test */
    public function gestor_que_nao_iniciou_nao_pode_confirmar_recebimento(): void
    {
        $settlement = Settlement::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'consultor_id' => $this->userConsultor->id,
            'criado_por' => $this->gestorIniciador->id,
            'data_inicio' => now()->subDays(7)->format('Y-m-d'),
            'data_fim' => now()->format('Y-m-d'),
            'valor_total' => 100.00,
            'empresa_id' => $this->empresa->id,
            'status' => 'enviado',
            'conferido_por' => $this->gestorIniciador->id,
            'conferido_em' => now(),
            'comprovante_path' => 'comprovantes/teste.pdf',
            'enviado_em' => now(),
        ]);

        $response = $this->actingAs($this->outroGestor)->post(
            route('fechamento-caixa.confirmar', $settlement->id),
            ['_token' => csrf_token()]
        );

        $response->assertForbidden();
    }

    /** @test */
    public function com_criado_por_nulo_outro_gestor_pode_confirmar_recebimento(): void
    {
        $settlement = Settlement::withoutGlobalScope(EmpresaScope::class)->create([
            'operacao_id' => $this->operacao->id,
            'consultor_id' => $this->userConsultor->id,
            'criado_por' => null,
            'data_inicio' => now()->subDays(7)->format('Y-m-d'),
            'data_fim' => now()->format('Y-m-d'),
            'valor_total' => 100.00,
            'empresa_id' => $this->empresa->id,
            'status' => 'enviado',
            'conferido_por' => $this->gestorIniciador->id,
            'conferido_em' => now(),
            'comprovante_path' => 'comprovantes/teste.pdf',
            'enviado_em' => now(),
        ]);

        $response = $this->actingAs($this->outroGestor)->post(
            route('fechamento-caixa.confirmar', $settlement->id),
            ['_token' => csrf_token()]
        );

        $response->assertRedirect(route('fechamento-caixa.show', $settlement->id));
        $response->assertSessionHas('success');
    }
}
