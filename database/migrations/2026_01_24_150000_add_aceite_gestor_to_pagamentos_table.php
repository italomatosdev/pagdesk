<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aceite de gestor/adm para pagamentos em produto/objeto (não gera caixa).
     */
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->foreignId('aceite_gestor_id')->nullable()->after('observacoes')->constrained('users')->onDelete('set null');
            $table->timestamp('aceite_gestor_em')->nullable()->after('aceite_gestor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropForeign(['aceite_gestor_id']);
            $table->dropColumn(['aceite_gestor_em']);
        });
    }
};
