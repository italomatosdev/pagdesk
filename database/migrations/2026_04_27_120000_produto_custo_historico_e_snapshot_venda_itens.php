<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_custo_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->decimal('custo_unitario', 15, 2);
            $table->dateTime('valido_de');
            $table->dateTime('valido_ate')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('observacao', 500)->nullable();
            $table->timestamps();
            $table->index(['produto_id', 'valido_de']);
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('custo_unitario_vigente', 15, 2)->nullable()->after('preco_venda');
            $table->timestamp('custo_vigente_atualizado_em')->nullable()->after('custo_unitario_vigente');
        });

        Schema::table('venda_itens', function (Blueprint $table) {
            $table->decimal('custo_unitario_aplicado', 15, 2)->nullable()->after('subtotal_crediario');
            $table->decimal('custo_total_aplicado', 15, 2)->nullable()->after('custo_unitario_aplicado');
        });
    }

    public function down(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropColumn(['custo_unitario_aplicado', 'custo_total_aplicado']);
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['custo_unitario_vigente', 'custo_vigente_atualizado_em']);
        });

        Schema::dropIfExists('produto_custo_historicos');
    }
};
