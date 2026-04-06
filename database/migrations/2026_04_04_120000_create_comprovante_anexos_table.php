<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprovante_anexos', function (Blueprint $table) {
            $table->id();
            $table->morphs('anexavel');
            $table->string('context', 40)->nullable()->comment('liberacao|pagamento_cliente para liberação; null nos demais');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprovante_anexos');
    }
};
