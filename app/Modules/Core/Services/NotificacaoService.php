<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Notificacao;
use App\Models\User;
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
     * Listar notificações do usuário
     *
     * @param int $userId
     * @param int $limit
     * @param bool $apenasNaoLidas
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listar(int $userId, int $limit = 20, bool $apenasNaoLidas = false)
    {
        $query = Notificacao::where('user_id', $userId)
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
        $query = Notificacao::where('user_id', $userId)
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
        return Notificacao::where('user_id', $userId)
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
        $notificacao = Notificacao::where('id', $notificacaoId)
            ->where('user_id', $userId)
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
        return Notificacao::where('user_id', $userId)
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
