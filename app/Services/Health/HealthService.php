<?php

namespace App\Services\Health;

class HealthService
{
    /** @var HealthCheckInterface[] */
    private array $checks = [];

    public function __construct(
        DatabaseHealthCheck $database,
        RedisHealthCheck $redis,
        CacheHealthCheck $cache,
        QueueHealthCheck $queue,
        SchedulerHealthCheck $scheduler
    ) {
        $this->checks = [
            $database,
            $redis,
            $cache,
            $queue,
            $scheduler,
        ];
    }

    /**
     * Verifica se a aplicação está "viva" (responde requisições)
     * Usado por: Kubernetes liveness probe, load balancers básicos
     */
    public function live(): array
    {
        return [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Verifica se a aplicação está "pronta" para receber tráfego
     * Usado por: Kubernetes readiness probe, load balancers avançados
     */
    public function ready(): array
    {
        $criticalChecks = array_filter(
            $this->checks,
            fn(HealthCheckInterface $check) => $check->isCritical()
        );

        $results = [];
        $allOk = true;

        foreach ($criticalChecks as $check) {
            $result = $check->check();
            $results[$check->name()] = $result['status'];
            
            if ($result['status'] === 'fail') {
                $allOk = false;
            }
        }

        return [
            'status' => $allOk ? 'ready' : 'not_ready',
            'checks' => $results,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Health check completo com todas as informações
     * Usado por: Dashboards, monitoramento detalhado
     */
    public function full(): array
    {
        $results = [];
        $hasCriticalFailure = false;
        $hasWarning = false;

        foreach ($this->checks as $check) {
            $result = $check->check();
            $results[$check->name()] = $result;

            if ($result['status'] === 'fail' && $check->isCritical()) {
                $hasCriticalFailure = true;
            }
            
            if (in_array($result['status'], ['warning', 'attention'])) {
                $hasWarning = true;
            }
        }

        $status = 'healthy';
        if ($hasCriticalFailure) {
            $status = 'unhealthy';
        } elseif ($hasWarning) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'app' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $results,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Retorna status resumido para cada check
     */
    public function summary(): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            $result = $check->check();
            $results[$check->name()] = $result['status'];
        }

        return $results;
    }
}
