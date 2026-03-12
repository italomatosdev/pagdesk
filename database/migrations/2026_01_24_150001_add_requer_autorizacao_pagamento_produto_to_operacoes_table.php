<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Se true, a operação permite pagamento em produto/objeto (requer aceite de gestor/adm).
     */
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->boolean('requer_autorizacao_pagamento_produto')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn('requer_autorizacao_pagamento_produto');
        });
    }
};
