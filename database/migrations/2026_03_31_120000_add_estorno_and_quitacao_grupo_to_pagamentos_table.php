<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->timestamp('estornado_em')->nullable();
            $table->foreignId('estornado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('estorno_motivo')->nullable();
            $table->foreignId('estorno_cash_ledger_entry_id')->nullable()->constrained('cash_ledger_entries')->nullOnDelete();
            $table->uuid('quitacao_grupo_id')->nullable();
            $table->index('quitacao_grupo_id');
            $table->index('estornado_em');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropForeign(['estornado_por_user_id']);
            $table->dropForeign(['estorno_cash_ledger_entry_id']);
            $table->dropIndex(['quitacao_grupo_id']);
            $table->dropIndex(['estornado_em']);
            $table->dropColumn([
                'estornado_em',
                'estornado_por_user_id',
                'estorno_motivo',
                'estorno_cash_ledger_entry_id',
                'quitacao_grupo_id',
            ]);
        });
    }
};
