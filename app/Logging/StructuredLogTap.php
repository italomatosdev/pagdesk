<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;

/**
 * Configura o canal de log estruturado: saída JSON + contexto (user_id, empresa_id, request_id).
 * Aceita Monolog\Logger ou Illuminate\Log\Logger (Laravel passa o wrapper em versões recentes).
 */
class StructuredLogTap
{
    public function __invoke(object $logger): void
    {
        $monolog = method_exists($logger, 'getLogger') ? $logger->getLogger() : $logger;
        if (!$monolog instanceof Logger) {
            return;
        }
        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true, true));
            $handler->pushProcessor(new StructuredLogProcessor());
        }
    }
}
