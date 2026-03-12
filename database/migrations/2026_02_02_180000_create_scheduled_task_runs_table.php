<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Histórico de execuções de tarefas agendadas (crons) para acompanhamento no Super Admin.
     */
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('task_name', 100)->comment('Ex: parcelas:marcar-atrasadas');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('status', 20)->default('running')->comment('running, success, failed');
            $table->text('message')->nullable()->comment('Mensagem de sucesso ou erro');
            $table->timestamps();

            $table->index(['task_name', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
