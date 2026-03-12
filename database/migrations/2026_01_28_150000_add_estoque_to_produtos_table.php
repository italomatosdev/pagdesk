<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Estoque: quantidade disponível para venda (decimal para compatibilidade com venda_itens.quantidade).
     */
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('estoque', 12, 3)->default(0)->after('unidade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('estoque');
        });
    }
};
