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
        Schema::table('emprestimos', function (Blueprint $table) {
            // Adicionar coluna primeiro
            $table->unsignedBigInteger('emprestimo_origem_id')->nullable()->after('motivo_rejeicao');
            $table->index('emprestimo_origem_id');
        });

        // Adicionar foreign key separadamente para evitar problemas com dados existentes
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->foreign('emprestimo_origem_id')
                ->references('id')
                ->on('emprestimos')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->dropForeign(['emprestimo_origem_id']);
            $table->dropIndex(['emprestimo_origem_id']);
            $table->dropColumn('emprestimo_origem_id');
        });
    }
};

