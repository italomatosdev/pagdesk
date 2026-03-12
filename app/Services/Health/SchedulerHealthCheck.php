<?php

namespace App\Services\Health;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SchedulerHealthCheck implements HealthCheckInterface
{
    private const MAX_MINUTES_WITHOUT_RUN = 5;

    public function name(): string
    {
        return 'scheduler';
    }

    public function check(): array
    {
        $result = [
            'status' => 'unknown',
            'last_run' => null,
            'last_task' => null,
        ];

        try {
            $lastRun = $this->getLastSchedulerRun();

            if ($lastRun) {
                $result['last_run'] = $lastRun->started_at;
                $result['last_task'] = $lastRun->task_name ?? null;
                $result['last_status'] = $lastRun->status ?? null;

                $lastRunTime = Carbon::parse($lastRun->started_at);
                $minutesSinceLastRun = now()->diffInMinutes($lastRunTime);

                if ($minutesSinceLastRun > self::MAX_MINUTES_WITHOUT_RUN) {
                    $result['status'] = 'warning';
                    $result['minutes_since_last_run'] = $minutesSinceLastRun;
                    $result['message'] = "Last run was {$minutesSinceLastRun} minutes ago";
                } else {
                    $result['status'] = 'ok';
                }
            } else {
                $heartbeat = $this->checkHeartbeatFile();
                if ($heartbeat) {
                    $result = array_merge($result, $heartbeat);
                } else {
                    $result['status'] = 'no_data';
                    $result['message'] = 'No scheduler runs recorded';
                }
            }

        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function isCritical(): bool
    {
        return false;
    }

    private function getLastSchedulerRun(): ?object
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('scheduled_task_runs')) {
                return null;
            }

            return DB::table('scheduled_task_runs')
                ->orderBy('started_at', 'desc')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function checkHeartbeatFile(): ?array
    {
        $heartbeatFile = storage_path('framework/scheduler-heartbeat');
        
        if (!file_exists($heartbeatFile)) {
            return null;
        }

        $lastHeartbeat = filemtime($heartbeatFile);
        $minutesSinceHeartbeat = (time() - $lastHeartbeat) / 60;

        return [
            'last_run' => date('Y-m-d H:i:s', $lastHeartbeat),
            'source' => 'heartbeat_file',
            'status' => $minutesSinceHeartbeat > 2 ? 'warning' : 'ok',
        ];
    }

    /**
     * Retorna estatísticas do scheduler das últimas 24h
     */
    public function getStats(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('scheduled_task_runs')) {
                return [];
            }

            $since = now()->subDay();

            return [
                'total_runs_24h' => DB::table('scheduled_task_runs')
                    ->where('started_at', '>=', $since)
                    ->count(),
                'successful_24h' => DB::table('scheduled_task_runs')
                    ->where('started_at', '>=', $since)
                    ->where('status', 'success')
                    ->count(),
                'failed_24h' => DB::table('scheduled_task_runs')
                    ->where('started_at', '>=', $since)
                    ->where('status', 'failed')
                    ->count(),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
