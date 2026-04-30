<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE emprestimos MODIFY COLUMN frequencia ENUM('diaria', 'semanal', 'mensal', 'quinzenal') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE emprestimos MODIFY COLUMN frequencia ENUM('diaria', 'semanal', 'mensal') NOT NULL");
    }
};
