<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\Cache;

class CacheHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'cache';
    }

    public function check(): array
    {
        try {
            $start = microtime(true);
            
            $key = 'health_check_' . uniqid();
            $value = 'test_' . time();
            
            Cache::put($key, $value, 10);
            $retrieved = Cache::get($key);
            Cache::forget($key);
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved !== $value) {
                return [
                    'status' => 'fail',
                    'message' => 'Cache read/write mismatch',
                ];
            }

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
                'driver' => config('cache.default'),
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
        return true;
    }
}
