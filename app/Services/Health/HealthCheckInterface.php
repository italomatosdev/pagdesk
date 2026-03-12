<?php

namespace App\Services\Health;

interface HealthCheckInterface
{
    /**
     * Nome do check para identificação
     */
    public function name(): string;

    /**
     * Executa o health check
     * 
     * @return array{status: string, message?: string, details?: array}
     */
    public function check(): array;

    /**
     * Indica se este check é crítico (afeta o status geral)
     */
    public function isCritical(): bool;
}
