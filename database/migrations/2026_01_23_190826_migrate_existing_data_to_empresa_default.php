<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Criar empresa padrão para dados existentes
        $empresaId = \DB::table('empresas')->insertGetId([
            'nome' => 'Empresa Principal',
            'razao_social' => 'Empresa Principal',
            'status' => 'ativa',
            'plano' => 'enterprise',
            'data_ativacao' => now(),
            'configuracoes' => json_encode([
                'workflow' => [
                    'requer_aprovacao' => true,
                    'requer_liberacao' => true,
                    'aprovacao_automatica_valor_max' => 0,
                ],
                'operacoes' => [
                    'permite_multiplas_operacoes' => true,
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Atualizar todas as tabelas existentes com empresa_id
        \DB::table('operacoes')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('users')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('clientes')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('emprestimos')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('parcelas')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('cash_ledger_entries')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('settlements')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('aprovacoes')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
        \DB::table('emprestimo_liberacoes')->whereNull('empresa_id')->update(['empresa_id' => $empresaId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover empresa padrão
        \DB::table('empresas')->where('nome', 'Empresa Principal')->delete();
    }
};
