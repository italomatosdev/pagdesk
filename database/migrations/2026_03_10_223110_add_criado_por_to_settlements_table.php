<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona campo para identificar quem criou o fechamento de caixa:
     * - Se criado_por == consultor_id: consultor solicitou fechamento (fluxo antigo)
     * - Se criado_por != consultor_id: gestor/admin fechou o caixa do usuário
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->foreignId('criado_por')->nullable()->after('consultor_id')->constrained('users')->onDelete('set null');
        });

        // Preencher criado_por com consultor_id para registros existentes (solicitação pelo próprio consultor)
        DB::table('settlements')
            ->whereNull('criado_por')
            ->update(['criado_por' => DB::raw('consultor_id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropForeign(['criado_por']);
            $table->dropColumn('criado_por');
        });
    }
};
