<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolve qual operação_id usar como padrão em filtros (GET) e formulários,
 * respeitando query string, old() e preferência do usuário (user_operacao_preferida).
 */
class OperacaoPreferida
{
    /**
     * Filtro GET: request explícito > preferência > null (lista usa fallback da view, ex. "Todas").
     *
     * @param  array<int>  $idsPermitidos
     */
    public static function resolverParaFiltroGet(Request $request, array $idsPermitidos, ?User $user = null): ?int
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $idsPermitidos)));

        if ($request->filled('operacao_id')) {
            $id = (int) $request->input('operacao_id');

            return in_array($id, $ids, true) ? $id : null;
        }

        if ($request->has('operacao_id')) {
            return null;
        }

        $pref = $user->getOperacaoPrincipalId();

        return ($pref !== null && in_array($pref, $ids, true)) ? $pref : null;
    }

    /**
     * Formulário POST: old() após validação > preferência > null.
     *
     * @param  array<int>  $idsPermitidos
     */
    public static function resolverParaFormulario(array $idsPermitidos, ?int $oldOperacaoId, ?User $user = null): ?int
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $idsPermitidos)));

        if ($oldOperacaoId !== null) {
            $old = (int) $oldOperacaoId;

            return in_array($old, $ids, true) ? $old : null;
        }

        $pref = $user->getOperacaoPrincipalId();

        return ($pref !== null && in_array($pref, $ids, true)) ? $pref : null;
    }

    /**
     * Formulário GET (ex.: sangria): query string > old() > preferência.
     *
     * @param  array<int>  $idsPermitidos
     */
    public static function resolverParaFormularioOuQuery(Request $request, array $idsPermitidos, ?User $user = null): ?int
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $idsPermitidos)));

        if ($request->filled('operacao_id')) {
            $id = (int) $request->input('operacao_id');

            return in_array($id, $ids, true) ? $id : null;
        }

        $old = old('operacao_id');
        if ($old !== null && $old !== '') {
            $o = (int) $old;

            return in_array($o, $ids, true) ? $o : null;
        }

        $pref = $user->getOperacaoPrincipalId();

        return ($pref !== null && in_array($pref, $ids, true)) ? $pref : null;
    }
}
