<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona método de pagamento "produto_objeto" (não gera caixa, requer aceite gestor/adm).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE pagamentos MODIFY metodo ENUM('dinheiro', 'pix', 'transferencia', 'outro', 'produto_objeto') DEFAULT 'dinheiro'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não remove valor do enum para não quebrar dados existentes; em produção poderia migrar primeiro
        DB::statement("ALTER TABLE pagamentos MODIFY metodo ENUM('dinheiro', 'pix', 'transferencia', 'outro') DEFAULT 'dinheiro'");
    }
};
