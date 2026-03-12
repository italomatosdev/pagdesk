<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona campo categoria para diferenciar: documento, selfie, anexo
     */
    public function up(): void
    {
        Schema::table('client_documents', function (Blueprint $table) {
            $table->string('categoria')->default('anexo')->after('cliente_id');
            // categoria: 'documento' (obrigatório), 'selfie' (obrigatório), 'anexo' (opcional)
            $table->string('nome_arquivo')->nullable()->after('arquivo_path');
            // Nome original do arquivo para exibição
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_documents', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'nome_arquivo']);
        });
    }
};
