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
        Schema::table('operacoes', function (Blueprint $table) {
            $table->decimal('taxa_juros_atraso', 5, 2)->default(0)->after('valor_aprovacao_automatica')->comment('Taxa de juros por atraso (ex: 1.5 = 1,5%)');
            $table->enum('tipo_calculo_juros', ['por_dia', 'por_mes'])->default('por_dia')->after('taxa_juros_atraso')->comment('Tipo de cálculo: por dia ou por mês');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn(['taxa_juros_atraso', 'tipo_calculo_juros']);
        });
    }
};
