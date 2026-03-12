<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adiciona contexto (user_id, empresa_id, request_id) a cada registro de log.
 * Útil para rastreamento e análise em ferramentas de observabilidade.
 */
class StructuredLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = [
            'user_id' => auth()->id(),
            'empresa_id' => auth()->user()?->empresa_id ?? null,
        ];
        if (app()->has('request') && $req = request()) {
            $extra['request_id'] = $req->header('X-Request-ID') ?: $req->header('X-Correlation-ID');
        }
        $record->extra = array_merge($record->extra, array_filter($extra, fn ($v) => $v !== null));

        return $record;
    }
}
