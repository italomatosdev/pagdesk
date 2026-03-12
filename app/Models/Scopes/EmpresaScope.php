<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class EmpresaScope implements Scope
{
    /**
     * Aplicar o scope na query
     * Filtra automaticamente por empresa_id do usuário autenticado
     * Super Admin não tem filtro (vê tudo)
     * Para Cliente, também inclui clientes vinculados através de empresa_cliente_vinculos
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && !auth()->user()->isSuperAdmin()) {
            $empresaId = auth()->user()->empresa_id;
            if ($empresaId) {
                // Se for modelo Cliente, incluir também clientes vinculados
                if ($model instanceof \App\Modules\Core\Models\Cliente) {
                    $builder->where(function ($q) use ($empresaId, $model) {
                        $q->where($model->getTable() . '.empresa_id', $empresaId)
                          ->orWhereExists(function ($subQuery) use ($empresaId, $model) {
                              $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                  ->from('empresa_cliente_vinculos')
                                  ->whereColumn('empresa_cliente_vinculos.cliente_id', $model->getTable() . '.id')
                                  ->where('empresa_cliente_vinculos.empresa_id', $empresaId)
                                  ->whereNull('empresa_cliente_vinculos.deleted_at');
                          });
                    });
                } else {
                    // Para outros modelos, comportamento padrão
                    $builder->where($model->getTable() . '.empresa_id', $empresaId);
                }
            } else {
                // Se usuário não tem empresa_id, não retorna nada
                $builder->whereRaw('1 = 0');
            }
        }
    }
}
