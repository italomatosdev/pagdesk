<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\EmprestimoGarantia;
use App\Modules\Loans\Models\EmprestimoGarantiaAnexo;
use App\Support\ClienteNomeExibicao;
use App\Support\FichaContatoLookup;
use App\Support\OperacaoPreferida;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GarantiaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar todas as garantias (apenas das operações do usuário; Super Admin vê todas).
     * Consultor vê apenas garantias dos empréstimos em que é o consultor; gestor/admin vê todas da operação.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $operacoesIds = Operacao::where('ativo', true)->pluck('id')->toArray();
            $emprestimoScope = fn ($q) => $q->whereIn('operacao_id', $operacoesIds);
        } else {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                $emprestimoScope = fn ($q) => $q->whereRaw('1 = 0');
            } else {
                $opsGestorAdmin = $user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']);
                $opsConsultor = $user->getOperacoesIdsOndeTemPapel(['consultor']);
                $opsSoConsultor = array_values(array_diff($opsConsultor, $opsGestorAdmin));
                $emprestimoScope = function ($q) use ($opsGestorAdmin, $opsSoConsultor, $user) {
                    $q->where(function ($q2) use ($opsGestorAdmin, $opsSoConsultor, $user) {
                        if (!empty($opsGestorAdmin)) {
                            $q2->whereIn('operacao_id', $opsGestorAdmin);
                        }
                        if (!empty($opsSoConsultor)) {
                            $q2->orWhere(function ($q3) use ($opsSoConsultor, $user) {
                                $q3->whereIn('operacao_id', $opsSoConsultor)->where('consultor_id', $user->id);
                            });
                        }
                    });
                };
            }
        }

        $query = EmprestimoGarantia::with(['emprestimo.cliente', 'emprestimo.operacao', 'anexos'])
            ->whereHas('emprestimo', $emprestimoScope);

        // Filtros
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('descricao', 'like', "%{$busca}%")
                  ->orWhere('localizacao', 'like', "%{$busca}%")
                  ->orWhereHas('emprestimo.cliente', function ($qc) use ($busca) {
                      $qc->where('nome', 'like', "%{$busca}%");
                  });
            });
        }

        $operacaoIdFiltro = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
        if ($operacaoIdFiltro !== null && ! empty($operacoesIds) && in_array($operacaoIdFiltro, $operacoesIds, true)) {
            $query->whereHas('emprestimo', fn ($q) => $q->where('operacao_id', $operacaoIdFiltro));
            if (! $user->temAlgumPapelNaOperacao($operacaoIdFiltro, ['gestor', 'administrador'])) {
                $query->whereHas('emprestimo', fn ($q) => $q->where('consultor_id', $user->id));
            }
        }

        if ($request->filled('status_emprestimo')) {
            $query->whereHas('emprestimo', function ($q) use ($request) {
                $q->where('status', $request->input('status_emprestimo'));
            });
        } else {
            $query->whereHas('emprestimo', fn ($q) => $q->where('status', '!=', 'cancelado'));
        }

        $garantias = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($garantias->map(fn ($g) => $g->emprestimo)->filter())
        );

        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $statsQuery = function ($q) use ($emprestimoScope) {
            $emprestimoScope($q);
            $q->where('status', '!=', 'cancelado');
        };
        $stats = [
            'total' => EmprestimoGarantia::whereHas('emprestimo', $statsQuery)->count(),
            'valor_total' => EmprestimoGarantia::whereHas('emprestimo', $statsQuery)->sum('valor_avaliado'),
            'imoveis' => EmprestimoGarantia::where('categoria', 'imovel')->whereHas('emprestimo', $statsQuery)->count(),
            'veiculos' => EmprestimoGarantia::where('categoria', 'veiculo')->whereHas('emprestimo', $statsQuery)->count(),
            'outros' => EmprestimoGarantia::where('categoria', 'outros')->whereHas('emprestimo', $statsQuery)->count(),
        ];

        return view('garantias.index', compact('garantias', 'operacoes', 'stats', 'fichasContatoPorClienteOperacao', 'operacaoIdFiltro'));
    }

    /**
     * Mostrar detalhes de uma garantia
     */
    public function show(int $garantiaId): View
    {
        $garantia = EmprestimoGarantia::with([
            'emprestimo.cliente',
            'emprestimo.operacao',
            'emprestimo.consultor',
            'anexos'
        ])->findOrFail($garantiaId);

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds) || !in_array((int) $garantia->emprestimo->operacao_id, $operacoesIds, true)) {
                abort(403, 'Você não tem permissão para visualizar esta garantia.');
            }
        }

        $nomeClienteExibicao = ClienteNomeExibicao::forEmprestimo($garantia->emprestimo);

        return view('garantias.show', compact('garantia', 'nomeClienteExibicao'));
    }

    /**
     * Adicionar garantia a um empréstimo
     */
    public function store(Request $request, int $emprestimoId): RedirectResponse
    {
        $emprestimo = Emprestimo::with('liberacao')->findOrFail($emprestimoId);

        // Verificar se é tipo empenho
        if (!$emprestimo->isEmpenho()) {
            return back()->with('error', 'Apenas empréstimos do tipo empenho podem ter garantias.');
        }

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível adicionar garantias a empréstimos finalizados.');
        }
        // Após liberação do dinheiro, não é possível adicionar novas garantias
        if ($emprestimo->foiLiberado()) {
            return back()->with('error', 'Não é possível adicionar garantias após a liberação do empréstimo.');
        }

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para adicionar garantias a este empréstimo.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem acesso a esta operação.');
            }
        }

        $validated = $request->validate([
            'categoria' => 'required|in:imovel,veiculo,outros',
            'descricao' => 'required|string|max:255',
            'valor_avaliado' => 'nullable|numeric|min:0',
            'localizacao' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
            'anexos' => 'nullable|array',
            'anexos.*' => 'file|max:5120|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);

        try {
            // Criar garantia
            $garantia = EmprestimoGarantia::create([
                'emprestimo_id' => $emprestimo->id,
                'categoria' => $validated['categoria'],
                'descricao' => $validated['descricao'],
                'valor_avaliado' => $validated['valor_avaliado'] ?? null,
                'localizacao' => $validated['localizacao'] ?? null,
                'observacoes' => $validated['observacoes'] ?? null,
                'status' => 'ativa',
            ]);

            // Upload de anexos
            if ($request->hasFile('anexos')) {
                foreach ($request->file('anexos') as $arquivo) {
                    $extensao = strtolower($arquivo->getClientOriginalExtension());
                    $caminho = $arquivo->store('garantias/' . $emprestimo->id, 'public');

                    EmprestimoGarantiaAnexo::create([
                        'garantia_id' => $garantia->id,
                        'nome_arquivo' => $arquivo->getClientOriginalName(),
                        'caminho' => $caminho,
                        'tipo' => EmprestimoGarantiaAnexo::determinarTipo($extensao),
                        'tamanho' => $arquivo->getSize(),
                    ]);
                }
            }

            return back()->with('success', 'Garantia adicionada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao adicionar garantia: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Atualizar garantia
     */
    public function update(Request $request, int $garantiaId): RedirectResponse
    {
        $garantia = EmprestimoGarantia::with('emprestimo.liberacao')->findOrFail($garantiaId);
        $emprestimo = $garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível editar garantias de empréstimos finalizados.');
        }
        // Após liberação do dinheiro, garantias não podem mais ser editadas
        if ($emprestimo->foiLiberado()) {
            return back()->with('error', 'Não é possível editar garantias após a liberação do empréstimo.');
        }

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para editar esta garantia.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem acesso a esta operação.');
            }
        }

        $validated = $request->validate([
            'categoria' => 'required|in:imovel,veiculo,outros',
            'descricao' => 'required|string|max:255',
            'valor_avaliado' => 'nullable|numeric|min:0',
            'localizacao' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
            'anexos' => 'nullable|array',
            'anexos.*' => 'file|max:5120|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);

        try {
            $garantia->update([
                'categoria' => $validated['categoria'],
                'descricao' => $validated['descricao'],
                'valor_avaliado' => $validated['valor_avaliado'] ?? null,
                'localizacao' => $validated['localizacao'] ?? null,
                'observacoes' => $validated['observacoes'] ?? null,
            ]);

            // Upload de novos anexos
            if ($request->hasFile('anexos')) {
                foreach ($request->file('anexos') as $arquivo) {
                    $extensao = strtolower($arquivo->getClientOriginalExtension());
                    $caminho = $arquivo->store('garantias/' . $emprestimo->id, 'public');

                    EmprestimoGarantiaAnexo::create([
                        'garantia_id' => $garantia->id,
                        'nome_arquivo' => $arquivo->getClientOriginalName(),
                        'caminho' => $caminho,
                        'tipo' => EmprestimoGarantiaAnexo::determinarTipo($extensao),
                        'tamanho' => $arquivo->getSize(),
                    ]);
                }
            }

            return back()->with('success', 'Garantia atualizada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar garantia: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Excluir garantia
     */
    public function destroy(int $garantiaId): RedirectResponse
    {
        $garantia = EmprestimoGarantia::with(['emprestimo.liberacao', 'anexos'])->findOrFail($garantiaId);
        $emprestimo = $garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível excluir garantias de empréstimos finalizados.');
        }
        // Após liberação do dinheiro, garantias não podem mais ser excluídas
        if ($emprestimo->foiLiberado()) {
            return back()->with('error', 'Não é possível excluir garantias após a liberação do empréstimo.');
        }

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para excluir esta garantia.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem acesso a esta operação.');
            }
        }

        try {
            // Anexos serão excluídos automaticamente pelo cascade e o evento deleting do model
            $garantia->delete();

            return back()->with('success', 'Garantia excluída com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao excluir garantia: ' . $e->getMessage());
        }
    }

    /**
     * Excluir anexo
     */
    public function destroyAnexo(int $anexoId): RedirectResponse
    {
        $anexo = EmprestimoGarantiaAnexo::with('garantia.emprestimo.liberacao')->findOrFail($anexoId);
        $emprestimo = $anexo->garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível excluir anexos de garantias de empréstimos finalizados.');
        }
        // Após liberação do dinheiro, anexos de garantia não podem mais ser excluídos
        if ($emprestimo->foiLiberado()) {
            return back()->with('error', 'Não é possível excluir anexos de garantias após a liberação do empréstimo.');
        }

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para excluir este anexo.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem acesso a esta operação.');
            }
        }

        try {
            $anexo->delete(); // O evento deleting do model remove o arquivo do storage

            return back()->with('success', 'Anexo excluído com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao excluir anexo: ' . $e->getMessage());
        }
    }

    /**
     * Upload de anexos
     */
    public function uploadAnexo(Request $request, int $garantiaId)
    {
        $garantia = EmprestimoGarantia::with('emprestimo.liberacao')->findOrFail($garantiaId);
        $emprestimo = $garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Não é possível adicionar anexos a garantias de empréstimos finalizados.'], 403);
            }
            return back()->with('error', 'Não é possível adicionar anexos a garantias de empréstimos finalizados.');
        }
        // Após liberação do dinheiro, não é possível adicionar anexos à garantia
        if ($emprestimo->foiLiberado()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Não é possível adicionar anexos a garantias após a liberação do empréstimo.'], 403);
            }
            return back()->with('error', 'Não é possível adicionar anexos a garantias após a liberação do empréstimo.');
        }

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Sem permissão'], 403);
            }
            return back()->with('error', 'Você não tem permissão para fazer upload nesta garantia.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['error' => 'Sem acesso a esta operação.'], 403);
                }
                return back()->with('error', 'Você não tem acesso a esta operação.');
            }
        }

        $request->validate([
            'arquivo' => 'required|file|max:5120|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);

        try {
            $arquivo = $request->file('arquivo');
            $extensao = strtolower($arquivo->getClientOriginalExtension());
            $caminho = $arquivo->store('garantias/' . $emprestimo->id, 'public');

            $anexo = EmprestimoGarantiaAnexo::create([
                'garantia_id' => $garantia->id,
                'nome_arquivo' => $arquivo->getClientOriginalName(),
                'caminho' => $caminho,
                'tipo' => EmprestimoGarantiaAnexo::determinarTipo($extensao),
                'tamanho' => $arquivo->getSize(),
            ]);

            // Se for requisição AJAX, retorna JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'anexo' => [
                        'id' => $anexo->id,
                        'nome' => $anexo->nome_arquivo,
                        'url' => $anexo->url,
                        'tipo' => $anexo->tipo,
                        'icone' => $anexo->icone,
                        'tamanho' => $anexo->tamanho_formatado,
                    ],
                ]);
            }

            // Se for formulário normal, redireciona de volta
            return back()->with('success', 'Anexo enviado com sucesso!');
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Erro ao enviar anexo: ' . $e->getMessage());
        }
    }
}
