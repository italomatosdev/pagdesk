<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona suporte para CPF e CNPJ
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Adicionar novo campo tipo_pessoa
            $table->enum('tipo_pessoa', ['fisica', 'juridica'])->default('fisica')->after('id');
            
            // Adicionar novo campo documento (substitui cpf)
            $table->string('documento', 14)->nullable()->after('tipo_pessoa');
        });

        // Migrar dados existentes de cpf para documento
        DB::statement("UPDATE clientes SET documento = cpf, tipo_pessoa = 'fisica' WHERE cpf IS NOT NULL");

        // Tornar documento obrigatório e único
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('documento', 14)->nullable(false)->change();
            $table->unique('documento');
            $table->index('documento');
        });

        // Remover campo cpf
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropIndex(['cpf']);
            $table->dropColumn('cpf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Recriar campo cpf
            $table->string('cpf', 11)->nullable()->after('id');
        });

        // Migrar dados de documento para cpf (apenas se for CPF - 11 dígitos)
        DB::statement("UPDATE clientes SET cpf = documento WHERE tipo_pessoa = 'fisica' AND LENGTH(documento) = 11");

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable(false)->change();
            $table->unique('cpf');
            $table->index('cpf');
            
            // Remover campos novos
            $table->dropUnique(['documento']);
            $table->dropIndex(['documento']);
            $table->dropColumn('documento');
            $table->dropColumn('tipo_pessoa');
        });
    }
};
