<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Devedores — Mural de clientes com parcelas em atraso (acima de X dias).
 * Sistema todo: sem filtro por operação ou consultor; compartilhado com todos os usuários.
 */
class DevedoresController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar devedores (clientes com ao menos uma parcela atrasada acima de X dias).
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

        $clienteIds = Parcela::query()
            ->join('emprestimos', 'parcelas.emprestimo_id', '=', 'emprestimos.id')
            ->whereIn('parcelas.status', ['pendente', 'atrasada'])
            ->where('parcelas.data_vencimento', '<', now()->startOfDay())
            ->whereRaw('DATEDIFF(?, parcelas.data_vencimento) >= ?', [now()->format('Y-m-d'), $diasMin])
            ->where('emprestimos.status', 'ativo')
            ->pluck('emprestimos.cliente_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $clientes = Cliente::with('documentos')
            ->whereIn('id', $clienteIds)
            ->orderBy('nome')
            ->get();

        return view('consultas.devedores', compact('clientes', 'diasMin'));
    }
}
