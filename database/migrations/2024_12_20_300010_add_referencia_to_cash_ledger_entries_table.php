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
            $table->string('referencia_tipo')->nullable()->after('pagamento_id')
                  ->comment('Tipo de referência: liberacao_emprestimo, pagamento_cliente, etc');
            $table->unsignedBigInteger('referencia_id')->nullable()->after('referencia_tipo')
                  ->comment('ID da referência (liberacao_id, emprestimo_id, etc)');
            
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['referencia_tipo', 'referencia_id']);
            $table->dropColumn(['referencia_tipo', 'referencia_id']);
        });
    }
};
