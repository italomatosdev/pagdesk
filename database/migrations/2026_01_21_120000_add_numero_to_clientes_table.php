<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona o campo "numero" ao endereço do cliente.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero', 20)
                ->nullable()
                ->after('endereco');
        });
    }

    /**
     * Remove o campo "numero" se necessário.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('numero');
        });
    }
};

