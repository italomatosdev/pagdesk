<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Documentos obrigatórios na criação de cliente, por operação.
     */
    public function up(): void
    {
        if (Schema::hasTable('operacao_documentos_obrigatorios')) {
            return;
        }
        Schema::create('operacao_documentos_obrigatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->string('tipo_documento', 80); // documento_cliente, selfie_documento, etc.
            $table->timestamps();

            $table->unique(['operacao_id', 'tipo_documento'], 'op_docs_obrig_unique');
            $table->index('operacao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacao_documentos_obrigatorios');
    }
};
