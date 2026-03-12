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
        Schema::create('empresa_cliente_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('vinculado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('vinculado_em')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            // Garante que uma empresa só pode ter um vínculo com o mesmo cliente
            $table->unique(['empresa_id', 'cliente_id']);
            
            // Índices para melhor performance
            $table->index('empresa_id');
            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_cliente_vinculos');
    }
};
