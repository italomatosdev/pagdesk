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
        // Adicionar status 'cancelado' ao enum
        DB::statement("ALTER TABLE emprestimo_liberacoes MODIFY COLUMN status ENUM('aguardando', 'liberado', 'pago_ao_cliente', 'cancelado') DEFAULT 'aguardando'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover status 'cancelado' do enum (voltar ao estado anterior)
        DB::statement("ALTER TABLE emprestimo_liberacoes MODIFY COLUMN status ENUM('aguardando', 'liberado', 'pago_ao_cliente') DEFAULT 'aguardando'");
    }
};
