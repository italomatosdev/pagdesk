<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona campos para responsável legal de pessoa jurídica
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('responsavel_nome', 255)->nullable()->after('data_nascimento');
            $table->string('responsavel_cpf', 11)->nullable()->after('responsavel_nome');
            $table->string('responsavel_rg', 20)->nullable()->after('responsavel_cpf');
            $table->string('responsavel_cnh', 20)->nullable()->after('responsavel_rg');
            $table->string('responsavel_cargo', 100)->nullable()->after('responsavel_cnh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'responsavel_nome',
                'responsavel_cpf',
                'responsavel_rg',
                'responsavel_cnh',
                'responsavel_cargo',
            ]);
        });
    }
};
