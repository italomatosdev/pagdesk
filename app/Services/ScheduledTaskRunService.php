<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;

/**
 * Serviço para registrar execuções de tarefas agendadas (crons).
 * Use no início do handle() e no fim (sucesso ou catch de falha).
 */
class ScheduledTaskRunService
{
    /**
     * Registra o início da execução. Chame no início do comando.
     *
     * @param  string  $taskName  Ex: parcelas:marcar-atrasadas
     * @return ScheduledTaskRun
     */
    public function start(string $taskName): ScheduledTaskRun
    {
        return ScheduledTaskRun::create([
            'task_name' => $taskName,
            'started_at' => now(),
            'status' => ScheduledTaskRun::STATUS_RUNNING,
            'message' => null,
        ]);
    }

    /**
     * Marca a execução como sucesso. Chame ao terminar o comando com sucesso.
     *
     * @param  ScheduledTaskRun  $run
     * @param  string|null  $message  Ex: "150 parcelas marcadas"
     */
    public function success(ScheduledTaskRun $run, ?string $message = null): void
    {
        $run->update([
            'finished_at' => now(),
            'status' => ScheduledTaskRun::STATUS_SUCCESS,
            'message' => $message,
        ]);
    }

    /**
     * Marca a execução como falha. Chame no catch do comando.
     *
     * @param  ScheduledTaskRun  $run
     * @param  string|null  $message  Mensagem de erro (ex: $e->getMessage())
     */
    public function fail(ScheduledTaskRun $run, ?string $message = null): void
    {
        $run->update([
            'finished_at' => now(),
            'status' => ScheduledTaskRun::STATUS_FAILED,
            'message' => $message,
        ]);
    }
}
