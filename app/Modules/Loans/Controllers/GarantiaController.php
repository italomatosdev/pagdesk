<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\EmprestimoGarantia;
use App\Modules\Loans\Models\EmprestimoGarantiaAnexo;
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
     * Listar todas as garantias
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();

        $query = EmprestimoGarantia::with(['emprestimo.cliente', 'emprestimo.operacao', 'anexos'])
            ->whereHas('emprestimo', function ($q) use ($operacoesIds) {
                if (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
            });

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

        if ($request->filled('operacao_id')) {
            $query->whereHas('emprestimo', function ($q) use ($request) {
                $q->where('operacao_id', $request->input('operacao_id'));
            });
        }

        if ($request->filled('status_emprestimo')) {
            $query->whereHas('emprestimo', function ($q) use ($request) {
                $q->where('status', $request->input('status_emprestimo'));
            });
        } else {
            // Por padrão, excluir garantias de empréstimos cancelados
            $query->whereHas('emprestimo', function ($q) {
                $q->where('status', '!=', 'cancelado');
            });
        }

        $garantias = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Operações para filtro
        $operacoes = \App\Modules\Core\Models\Operacao::where('ativo', true)
            ->whereIn('id', $operacoesIds)
            ->get();

        // Estatísticas (excluir garantias de empréstimos cancelados)
        $stats = [
            'total' => EmprestimoGarantia::whereHas('emprestimo', function ($q) use ($operacoesIds) {
                if (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
                $q->where('status', '!=', 'cancelado');
            })->count(),
            'valor_total' => EmprestimoGarantia::whereHas('emprestimo', function ($q) use ($operacoesIds) {
                if (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
                $q->where('status', '!=', 'cancelado');
            })->sum('valor_avaliado'),
            'imoveis' => EmprestimoGarantia::where('categoria', 'imovel')->whereHas('emprestimo', function ($q) use ($operacoesIds) {
                if (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
                $q->where('status', '!=', 'cancelado');
            })->count(),
            'veiculos' => EmprestimoGarantia::where('categoria', 'veiculo')->whereHas('emprestimo', function ($q) use ($operacoesIds) {
                if (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
                $q->where('status', '!=', 'cancelado');
            })->count(),
            'outros' => EmprestimoGarantia::where('categoria', 'outros')->whereHas('emprestimo', function ($q) use ($operacoesIds) {
                if (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                }
                $q->where('status', '!=', 'cancelado');
            })->count(),
        ];

        return view('garantias.index', compact('garantias', 'operacoes', 'stats'));
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

        // Verificar permissão (mesma lógica do index)
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        
        if (!empty($operacoesIds) && !in_array($garantia->emprestimo->operacao_id, $operacoesIds)) {
            abort(403, 'Você não tem permissão para visualizar esta garantia.');
        }

        return view('garantias.show', compact('garantia'));
    }

    /**
     * Adicionar garantia a um empréstimo
     */
    public function store(Request $request, int $emprestimoId): RedirectResponse
    {
        $emprestimo = Emprestimo::findOrFail($emprestimoId);

        // Verificar se é tipo empenho
        if (!$emprestimo->isEmpenho()) {
            return back()->with('error', 'Apenas empréstimos do tipo empenho podem ter garantias.');
        }

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível adicionar garantias a empréstimos finalizados.');
        }

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para adicionar garantias a este empréstimo.');
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
        $garantia = EmprestimoGarantia::with('emprestimo')->findOrFail($garantiaId);
        $emprestimo = $garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível editar garantias de empréstimos finalizados.');
        }

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para editar esta garantia.');
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
        $garantia = EmprestimoGarantia::with(['emprestimo', 'anexos'])->findOrFail($garantiaId);
        $emprestimo = $garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível excluir garantias de empréstimos finalizados.');
        }

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para excluir esta garantia.');
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
        $anexo = EmprestimoGarantiaAnexo::with('garantia.emprestimo')->findOrFail($anexoId);
        $emprestimo = $anexo->garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível excluir anexos de garantias de empréstimos finalizados.');
        }

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para excluir este anexo.');
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
        $garantia = EmprestimoGarantia::with('emprestimo')->findOrFail($garantiaId);
        $emprestimo = $garantia->emprestimo;

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Não é possível adicionar anexos a garantias de empréstimos finalizados.'], 403);
            }
            return back()->with('error', 'Não é possível adicionar anexos a garantias de empréstimos finalizados.');
        }

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Sem permissão'], 403);
            }
            return back()->with('error', 'Você não tem permissão para fazer upload nesta garantia.');
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
