<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'queue';
    }

    public function check(): array
    {
        $result = [
            'status' => 'ok',
            'driver' => config('queue.default'),
            'size' => 0,
            'failed' => 0,
        ];

        try {
            $result['size'] = $this->getQueueSize();
            $result['failed'] = $this->getFailedJobsCount();

            if ($result['size'] > 1000) {
                $result['status'] = 'warning';
                $result['message'] = 'Queue size is high';
            } elseif ($result['size'] > 100) {
                $result['status'] = 'attention';
            }

            if ($result['failed'] > 0) {
                $result['has_failed_jobs'] = true;
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

    private function getQueueSize(): int
    {
        $driver = config('queue.default');

        if ($driver === 'redis') {
            try {
                $connection = config('queue.connections.redis.connection', 'default');
                $queueName = config('queue.connections.redis.queue', 'default');
                $redis = Redis::connection($connection);
                
                return (int) $redis->llen('queues:' . $queueName);
            } catch (\Throwable) {
                return 0;
            }
        }

        if ($driver === 'database') {
            try {
                return DB::table(config('queue.connections.database.table', 'jobs'))->count();
            } catch (\Throwable) {
                return 0;
            }
        }

        return 0;
    }

    private function getFailedJobsCount(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return DB::table('failed_jobs')->count();
            }
        } catch (\Throwable) {
        }

        return 0;
    }
}
