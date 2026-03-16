<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem confirmou o pagamento ao cliente (consultor ou gestor/admin).
     * Nullable para compatibilidade com registros antigos.
     */
    public function up(): void
    {
        Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
            $table->foreignId('confirmado_pagamento_por_id')
                ->nullable()
                ->after('pago_ao_cliente_em')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
            $table->dropForeign(['confirmado_pagamento_por_id']);
        });
    }
};
