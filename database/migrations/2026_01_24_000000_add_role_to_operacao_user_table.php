<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Papel por operação: consultor, gestor ou administrador em cada operacao_user.
     */
    public function up(): void
    {
        Schema::table('operacao_user', function (Blueprint $table) {
            $table->string('role', 50)->nullable()->after('user_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacao_user', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
