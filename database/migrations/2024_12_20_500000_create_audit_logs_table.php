<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Log de auditoria para ações críticas
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Quem fez a ação
            $table->string('action'); // Ex: criar_emprestimo, aprovar_emprestimo, alterar_limite
            $table->string('model_type')->nullable(); // Tipo do modelo afetado (ex: App\Modules\Loans\Models\Emprestimo)
            $table->unsignedBigInteger('model_id')->nullable(); // ID do modelo afetado
            $table->json('old_values')->nullable(); // Valores anteriores
            $table->json('new_values')->nullable(); // Valores novos
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            // Índices para busca
            $table->index('user_id');
            $table->index('action');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

