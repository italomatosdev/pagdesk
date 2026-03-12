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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('operacao_id')->nullable()->after('email')->constrained('operacoes')->onDelete('set null');
            $table->index('operacao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['operacao_id']);
            $table->dropIndex(['operacao_id']);
            $table->dropColumn('operacao_id');
        });
    }
};
