<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: alterar enum para incluir novo valor
        DB::statement("ALTER TABLE emprestimos MODIFY COLUMN status ENUM('draft', 'pendente', 'aprovado', 'ativo', 'finalizado', 'cancelado', 'aguardando_aceite_retroativo') DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Reverter: remover valor do enum (empréstimos com esse status precisariam ser migrados antes)
        DB::statement("ALTER TABLE emprestimos MODIFY COLUMN status ENUM('draft', 'pendente', 'aprovado', 'ativo', 'finalizado', 'cancelado') DEFAULT 'draft'");
    }
};
