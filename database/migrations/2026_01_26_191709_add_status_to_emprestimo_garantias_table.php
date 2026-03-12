<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('emprestimo_garantias', function (Blueprint $table) {
            $table->enum('status', ['ativa', 'liberada', 'executada'])->default('ativa')->after('observacoes');
            $table->dateTime('data_liberacao')->nullable()->after('status');
            $table->dateTime('data_execucao')->nullable()->after('data_liberacao');
        });
        
        // Atualizar garantias existentes para status 'ativa' se não tiverem status
        DB::table('emprestimo_garantias')
            ->whereNull('status')
            ->update(['status' => 'ativa']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimo_garantias', function (Blueprint $table) {
            $table->dropColumn(['status', 'data_liberacao', 'data_execucao']);
        });
    }
};
