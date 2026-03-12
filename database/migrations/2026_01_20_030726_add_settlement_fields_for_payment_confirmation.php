<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona campos para o novo fluxo de prestação de contas:
     * - comprovante_path: Comprovante anexado pelo consultor
     * - enviado_em: Quando o consultor anexou o comprovante
     * - recebido_em: Quando o gestor confirmou recebimento
     * - recebido_por: ID do gestor que confirmou recebimento
     * 
     * Ajusta o enum de status para incluir 'enviado' e 'concluido'
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Adicionar novos campos
            $table->string('comprovante_path')->nullable()->after('motivo_rejeicao');
            $table->timestamp('enviado_em')->nullable()->after('comprovante_path');
            $table->foreignId('recebido_por')->nullable()->after('enviado_em')->constrained('users')->onDelete('set null');
            $table->timestamp('recebido_em')->nullable()->after('recebido_por');
        });

        // Ajustar enum de status para incluir 'enviado' e 'concluido'
        // MySQL não suporta MODIFY ENUM diretamente, então precisamos usar DB::statement
        DB::statement("ALTER TABLE settlements MODIFY COLUMN status ENUM('pendente', 'aprovado', 'enviado', 'concluido', 'rejeitado', 'conferido', 'validado') DEFAULT 'pendente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropForeign(['recebido_por']);
            $table->dropColumn(['comprovante_path', 'enviado_em', 'recebido_por', 'recebido_em']);
        });

        // Reverter enum para o estado original
        DB::statement("ALTER TABLE settlements MODIFY COLUMN status ENUM('pendente', 'conferido', 'validado', 'rejeitado') DEFAULT 'pendente'");
    }
};
