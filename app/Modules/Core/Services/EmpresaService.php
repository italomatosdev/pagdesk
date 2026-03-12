<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmpresaService
{
    /**
     * Criar nova empresa
     */
    public function criar(array $dados): Empresa
    {
        return DB::transaction(function () use ($dados) {
            // Configurações padrão
            $configuracoes = $dados['configuracoes'] ?? [
                'workflow' => [
                    'requer_aprovacao' => true,
                    'requer_liberacao' => true,
                    'aprovacao_automatica_valor_max' => 0,
                ],
                'operacoes' => [
                    'permite_multiplas_operacoes' => true,
                ],
            ];

            $empresa = Empresa::create([
                'nome' => $dados['nome'],
                'razao_social' => $dados['razao_social'] ?? $dados['nome'],
                'cnpj' => $dados['cnpj'] ?? null,
                'email_contato' => $dados['email_contato'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
                'status' => $dados['status'] ?? 'ativa',
                'plano' => $dados['plano'] ?? 'basico',
                'data_ativacao' => $dados['data_ativacao'] ?? now(),
                'data_expiracao' => $dados['data_expiracao'] ?? null,
                'configuracoes' => $configuracoes,
            ]);

            return $empresa;
        });
    }

    /**
     * Atualizar empresa
     */
    public function atualizar(int $id, array $dados): Empresa
    {
        $empresa = Empresa::findOrFail($id);

        return DB::transaction(function () use ($empresa, $dados) {
            $empresa->update($dados);
            return $empresa->fresh();
        });
    }

    /**
     * Criar empresa com setup inicial
     * Cria empresa, primeira operação e primeiro usuário administrador
     */
    public function criarComSetup(array $dadosEmpresa, array $dadosOperacao, array $dadosUsuario): array
    {
        return DB::transaction(function () use ($dadosEmpresa, $dadosOperacao, $dadosUsuario) {
            // Criar empresa
            $empresa = $this->criar($dadosEmpresa);

            // Criar primeira operação
            $operacao = $empresa->operacoes()->create([
                'nome' => $dadosOperacao['nome'] ?? 'Operação Principal',
                'codigo' => $dadosOperacao['codigo'] ?? null,
                'descricao' => $dadosOperacao['descricao'] ?? null,
                'ativo' => true,
            ]);

            // Criar primeiro usuário (administrador)
            $usuario = User::create([
                'name' => $dadosUsuario['name'],
                'email' => $dadosUsuario['email'],
                'password' => Hash::make($dadosUsuario['password']),
                'empresa_id' => $empresa->id,
                'operacao_id' => $operacao->id,
                'is_super_admin' => false,
            ]);

            // Atribuir papel de administrador
            $roleAdmin = \App\Modules\Core\Models\Role::where('name', 'administrador')->first();
            if ($roleAdmin) {
                $usuario->roles()->attach($roleAdmin->id);
            }

            // Vincular usuário à operação
            $usuario->operacoes()->attach($operacao->id);

            return [
                'empresa' => $empresa,
                'operacao' => $operacao,
                'usuario' => $usuario,
            ];
        });
    }

    /**
     * Suspender empresa
     */
    public function suspender(int $id): Empresa
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->update(['status' => 'suspensa']);
        return $empresa->fresh();
    }

    /**
     * Ativar empresa
     */
    public function ativar(int $id): Empresa
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->update([
            'status' => 'ativa',
            'data_ativacao' => now(),
        ]);
        return $empresa->fresh();
    }

    /**
     * Cancelar empresa (soft delete)
     */
    public function cancelar(int $id): Empresa
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->update(['status' => 'cancelada']);
        $empresa->delete();
        return $empresa;
    }

    /**
     * Obter estatísticas da empresa
     */
    public function obterEstatisticas(int $id): array
    {
        $empresa = Empresa::findOrFail($id);

        return [
            'operacoes' => $empresa->operacoes()->count(),
            'usuarios' => $empresa->usuarios()->count(),
            'clientes' => Cliente::where('empresa_id', $empresa->id)
                ->orWhereHas('empresasVinculadas', function ($q) use ($empresa) {
                    $q->where('empresa_id', $empresa->id);
                })
                ->count(),
            'emprestimos_ativos' => $empresa->emprestimos()->where('status', 'ativo')->count(),
            'emprestimos_total' => $empresa->emprestimos()->count(),
        ];
    }
}
