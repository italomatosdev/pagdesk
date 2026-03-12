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
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->enum('tipo_juros', ['nenhum', 'automatico', 'manual', 'fixo'])->nullable()->after('observacoes')->comment('Tipo de juros aplicado');
            $table->decimal('taxa_juros_aplicada', 5, 2)->nullable()->after('tipo_juros')->comment('Taxa de juros aplicada (para automático e manual)');
            $table->decimal('valor_juros', 10, 2)->default(0)->after('taxa_juros_aplicada')->comment('Valor de juros/multa aplicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropColumn(['tipo_juros', 'taxa_juros_aplicada', 'valor_juros']);
        });
    }
};
