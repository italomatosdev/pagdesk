<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Campos do produto/objeto (quando metodo = produto_objeto) e estado de rejeição.
     */
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->string('produto_nome', 255)->nullable()->after('aceite_gestor_em');
            $table->text('produto_descricao')->nullable()->after('produto_nome');
            $table->decimal('produto_valor', 15, 2)->nullable()->after('produto_descricao');
            $table->json('produto_imagens')->nullable()->after('produto_valor')->comment('Array de paths das imagens');
            $table->foreignId('rejeitado_por_id')->nullable()->after('produto_imagens')->constrained('users')->onDelete('set null');
            $table->timestamp('rejeitado_em')->nullable()->after('rejeitado_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropForeign(['rejeitado_por_id']);
            $table->dropColumn([
                'produto_nome',
                'produto_descricao',
                'produto_valor',
                'produto_imagens',
                'rejeitado_em',
            ]);
        });
    }
};
