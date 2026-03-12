<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona campos para sistema de amortização (Price)
     */
    public function up(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->decimal('valor_juros', 15, 2)->nullable()->after('valor');
            $table->decimal('valor_amortizacao', 15, 2)->nullable()->after('valor_juros');
            $table->decimal('saldo_devedor', 15, 2)->nullable()->after('valor_amortizacao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropColumn(['valor_juros', 'valor_amortizacao', 'saldo_devedor']);
        });
    }
};
