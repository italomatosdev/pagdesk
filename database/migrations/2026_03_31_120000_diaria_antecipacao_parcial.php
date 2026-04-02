<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE parcelas MODIFY COLUMN status ENUM(
            'pendente',
            'paga',
            'atrasada',
            'cancelada',
            'quitada_garantia',
            'paga_parcial'
        )");

        Schema::create('solicitacao_pagamento_diaria_parcial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parcela_id');
            $table->foreign('parcela_id', 'sdpd_parcela_fk')->references('id')->on('parcelas')->onDelete('cascade');
            $table->unsignedBigInteger('emprestimo_id');
            $table->foreign('emprestimo_id', 'sdpd_emp_fk')->references('id')->on('emprestimos')->onDelete('cascade');
            $table->unsignedBigInteger('consultor_id');
            $table->foreign('consultor_id', 'sdpd_consultor_fk')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('valor_recebido', 15, 2);
            $table->decimal('valor_esperado', 15, 2);
            $table->decimal('faltante', 15, 2);
            $table->string('metodo', 50);
            $table->date('data_pagamento');
            $table->string('comprovante_path')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('pagamento_id')->nullable();
            $table->foreign('pagamento_id', 'sdpd_pag_fk')->references('id')->on('pagamentos')->onDelete('set null');
            $table->enum('status', ['aguardando', 'aprovado', 'rejeitado'])->default('aguardando');
            $table->unsignedBigInteger('aprovado_por_id')->nullable();
            $table->foreign('aprovado_por_id', 'sdpd_aprov_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->unsignedBigInteger('rejeitado_por_id')->nullable();
            $table->foreign('rejeitado_por_id', 'sdpd_rej_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('rejeitado_em')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id', 'sdpd_empresa_fk')->references('id')->on('empresas')->onDelete('cascade');
            $table->timestamps();
            $table->index(['status', 'empresa_id'], 'sdpd_status_empresa_idx');
            $table->index(['parcela_id', 'status'], 'sdpd_parcela_status_idx');
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->string('lote_id', 36)->nullable()->after('parcela_id');
            $table->boolean('aguardando_aprovacao_diaria_parcial')->default(false)->after('lote_id');
            $table->index('lote_id', 'pagamentos_lote_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropIndex('pagamentos_lote_id_idx');
            $table->dropColumn(['lote_id', 'aguardando_aprovacao_diaria_parcial']);
        });

        Schema::dropIfExists('solicitacao_pagamento_diaria_parcial');

        DB::statement("UPDATE parcelas SET status = 'paga' WHERE status = 'paga_parcial'");

        DB::statement("ALTER TABLE parcelas MODIFY COLUMN status ENUM(
            'pendente',
            'paga',
            'atrasada',
            'cancelada',
            'quitada_garantia'
        )");
    }
};
