<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona venda_id ao empréstimo (crediário) e inclui 'crediario' no enum tipo.
     */
    public function up(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->unsignedBigInteger('venda_id')->nullable()->after('emprestimo_origem_id');
            $table->index('venda_id');
        });
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->foreign('venda_id')->references('id')->on('vendas')->onDelete('set null');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE emprestimos MODIFY COLUMN tipo ENUM('dinheiro', 'price', 'troca_cheque', 'empenho', 'crediario') NOT NULL DEFAULT 'dinheiro'");
        }
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE emprestimos DROP CONSTRAINT IF EXISTS emprestimos_tipo_check");
            DB::statement("ALTER TABLE emprestimos ADD CONSTRAINT emprestimos_tipo_check CHECK (tipo::text = ANY (ARRAY['dinheiro'::text, 'price'::text, 'troca_cheque'::text, 'empenho'::text, 'crediario'::text]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->dropForeign(['venda_id']);
            $table->dropIndex(['venda_id']);
            $table->dropColumn('venda_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE emprestimos MODIFY COLUMN tipo ENUM('dinheiro', 'price', 'troca_cheque', 'empenho') NOT NULL DEFAULT 'dinheiro'");
        }
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE emprestimos DROP CONSTRAINT IF EXISTS emprestimos_tipo_check");
            DB::statement("ALTER TABLE emprestimos ADD CONSTRAINT emprestimos_tipo_check CHECK (tipo::text = ANY (ARRAY['dinheiro'::text, 'price'::text, 'troca_cheque'::text, 'empenho'::text]))");
        }
    }
};
