<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->unsignedBigInteger('operacao_id')->nullable()->after('user_id');
            $table->foreign('operacao_id')->references('id')->on('operacoes')->onDelete('set null');
            $table->index('operacao_id');
        });
    }

    public function down(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->dropForeign(['operacao_id']);
            $table->dropIndex(['operacao_id']);
        });
    }
};
