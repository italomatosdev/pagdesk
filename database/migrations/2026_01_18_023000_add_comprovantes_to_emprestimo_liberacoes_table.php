<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona campos para armazenar comprovantes de liberação e pagamento ao cliente
     */
    public function up(): void
    {
        Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
            $table->string('comprovante_liberacao')->nullable()->after('observacoes_liberacao');
            $table->string('comprovante_pagamento_cliente')->nullable()->after('observacoes_pagamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
            $table->dropColumn(['comprovante_liberacao', 'comprovante_pagamento_cliente']);
        });
    }
};
