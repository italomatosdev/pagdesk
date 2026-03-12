<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;

class DatabaseHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): array
    {
        try {
            $start = microtime(true);
            
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
                'connection' => config('database.default'),
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
