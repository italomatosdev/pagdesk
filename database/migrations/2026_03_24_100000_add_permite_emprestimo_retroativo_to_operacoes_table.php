<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->boolean('permite_emprestimo_retroativo')->default(false)->after('requer_autorizacao_pagamento_produto');
        });
    }

    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn('permite_emprestimo_retroativo');
        });
    }
};
