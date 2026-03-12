<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTaskRun extends Model
{
    protected $table = 'scheduled_task_runs';

    protected $fillable = [
        'task_name',
        'started_at',
        'finished_at',
        'status',
        'message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /**
     * Última execução por nome da tarefa (uma por task_name).
     */
    public static function ultimaPorTask(string $taskName): ?self
    {
        return static::where('task_name', $taskName)
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Lista de nomes de tarefas que já rodaram (para exibir no Super Admin).
     */
    public static function taskNamesExistentes(): array
    {
        return static::query()
            ->select('task_name')
            ->distinct()
            ->orderBy('task_name')
            ->pluck('task_name')
            ->toArray();
    }

    /**
     * Histórico das últimas N execuções de uma tarefa.
     */
    public static function historico(string $taskName, int $limit = 20)
    {
        return static::where('task_name', $taskName)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }
}
