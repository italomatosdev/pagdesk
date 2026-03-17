<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Devedores — Mural de clientes com parcelas em atraso (acima de X dias).
 * Escopo: sistema inteiro (todas as empresas). Único filtro: quantidade de dias em atraso (dias_min).
 */
class DevedoresController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar devedores (clientes com ao menos uma parcela atrasada acima de X dias).
     * Ignora escopo de empresa para mostrar todo o sistema; filtro apenas por dias em atraso.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            abort(403, 'Acesso negado. Esta consulta é para usuários do sistema (não Super Admin).');
        }

        $diasMin = $request->input('dias_min');
        if ($diasMin !== null && $diasMin !== '') {
            $diasMin = max(0, (int) $diasMin);
        } else {
            $diasMin = 1;
        }

        $hoje = now()->format('Y-m-d');

        $diasAtrasoPorCliente = Parcela::withoutGlobalScope(EmpresaScope::class)
            ->join('emprestimos', 'parcelas.emprestimo_id', '=', 'emprestimos.id')
            ->whereIn('parcelas.status', ['pendente', 'atrasada'])
            ->where('parcelas.data_vencimento', '<', now()->startOfDay())
            ->whereRaw('DATEDIFF(?, parcelas.data_vencimento) >= ?', [$hoje, $diasMin])
            ->where('emprestimos.status', 'ativo')
            ->selectRaw('emprestimos.cliente_id AS cliente_id, MAX(DATEDIFF(?, parcelas.data_vencimento)) AS dias_atraso', [$hoje])
            ->groupBy('emprestimos.cliente_id')
            ->pluck('dias_atraso', 'cliente_id')
            ->filter(fn ($_, $id) => $id !== null)
            ->toArray();

        $clienteIds = array_keys($diasAtrasoPorCliente);

        $clientes = Cliente::withoutGlobalScope(EmpresaScope::class)
            ->with('documentos')
            ->whereIn('id', $clienteIds)
            ->orderBy('nome')
            ->get();

        return view('consultas.devedores', compact('clientes', 'diasMin', 'diasAtrasoPorCliente'));
    }
}
