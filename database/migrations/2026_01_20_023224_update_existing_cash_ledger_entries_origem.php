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
        // Atualizar registros existentes para ter origem 'automatica'
        \DB::table('cash_ledger_entries')
            ->whereNull('origem')
            ->orWhere('origem', '')
            ->update(['origem' => 'automatica']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não há necessidade de reverter, pois os registros antigos já eram automáticos
    }
};
