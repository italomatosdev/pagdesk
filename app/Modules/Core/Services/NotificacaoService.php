<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Notificacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificacaoService
{
    /**
     * Criar nova notificação
     *
     * @param array $dados
     * @return Notificacao
     */
    public function criar(array $dados): Notificacao
    {
        return Notificacao::create([
            'user_id' => $dados['user_id'],
            'operacao_id' => $dados['operacao_id'] ?? null,
            'tipo' => $dados['tipo'],
            'titulo' => $dados['titulo'],
            'mensagem' => $dados['mensagem'],
            'dados' => $dados['dados'] ?? null,
            'url' => $dados['url'] ?? null,
        ]);
    }

    /**
     * Criar notificação para múltiplos usuários
     *
     * @param array $userIds
     * @param array $dados
     * @return void
     */
    public function criarParaMultiplos(array $userIds, array $dados): void
    {
        $notificacoes = [];
        $operacaoId = $dados['operacao_id'] ?? null;
        foreach ($userIds as $userId) {
            // Converter array de dados para JSON se for array
            $dadosJson = null;
            if (isset($dados['dados']) && is_array($dados['dados'])) {
                $dadosJson = json_encode($dados['dados']);
            } elseif (isset($dados['dados'])) {
                $dadosJson = $dados['dados'];
            }

            $notificacoes[] = [
                'user_id' => $userId,
                'operacao_id' => $operacaoId,
                'tipo' => $dados['tipo'],
                'titulo' => $dados['titulo'],
                'mensagem' => $dados['mensagem'],
                'dados' => $dadosJson,
                'url' => $dados['url'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Notificacao::insert($notificacoes);
    }

    /**
     * Criar notificação para todos os usuários com uma role específica
     *
     * @param string $role
     * @param array $dados
     * @return void
     */
    public function criarParaRole(string $role, array $dados): void
    {
        $users = User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })->pluck('id');

        if ($users->count() > 0) {
            $this->criarParaMultiplos($users->toArray(), $dados);
        }
    }

    /**
     * Criar notificação para usuários que tenham o papel indicado **nessa** operação (operacao_user.role).
     * Super Admin recebe sempre.
     *
     * @param string $role consultor|gestor|administrador
     * @param int $operacaoId
     * @param array $dados
     * @return void
     */
    public function criarParaRoleComOperacao(string $role, int $operacaoId, array $dados): void
    {
        $userIds = DB::table('operacao_user')
            ->where('operacao_id', $operacaoId)
            ->where('role', $role)
            ->pluck('user_id')
            ->toArray();

        $superAdminIds = User::where('is_super_admin', true)->pluck('id')->toArray();
        $userIds = array_unique(array_merge($userIds, $superAdminIds));

        if (!empty($userIds)) {
            $dados['operacao_id'] = $operacaoId;
            $this->criarParaMultiplos($userIds, $dados);
        }
    }

    /**
     * Criar notificação para gestores e administradores **da operação** (operacao_user.role in gestor, administrador).
     * Super Admin recebe sempre. Útil para notificar "gestores da operação" sem duplicar.
     *
     * @param int $operacaoId
     * @param array $dados
     * @param array $excluirUserIds IDs de usuários a excluir (ex.: quem disparou a ação)
     * @return void
     */
    public function criarParaGestoresDaOperacao(int $operacaoId, array $dados, array $excluirUserIds = []): void
    {
        $userIds = DB::table('operacao_user')
            ->where('operacao_id', $operacaoId)
            ->whereIn('role', ['gestor', 'administrador'])
            ->pluck('user_id')
            ->toArray();

        $superAdminIds = User::where('is_super_admin', true)->pluck('id')->toArray();
        $userIds = array_unique(array_merge($userIds, $superAdminIds));

        if (!empty($excluirUserIds)) {
            $userIds = array_values(array_diff($userIds, $excluirUserIds));
        }

        if (!empty($userIds)) {
            $dados['operacao_id'] = $operacaoId;
            $this->criarParaMultiplos($userIds, $dados);
        }
    }

    /**
     * Query base de notificações visíveis ao usuário (filtro por operação).
     * Super Admin vê todas; demais só notificações sem operação ou da sua operação.
     */
    protected function queryNotificacoesDoUsuario(int $userId): Builder
    {
        $query = Notificacao::where('user_id', $userId);
        $user = User::find($userId);
        if ($user && !$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            $query->where(function (Builder $q) use ($operacoesIds) {
                $q->whereNull('operacao_id');
                if (!empty($operacoesIds)) {
                    $q->orWhereIn('operacao_id', $operacoesIds);
                }
            });
        }
        return $query;
    }

    /**
     * Listar notificações do usuário
     *
     * @param int $userId
     * @param int $limit
     * @param bool $apenasNaoLidas
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listar(int $userId, int $limit = 20, bool $apenasNaoLidas = false)
    {
        $query = $this->queryNotificacoesDoUsuario($userId)
            ->orderBy('created_at', 'desc');

        if ($apenasNaoLidas) {
            $query->where('lida', false);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Listar notificações do usuário com paginação
     *
     * @param int $userId
     * @param int $perPage
     * @param string|null $filtro (todas, lidas, nao_lidas)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarComPaginacao(int $userId, int $perPage = 20, ?string $filtro = null)
    {
        $query = $this->queryNotificacoesDoUsuario($userId)
            ->orderBy('created_at', 'desc');

        if ($filtro === 'nao_lidas') {
            $query->where('lida', false);
        } elseif ($filtro === 'lidas') {
            $query->where('lida', true);
        }

        return $query->paginate($perPage);
    }

    /**
     * Contar notificações não lidas
     *
     * @param int $userId
     * @return int
     */
    public function contarNaoLidas(int $userId): int
    {
        return $this->queryNotificacoesDoUsuario($userId)
            ->where('lida', false)
            ->count();
    }

    /**
     * Marcar notificação como lida
     *
     * @param int $notificacaoId
     * @param int $userId
     * @return bool
     */
    public function marcarComoLida(int $notificacaoId, int $userId): bool
    {
        $notificacao = $this->queryNotificacoesDoUsuario($userId)
            ->where('id', $notificacaoId)
            ->first();

        if ($notificacao) {
            $notificacao->marcarComoLida();
            return true;
        }

        return false;
    }

    /**
     * Marcar todas as notificações como lidas
     *
     * @param int $userId
     * @return int
     */
    public function marcarTodasComoLidas(int $userId): int
    {
        return $this->queryNotificacoesDoUsuario($userId)
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now(),
            ]);
    }

    /**
     * Excluir notificações antigas (mais de 30 dias)
     *
     * @return int
     */
    public function limparAntigas(): int
    {
        return Notificacao::where('created_at', '<', now()->subDays(30))
            ->where('lida', true)
            ->delete();
    }
}
