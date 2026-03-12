<?php

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\Auditoria;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Registrar ação na auditoria
     *
     * @param string $action Nome da ação (ex: criar_emprestimo)
     * @param mixed $model Modelo afetado (opcional)
     * @param array|null $oldValues Valores anteriores (opcional)
     * @param array|null $newValues Valores novos (opcional)
     * @param string|null $observacoes Observações adicionais
     * @return Auditoria
     */
    public static function auditar(
        string $action,
        $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $observacoes = null
    ): Auditoria {
        $request = request();

        return Auditoria::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'observacoes' => $observacoes,
        ]);
    }
}

