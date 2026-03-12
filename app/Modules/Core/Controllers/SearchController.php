<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Emprestimo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Busca global inteligente
     */
    public function buscar(Request $request): JsonResponse
    {
        $termo = trim($request->input('q', ''));
        
        if (strlen($termo) < 2) {
            return response()->json([
                'results' => [],
                'total' => 0
            ]);
        }

        $user = auth()->user();
        $results = [];

        // Detectar tipo de busca
        $tipoBusca = $this->detectarTipoBusca($termo);

        // Buscar clientes
        if ($tipoBusca === 'cpf' || $tipoBusca === 'nome' || $tipoBusca === 'geral') {
            $clientes = $this->buscarClientes($termo, $user);
            foreach ($clientes as $cliente) {
                $results[] = [
                    'type' => 'cliente',
                    'id' => $cliente->id,
                    'title' => $cliente->nome,
                    'subtitle' => $cliente->cpf_formatado,
                    'url' => route('clientes.show', $cliente->id),
                    'icon' => 'bx-user',
                    'badge' => 'Cliente'
                ];
            }
        }

        // Buscar empréstimos (por ID ou nome do cliente)
        if ($tipoBusca === 'id' || $tipoBusca === 'nome' || $tipoBusca === 'geral') {
            $emprestimos = $this->buscarEmprestimos($termo, $user);
            foreach ($emprestimos as $emprestimo) {
                $results[] = [
                    'type' => 'emprestimo',
                    'id' => $emprestimo->id,
                    'title' => 'Empréstimo #' . $emprestimo->id,
                    'subtitle' => $emprestimo->cliente->nome . ' - R$ ' . number_format($emprestimo->valor_total, 2, ',', '.'),
                    'url' => route('emprestimos.show', $emprestimo->id),
                    'icon' => 'bx-money',
                    'badge' => ucfirst($emprestimo->status)
                ];
            }
        }

        // Buscar operações
        if ($tipoBusca === 'nome' || $tipoBusca === 'geral') {
            $operacoes = $this->buscarOperacoes($termo, $user);
            foreach ($operacoes as $operacao) {
                $results[] = [
                    'type' => 'operacao',
                    'id' => $operacao->id,
                    'title' => $operacao->nome,
                    'subtitle' => $operacao->codigo ?? 'Sem código',
                    'url' => route('operacoes.show', $operacao->id),
                    'icon' => 'bx-building',
                    'badge' => $operacao->ativo ? 'Ativa' : 'Inativa'
                ];
            }
        }

        // Buscar usuários (apenas para administradores)
        if ($user->hasRole('administrador') && ($tipoBusca === 'nome' || $tipoBusca === 'geral')) {
            $usuarios = $this->buscarUsuarios($termo);
            foreach ($usuarios as $usuario) {
                $results[] = [
                    'type' => 'usuario',
                    'id' => $usuario->id,
                    'title' => $usuario->name,
                    'subtitle' => $usuario->email,
                    'url' => route('usuarios.show', $usuario->id),
                    'icon' => 'bx-user-circle',
                    'badge' => $usuario->roles->first() ? ucfirst($usuario->roles->first()->name) : 'Sem papel'
                ];
            }
        }

        return response()->json([
            'results' => array_slice($results, 0, 10), // Limitar a 10 resultados
            'total' => count($results)
        ]);
    }

    /**
     * Detectar tipo de busca baseado no termo
     */
    private function detectarTipoBusca(string $termo): string
    {
        // Se contém apenas números e tem 11 dígitos ou mais, provavelmente é CPF
        $cpfLimpo = preg_replace('/[^0-9]/', '', $termo);
        if (strlen($cpfLimpo) >= 11) {
            return 'cpf';
        }

        // Se começa com # ou é apenas números pequenos, pode ser ID de empréstimo
        if (preg_match('/^#?\d{1,6}$/', $termo)) {
            return 'id';
        }

        // Se contém apenas números pequenos, pode ser ID ou nome
        if (preg_match('/^\d{1,3}$/', $termo)) {
            return 'geral';
        }

        // Caso contrário, busca geral (nome, etc)
        return 'nome';
    }

    /**
     * Buscar clientes
     */
    private function buscarClientes(string $termo, $user)
    {
        $query = Cliente::query();
        
        $cpfLimpo = preg_replace('/[^0-9]/', '', $termo);
        // Normalizar documento: sem zeros à esquerda, para encontrar tanto "058..." quanto "58..."
        $docSearch = $cpfLimpo !== '' ? ltrim($cpfLimpo, '0') : '';
        
        if (strlen($cpfLimpo) >= 3) {
            $query->where(function($q) use ($docSearch, $cpfLimpo, $termo) {
                if ($docSearch !== '') {
                    $q->where('documento', 'like', '%' . $docSearch . '%')
                      ->orWhere('documento', 'like', '%' . $cpfLimpo . '%');
                }
                $q->orWhere('nome', 'like', '%' . $termo . '%');
            });
        } else {
            $query->where('nome', 'like', '%' . $termo . '%');
        }

        // Busca global: filtro por empresa já vem do EmpresaScope do model Cliente.
        // Não exige vínculo em operation_clients para que todos os clientes da empresa apareçam.

        return $query->orderBy('nome')->limit(5)->get();
    }

    /**
     * Buscar empréstimos
     */
    private function buscarEmprestimos(string $termo, $user)
    {
        $query = Emprestimo::with(['cliente', 'operacao']);
        
        // Se é um número, buscar por ID
        if (preg_match('/^#?(\d+)$/', $termo, $matches)) {
            $id = (int) $matches[1];
            $query->where('id', $id);
        } else {
            // Buscar por nome do cliente
            $query->whereHas('cliente', function($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%");
            });
        }

        // Filtrar por operações do usuário (se não for admin)
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->orderBy('created_at', 'desc')->limit(5)->get();
    }

    /**
     * Buscar operações
     */
    private function buscarOperacoes(string $termo, $user)
    {
        $query = Operacao::query();
        
        $query->where(function($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('codigo', 'like', "%{$termo}%");
        });

        // Filtrar por operações do usuário (se não for admin)
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->where('ativo', true)->orderBy('nome')->limit(3)->get();
    }

    /**
     * Buscar usuários (apenas admin)
     */
    private function buscarUsuarios(string $termo)
    {
        return User::where(function($q) use ($termo) {
                $q->where('name', 'like', "%{$termo}%")
                  ->orWhere('email', 'like', "%{$termo}%");
            })
            ->with('roles')
            ->orderBy('name')
            ->limit(3)
            ->get();
    }
}
