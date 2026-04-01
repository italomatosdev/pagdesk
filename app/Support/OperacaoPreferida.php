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

    /**
     * Filtro GET com múltiplas operações (ex.: dashboard).
     *
     * - operacao_id[]: interseção com ids permitidos; com envio do form (dashboard_filtrar=1), lista vazia = todas permitidas.
     * - operacao_id escalar (legado): uma operação.
     * - operacao_id vazio no GET (legado): todas permitidas.
     * - Link manual com operacao_id[]= sem dashboard_filtrar: aplica as selecionadas.
     * - Sem parâmetros de operação e sem envio do form: usa operação preferida (uma), se válida; senão todas.
     *
     * @param  array<int>  $idsPermitidos
     * @return array<int>
     */
    public static function resolverOperacoesIdsParaFiltroGet(Request $request, array $idsPermitidos, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $ids = array_values(array_unique(array_map('intval', $idsPermitidos)));
        if ($ids === []) {
            return [];
        }

        $submeteu = $request->boolean('dashboard_filtrar');
        $raw = $request->input('operacao_id');

        if (is_array($raw)) {
            $selected = array_values(array_unique(array_intersect(
                array_values(array_filter(array_map('intval', $raw), fn ($i) => $i > 0)),
                $ids
            )));

            if ($submeteu) {
                return $selected !== [] ? $selected : $ids;
            }

            if ($selected !== []) {
                return $selected;
            }
        } elseif (is_string($raw) || is_numeric($raw)) {
            if ($request->filled('operacao_id')) {
                $id = (int) $raw;

                return in_array($id, $ids, true) ? [$id] : $ids;
            }
        }

        if ($request->has('operacao_id') && ! is_array($raw)) {
            return $ids;
        }

        if (! $submeteu) {
            $pref = $user->getOperacaoPrincipalId();
            if ($pref !== null && in_array($pref, $ids, true)) {
                return [$pref];
            }
        }

        return $ids;
    }
}
