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
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', [
                'emprestimo_pendente',
                'emprestimo_aprovado',
                'liberacao_disponivel',
                'parcela_vencendo',
                'parcela_atrasada',
                'prestacao_pendente',
                'prestacao_aprovada',
                'prestacao_rejeitada',
                'pagamento_registrado',
            ]);
            $table->string('titulo');
            $table->text('mensagem');
            $table->json('dados')->nullable(); // Dados adicionais (ID do empréstimo, parcela, etc.)
            $table->string('url')->nullable(); // Link para a ação relacionada
            $table->boolean('lida')->default(false);
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lida']);
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
