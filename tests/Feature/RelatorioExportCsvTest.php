<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetupEmpresaOperacaoUser;
use Tests\TestCase;

class RelatorioExportCsvTest extends TestCase
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
            'name' => 'Gestor Rel Export',
            'email' => 'gestor-rel-export@test.com',
            'password' => Hash::make('password'),
            'empresa_id' => $this->empresa->id,
            'operacao_id' => $this->operacao->id,
            'email_verified_at' => now(),
        ]);
        $this->gestor->roles()->attach($roleGestor->id);
        $this->gestor->operacoes()->attach($this->operacao->id, ['role' => 'gestor']);
    }

    /** @test */
    public function consultor_recebe_403_ao_exportar_relatorio_receber_por_cliente(): void
    {
        $response = $this->actingAs($this->userConsultor)->get(
            route('relatorios.receber-por-cliente.export', [
                'date_from' => now()->startOfMonth()->format('Y-m-d'),
                'date_to' => now()->endOfMonth()->format('Y-m-d'),
                'operacao_id' => $this->operacao->id,
            ])
        );

        $response->assertForbidden();
    }

    /** @test */
    public function gestor_baixa_csv_receber_por_cliente_com_bom_e_cabecalho(): void
    {
        $response = $this->actingAs($this->gestor)->get(
            route('relatorios.receber-por-cliente.export', [
                'date_from' => now()->startOfMonth()->format('Y-m-d'),
                'date_to' => now()->endOfMonth()->format('Y-m-d'),
                'operacao_id' => $this->operacao->id,
                'somente_sem_juros' => '0',
            ])
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $disp = $response->headers->get('content-disposition');
        $this->assertIsString($disp);
        $this->assertStringContainsString('attachment', strtolower($disp));
        $this->assertStringContainsString('.csv', $disp);

        ob_start();
        $response->baseResponse->sendContent();
        $raw = ob_get_clean();
        $this->assertNotSame('', $raw);
        $this->assertSame("\xEF\xBB\xBF", substr($raw, 0, 3));
        $this->assertStringContainsString('Cliente', $raw);
    }
}
