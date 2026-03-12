<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchedulerLogger
{
    private static array $runningTasks = [];

    /**
     * Registra início de uma tarefa
     * Retorna o ID do registro para atualização posterior
     */
    public static function start(string $taskName): ?int
    {
        try {
            self::writeHeartbeat();

            if (!DB::getSchemaBuilder()->hasTable('scheduled_task_runs')) {
                return null;
            }

            $id = DB::table('scheduled_task_runs')->insertGetId([
                'task_name' => substr($taskName, 0, 100),
                'started_at' => now(),
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::$runningTasks[$taskName] = [
                'id' => $id,
                'started_at' => microtime(true),
            ];

            return $id;
        } catch (\Throwable $e) {
            Log::warning('Failed to log scheduler start: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra conclusão com sucesso
     */
    public static function success(string $taskName, ?string $message = null): void
    {
        self::finish($taskName, 'success', $message);
    }

    /**
     * Registra conclusão com falha
     */
    public static function failed(string $taskName, string $error): void
    {
        self::finish($taskName, 'failed', $error);
    }

    /**
     * Finaliza o registro de uma tarefa
     */
    private static function finish(string $taskName, string $status, ?string $message): void
    {
        try {
            self::writeHeartbeat();

            if (!DB::getSchemaBuilder()->hasTable('scheduled_task_runs')) {
                return;
            }

            $runInfo = self::$runningTasks[$taskName] ?? null;

            if ($runInfo && isset($runInfo['id'])) {
                DB::table('scheduled_task_runs')
                    ->where('id', $runInfo['id'])
                    ->update([
                        'finished_at' => now(),
                        'status' => $status,
                        'message' => $message ? substr($message, 0, 65000) : null,
                        'updated_at' => now(),
                    ]);

                unset(self::$runningTasks[$taskName]);
            } else {
                DB::table('scheduled_task_runs')->insert([
                    'task_name' => substr($taskName, 0, 100),
                    'started_at' => now(),
                    'finished_at' => now(),
                    'status' => $status,
                    'message' => $message ? substr($message, 0, 65000) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to log scheduler finish: ' . $e->getMessage());
        }
    }

    /**
     * Registra heartbeat do scheduler
     */
    public static function heartbeat(): void
    {
        self::writeHeartbeat();
    }

    /**
     * Escreve arquivo de heartbeat
     */
    private static function writeHeartbeat(): void
    {
        try {
            $dir = storage_path('framework');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir . '/scheduler-heartbeat';
            file_put_contents($path, now()->toIso8601String());
        } catch (\Throwable) {
        }
    }

    /**
     * Limpa registros antigos
     */
    public static function cleanup(int $days = 7): int
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('scheduled_task_runs')) {
                return 0;
            }

            return DB::table('scheduled_task_runs')
                ->where('created_at', '<', now()->subDays($days))
                ->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Obtém última execução de uma tarefa
     */
    public static function getLastRun(?string $taskName = null): ?object
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('scheduled_task_runs')) {
                return null;
            }

            $query = DB::table('scheduled_task_runs')
                ->orderBy('started_at', 'desc');

            if ($taskName) {
                $query->where('task_name', $taskName);
            }

            return $query->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
