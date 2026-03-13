<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_emprestimo_retroativo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->foreignId('solicitante_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['aguardando', 'aprovado', 'rejeitado'])->default('aguardando');
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_emprestimo_retroativo');
    }
};
