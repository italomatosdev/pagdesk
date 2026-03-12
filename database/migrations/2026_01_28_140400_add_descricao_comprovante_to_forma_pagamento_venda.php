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
        Schema::table('forma_pagamento_venda', function (Blueprint $table) {
            $table->string('descricao', 255)->nullable()->after('valor');
            $table->string('comprovante_path', 500)->nullable()->after('descricao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forma_pagamento_venda', function (Blueprint $table) {
            $table->dropColumn(['descricao', 'comprovante_path']);
        });
    }
};
