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
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('tipo')->constrained('categoria_movimentacao')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
        });
    }
};
