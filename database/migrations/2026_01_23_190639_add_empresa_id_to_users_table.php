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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            $table->boolean('is_super_admin')->default(false)->after('empresa_id');
            $table->index('empresa_id');
            $table->index('is_super_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn(['empresa_id', 'is_super_admin']);
        });
    }
};
