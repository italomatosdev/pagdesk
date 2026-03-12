<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->decimal('juros_incorporados', 15, 2)->default(0)->after('valor_total')
                ->comment('Juros do empréstimo anterior incorporados ao principal (em negociações)');
        });
    }

    public function down(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->dropColumn('juros_incorporados');
        });
    }
};
