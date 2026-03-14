<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->boolean('consultores_veem_apenas_proprios_emprestimos')->default(false)->after('permite_emprestimo_retroativo');
        });
    }

    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn('consultores_veem_apenas_proprios_emprestimos');
        });
    }
};
