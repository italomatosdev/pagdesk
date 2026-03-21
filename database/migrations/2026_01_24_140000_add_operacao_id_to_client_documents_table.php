<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documento vinculado ao cliente no contexto de uma operação.
     * null = comportamento legado (documento global / por empresa via empresa_id).
     */
    public function up(): void
    {
        Schema::table('client_documents', function (Blueprint $table) {
            $table->foreignId('operacao_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('operacoes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_documents', function (Blueprint $table) {
            $table->dropForeign(['operacao_id']);
            $table->dropColumn('operacao_id');
        });
    }
};
