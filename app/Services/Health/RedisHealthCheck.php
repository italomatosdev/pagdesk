<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\Redis;

class RedisHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'redis';
    }

    public function check(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return [
                'status' => 'skip',
                'message' => 'Redis not configured',
            ];
        }

        try {
            $start = microtime(true);
            
            $redis = Redis::connection();
            $pong = $redis->ping();
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
                'response' => $pong,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function isCritical(): bool
    {
        return config('cache.default') === 'redis' || config('queue.default') === 'redis';
    }
}
