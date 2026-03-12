<?php

namespace App\Http\Controllers;

use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private HealthService $healthService
    ) {}

    /**
     * Health check completo
     * 
     * Endpoint: GET /health
     * 
     * Retorna status detalhado de todos os serviços.
     * HTTP 200 = healthy/degraded
     * HTTP 503 = unhealthy (falha crítica)
     */
    public function __invoke(): JsonResponse
    {
        $result = $this->healthService->full();
        $status = $result['status'] === 'unhealthy' ? 503 : 200;

        return response()->json($result, $status);
    }

    /**
     * Liveness probe
     * 
     * Endpoint: GET /health/live
     * 
     * Verifica se a aplicação está "viva" (processo PHP rodando).
     * Usado por Kubernetes liveness probe ou load balancers básicos.
     * Sempre retorna 200 se o PHP estiver respondendo.
     */
    public function live(): JsonResponse
    {
        return response()->json(
            $this->healthService->live(),
            200
        );
    }

    /**
     * Readiness probe
     * 
     * Endpoint: GET /health/ready
     * 
     * Verifica se a aplicação está "pronta" para receber tráfego.
     * Usado por Kubernetes readiness probe ou load balancers avançados.
     * Verifica apenas serviços críticos (DB, Redis, Cache).
     * 
     * HTTP 200 = pronto para receber tráfego
     * HTTP 503 = não está pronto (não enviar tráfego)
     */
    public function ready(): JsonResponse
    {
        $result = $this->healthService->ready();
        $status = $result['status'] === 'ready' ? 200 : 503;

        return response()->json($result, $status);
    }
}
