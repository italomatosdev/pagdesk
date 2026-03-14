<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Registra quem criou o empréstimo (útil quando gestor cria em nome do consultor).
     */
    public function up(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->foreignId('criado_por_user_id')
                ->nullable()
                ->after('consultor_id')
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Usuário que criou o registro (gestor ou consultor)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->dropForeign(['criado_por_user_id']);
        });
    }
};
