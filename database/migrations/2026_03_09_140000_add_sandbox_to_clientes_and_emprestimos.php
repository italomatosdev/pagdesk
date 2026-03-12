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
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('sandbox')->default(false)->after('empresa_id');
        });
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->boolean('sandbox')->default(false)->after('empresa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('sandbox');
        });
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->dropColumn('sandbox');
        });
    }
};
