<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Banco, agência, conta e número do cheque podem ser preenchidos depois.
     */
    public function up(): void
    {
        Schema::table('emprestimo_cheques', function (Blueprint $table) {
            $table->string('banco', 100)->nullable()->change();
            $table->string('agencia', 20)->nullable()->change();
            $table->string('conta', 20)->nullable()->change();
            $table->string('numero_cheque', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimo_cheques', function (Blueprint $table) {
            $table->string('banco', 100)->nullable(false)->change();
            $table->string('agencia', 20)->nullable(false)->change();
            $table->string('conta', 20)->nullable(false)->change();
            $table->string('numero_cheque', 50)->nullable(false)->change();
        });
    }
};
