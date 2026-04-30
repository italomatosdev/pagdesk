<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\ClienteDadosEmpresa;
use App\Modules\Core\Models\ClientDocument;
use App\Modules\Core\Models\EmpresaClienteVinculo;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Core\Models\OperationClient;
use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClienteService
{
    use Auditable;

    /**
     * Cadastrar novo cliente
     *
     * @param array $dados
     * @return Cliente
     * @throws ValidationException
     */
    public function cadastrar(array $dados): Cliente
    {
        // Determinar tipo de pessoa (padrão: física)
        $tipoPessoa = $dados['tipo_pessoa'] ?? 'fisica';
        
        // Limpar documento (remover formatação)
        $documento = preg_replace('/[^0-9]/', '', $dados['documento'] ?? '');

        // Validar documento conforme tipo
        if ($tipoPessoa === 'fisica') {
            // Validar CPF
            if (strlen($documento) !== 11) {
                throw ValidationException::withMessages([
                    'documento' => 'CPF deve conter 11 dígitos.'
                ]);
            }
            
            // Validar CPF (algoritmo)
            if (!\App\Helpers\ValidacaoDocumento::validarCpf($documento)) {
                throw ValidationException::withMessages([
                    'documento' => 'CPF inválido.'
                ]);
            }
        } else {
            // Validar CNPJ
            if (strlen($documento) !== 14) {
                throw ValidationException::withMessages([
                    'documento' => 'CNPJ deve conter 14 dígitos.'
                ]);
            }
            
            // Validar CNPJ (algoritmo)
            if (!\App\Helpers\ValidacaoDocumento::validarCnpj($documento)) {
                throw ValidationException::withMessages([
                    'documento' => 'CNPJ inválido.'
                ]);
            }
        }

        // Verificar se documento já existe
        $clienteExistente = Cliente::buscarPorDocumento($documento);
        
        if ($clienteExistente) {
            $tipoDoc = $tipoPessoa === 'fisica' ? 'CPF' : 'CNPJ';
            throw ValidationException::withMessages([
                'documento' => "Cliente já cadastrado com este {$tipoDoc}."
            ]);
        }

        $dados['tipo_pessoa'] = $tipoPessoa;
        $dados['documento'] = $documento;
        
        // Se for pessoa jurídica, remover data de nascimento e validar responsável
        if ($tipoPessoa === 'juridica') {
            unset($dados['data_nascimento']);
            
            // Validar CPF do responsável se informado
            if (!empty($dados['responsavel_cpf'])) {
                $responsavelCpf = preg_replace('/[^0-9]/', '', $dados['responsavel_cpf']);
                
                if (strlen($responsavelCpf) !== 11) {
                    throw ValidationException::withMessages([
                        'responsavel_cpf' => 'CPF do responsável deve conter 11 dígitos.'
                    ]);
                }
                
                if (!\App\Helpers\ValidacaoDocumento::validarCpf($responsavelCpf)) {
                    throw ValidationException::withMessages([
                        'responsavel_cpf' => 'CPF do responsável inválido.'
                    ]);
                }
                
                $dados['responsavel_cpf'] = $responsavelCpf;
            }
        } else {
            // Se for pessoa física, remover campos do responsável
            unset($dados['responsavel_nome'], $dados['responsavel_cpf'], $dados['responsavel_rg'], $dados['responsavel_cnh'], $dados['responsavel_cargo']);
        }

        // Adicionar empresa_id do usuário autenticado
        if (!isset($dados['empresa_id']) && auth()->check() && !auth()->user()->isSuperAdmin()) {
            $dados['empresa_id'] = auth()->user()->empresa_id;
        }

        return DB::transaction(function () use ($dados) {
            try {
                // Remover documentos dos dados antes de criar o cliente
                $documentos = $dados['documentos'] ?? null;
                unset($dados['documentos']);
                $operacaoIdDocumentos = $dados['operacao_id_documentos'] ?? null;
                unset($dados['operacao_id_documentos']);
                
                $cliente = Cliente::create($dados);
                
                $tipoDoc = $cliente->isPessoaFisica() ? 'CPF' : 'CNPJ';
                \Log::info("Cliente criado com sucesso. ID: {$cliente->id}, {$tipoDoc}: {$cliente->documento}");

                // Processar uploads de documentos se fornecidos
                if ($documentos) {
                    \Log::info("Processando documentos para cliente ID: {$cliente->id}");
                    $this->processarDocumentos($cliente->id, $documentos, $operacaoIdDocumentos);
                    \Log::info("Documentos processados com sucesso para cliente ID: {$cliente->id}");
                } else {
                    \Log::warning("Nenhum documento fornecido para cliente ID: {$cliente->id}");
                }

                // Auditoria
                self::auditar('criar_cliente', $cliente, null, $cliente->toArray());

                return $cliente;
            } catch (\Exception $e) {
                \Log::error("Erro ao cadastrar cliente: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'dados' => array_merge($dados, ['documentos' => 'removido para log']),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Buscar cliente por documento (CPF ou CNPJ) na empresa atual (com escopo)
     *
     * @param string $documento
     * @return Cliente|null
     */
    public function buscarPorDocumento(string $documento): ?Cliente
    {
        // Remove formatação do documento
        $documentoLimpo = preg_replace('/[^0-9]/', '', $documento);
        
        // Busca COM escopo de empresa (na empresa atual)
        return Cliente::where('documento', $documentoLimpo)->first();
    }

    /**
     * Buscar cliente por CPF (mantido para compatibilidade)
     * @deprecated Use buscarPorDocumento() ao invés disso
     */
    public function buscarPorCpf(string $cpf): ?Cliente
    {
        return $this->buscarPorDocumento($cpf);
    }

    /**
     * Vincular cliente a uma operação
     *
     * @param int $clienteId
     * @param int $operacaoId
     * @param float $limiteCredito
     * @param int|null $consultorId
     * @param string|null $notasInternas
     * @return OperationClient
     */
    public function vincularOperacao(
        int $clienteId,
        int $operacaoId,
        float $limiteCredito = 0,
        ?int $consultorId = null,
        ?string $notasInternas = null
    ): OperationClient {
        // Inclui soft-deletes: índice único (operacao_id, cliente_id) ainda bloqueia INSERT se só existir linha "apagada".
        $vinculoExistente = OperationClient::withTrashed()
            ->where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->first();

        if ($vinculoExistente) {
            if ($vinculoExistente->trashed()) {
                $vinculoExistente->restore();
            }
            // Atualizar vínculo existente
            $vinculoExistente->update([
                'limite_credito' => $limiteCredito,
                'consultor_id' => $consultorId,
                'notas_internas' => $notasInternas,
                'status' => 'ativo',
            ]);

            // Auditoria
            self::auditar('atualizar_vinculo_cliente_operacao', $vinculoExistente);

            return $vinculoExistente;
        }

        // Criar novo vínculo
        $vinculo = OperationClient::create([
            'cliente_id' => $clienteId,
            'operacao_id' => $operacaoId,
            'limite_credito' => $limiteCredito,
            'consultor_id' => $consultorId,
            'notas_internas' => $notasInternas,
            'status' => 'ativo',
        ]);

        // Auditoria
        self::auditar('criar_vinculo_cliente_operacao', $vinculo);

        return $vinculo;
    }

    /**
     * Atualizar limite de crédito do cliente em uma operação
     *
     * @param int $vinculoId
     * @param float $novoLimite
     * @param string|null $observacoes
     * @return OperationClient
     */
    public function atualizarLimiteCredito(
        int $vinculoId,
        float $novoLimite,
        ?string $observacoes = null
    ): OperationClient {
        $vinculo = OperationClient::findOrFail($vinculoId);
        
        $oldValue = $vinculo->limite_credito;
        
        $vinculo->update([
            'limite_credito' => $novoLimite,
        ]);

        // Auditoria
        self::auditar(
            'alterar_limite_credito',
            $vinculo,
            ['limite_credito' => $oldValue],
            ['limite_credito' => $novoLimite],
            $observacoes
        );

        return $vinculo->fresh();
    }

    /**
     * Atualizar dados do cliente
     *
     * @param int $clienteId
     * @param array $dados
     * @return Cliente
     */
    public function atualizar(int $clienteId, array $dados): Cliente
    {
        return DB::transaction(function () use ($clienteId, $dados) {
            // Buscar cliente globalmente (sem escopo de empresa) para permitir edição de clientes de outras empresas
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->findOrFail($clienteId);
            
            $oldValues = $cliente->toArray();
            
            // Não permitir alterar tipo_pessoa e documento
            unset($dados['tipo_pessoa'], $dados['documento']);
            
            // Se for pessoa jurídica, remover data de nascimento e validar responsável
            if ($cliente->isPessoaJuridica()) {
                unset($dados['data_nascimento']);
                
                // Validar CPF do responsável se informado
                if (!empty($dados['responsavel_cpf'])) {
                    $responsavelCpf = preg_replace('/[^0-9]/', '', $dados['responsavel_cpf']);
                    
                    if (strlen($responsavelCpf) !== 11) {
                        throw ValidationException::withMessages([
                            'responsavel_cpf' => 'CPF do responsável deve conter 11 dígitos.'
                        ]);
                    }
                    
                    if (!\App\Helpers\ValidacaoDocumento::validarCpf($responsavelCpf)) {
                        throw ValidationException::withMessages([
                            'responsavel_cpf' => 'CPF do responsável inválido.'
                        ]);
                    }
                    
                    $dados['responsavel_cpf'] = $responsavelCpf;
                }
            } else {
                // Se for pessoa física, remover campos do responsável
                unset($dados['responsavel_nome'], $dados['responsavel_cpf'], $dados['responsavel_rg'], $dados['responsavel_cnh'], $dados['responsavel_cargo']);
            }
            
            // Separar documentos dos dados do cliente
            $documentos = $dados['documentos'] ?? null;
            unset($dados['documentos']);
            
            // Se a empresa atual é a criadora do cliente, atualiza os dados originais
            // Caso contrário, cria/atualiza um override para a empresa atual
            $empresaId = auth()->user()->empresa_id;
            if ($cliente->isEmpresaCriadora($empresaId)) {
                $cliente->update($dados);
            } else {
                // Remover campos vazios/null (não sobrescrever com null se não foi informado)
                $dados = array_filter($dados, function ($value) {
                    return $value !== null && $value !== '';
                });
                
                // Buscar ou criar registro de override
                $dadosEmpresa = \App\Modules\Core\Models\ClienteDadosEmpresa::firstOrNew([
                    'cliente_id' => $clienteId,
                    'empresa_id' => $empresaId,
                ]);
                
                // Atualizar apenas os campos informados
                $dadosEmpresa->fill($dados);
                $dadosEmpresa->save();
                
                // Limpar cache de dados da empresa no model
                $cliente->cachedDadosEmpresa = null;
            }

            // Processar uploads de documentos se fornecidos
            if ($documentos) {
                $this->processarDocumentosAtualizacao($clienteId, $documentos);
            }

            // Auditoria
            self::auditar(
                'atualizar_cliente',
                $cliente,
                $oldValues,
                $cliente->toArray()
            );

            // Recarregar cliente sem escopo para evitar problemas com EmpresaScope
            return Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->findOrFail($clienteId);
        });
    }

    /**
     * Atualizar dados do cliente para uma empresa específica (override)
     * Usado quando uma empresa que não criou o cliente quer editar os dados
     *
     * @param int $clienteId
     * @param int $empresaId
     * @param array $dados
     * @return Cliente
     */
    public function atualizarDadosEmpresa(int $clienteId, int $empresaId, array $dados): Cliente
    {
        return DB::transaction(function () use ($clienteId, $empresaId, $dados) {
            // Buscar cliente (sem escopo, pois pode ser de outra empresa)
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->findOrFail($clienteId);
            
            // Não permitir alterar tipo_pessoa e documento (sempre globais)
            unset($dados['tipo_pessoa'], $dados['documento'], $dados['empresa_id']);
            
            // Se for pessoa jurídica, remover data de nascimento e validar responsável
            if ($cliente->isPessoaJuridica()) {
                unset($dados['data_nascimento']);
                
                // Validar CPF do responsável se informado
                if (!empty($dados['responsavel_cpf'])) {
                    $responsavelCpf = preg_replace('/[^0-9]/', '', $dados['responsavel_cpf']);
                    
                    if (strlen($responsavelCpf) !== 11) {
                        throw ValidationException::withMessages([
                            'responsavel_cpf' => 'CPF do responsável deve conter 11 dígitos.'
                        ]);
                    }
                    
                    if (!\App\Helpers\ValidacaoDocumento::validarCpf($responsavelCpf)) {
                        throw ValidationException::withMessages([
                            'responsavel_cpf' => 'CPF do responsável inválido.'
                        ]);
                    }
                    
                    $dados['responsavel_cpf'] = $responsavelCpf;
                }
            } else {
                // Se for pessoa física, remover campos do responsável
                unset($dados['responsavel_nome'], $dados['responsavel_cpf'], $dados['responsavel_rg'], $dados['responsavel_cnh'], $dados['responsavel_cargo']);
            }
            
            // Separar documentos dos dados (documentos sempre são globais, não por empresa)
            $documentos = $dados['documentos'] ?? null;
            unset($dados['documentos']);
            
            // Remover campos vazios/null (não sobrescrever com null se não foi informado)
            $dados = array_filter($dados, function ($value) {
                return $value !== null && $value !== '';
            });
            
            // Buscar ou criar registro de override
            $dadosEmpresa = ClienteDadosEmpresa::firstOrNew([
                'cliente_id' => $clienteId,
                'empresa_id' => $empresaId,
            ]);
            
            $oldValues = $dadosEmpresa->exists ? $dadosEmpresa->toArray() : [];
            
            // Atualizar apenas os campos informados
            $dadosEmpresa->fill($dados);
            $dadosEmpresa->save();
            
            // Processar uploads de documentos se fornecidos (documentos são globais)
            if ($documentos) {
                $this->processarDocumentosAtualizacao($clienteId, $documentos);
            }
            
            // Auditoria
            self::auditar(
                'atualizar_dados_empresa_cliente',
                $dadosEmpresa,
                $oldValues,
                $dadosEmpresa->toArray(),
                "Cliente ID: {$clienteId}, Empresa ID: {$empresaId}"
            );
            
            // Limpar cache de dados da empresa no model
            $cliente->cachedDadosEmpresa = null;
            
            // Recarregar cliente sem escopo para evitar problemas com EmpresaScope
            return Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->findOrFail($clienteId);
        });
    }

    /**
     * Processar uploads de documentos do cliente (criação)
     * Documentos são sempre originais (empresa_id = null) na criação
     *
     * @param int $clienteId
     * @param array $documentos Array com 'documento_cliente', 'selfie_documento', 'anexos'
     * @param int|null $operacaoId Contexto da operação (ex.: cadastro por link público)
     * @return void
     */
    protected function processarDocumentos(int $clienteId, array $documentos, ?int $operacaoId = null): void
    {
        try {
            // Na criação, documentos são sempre originais (empresa_id = null)
            $empresaId = null;
            
            // Processar documento do cliente (quando enviado; obrigatoriedade é por operação, validada no controller)
            if (!empty($documentos['documento_cliente'])) {
                $docFile = $documentos['documento_cliente'];
                if ($docFile->isValid()) {
                    $path = $docFile->store('clientes/documentos', 'public');
                    ClientDocument::create([
                        'cliente_id' => $clienteId,
                        'empresa_id' => $empresaId,
                        'operacao_id' => $operacaoId,
                        'categoria' => 'documento',
                        'tipo' => 'documento_identidade',
                        'arquivo_path' => $path,
                        'nome_arquivo' => $docFile->getClientOriginalName(),
                    ]);
                } else {
                    \Log::error("Erro no upload do documento do cliente ID {$clienteId}: " . $docFile->getError());
                    throw new \Exception("Erro ao fazer upload do documento do cliente.");
                }
            }

            // Processar selfie com documento (quando enviada; obrigatoriedade é por operação, validada no controller)
            if (!empty($documentos['selfie_documento'])) {
                $selfieFile = $documentos['selfie_documento'];
                if ($selfieFile->isValid()) {
                    $path = $selfieFile->store('clientes/selfies', 'public');
                    ClientDocument::create([
                        'cliente_id' => $clienteId,
                        'empresa_id' => $empresaId,
                        'operacao_id' => $operacaoId,
                        'categoria' => 'selfie',
                        'tipo' => 'selfie_documento',
                        'arquivo_path' => $path,
                        'nome_arquivo' => $selfieFile->getClientOriginalName(),
                    ]);
                } else {
                    \Log::error("Erro no upload da selfie do cliente ID {$clienteId}: " . $selfieFile->getError());
                    throw new \Exception("Erro ao fazer upload da selfie com documento.");
                }
            }

            // Processar anexos adicionais (opcionais, múltiplos)
            if (isset($documentos['anexos']) && is_array($documentos['anexos'])) {
                foreach ($documentos['anexos'] as $anexo) {
                    if ($anexo && $anexo->isValid()) {
                        $path = $anexo->store('clientes/anexos', 'public');
                        ClientDocument::create([
                            'cliente_id' => $clienteId,
                            'empresa_id' => $empresaId, // null = documento original
                            'operacao_id' => $operacaoId,
                            'categoria' => 'anexo',
                            'tipo' => 'anexo',
                            'arquivo_path' => $path,
                            'nome_arquivo' => $anexo->getClientOriginalName(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Erro ao processar documentos do cliente ID {$clienteId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Grava documentos do formulário com contexto de operação (ex.: link público quando o CPF já existe e está entrando em outra operação).
     *
     * @param  array<string, mixed>  $documentos  documento_cliente, selfie_documento, anexos
     */
    public function processarDocumentosParaOperacao(int $clienteId, array $documentos, int $operacaoId): void
    {
        $this->processarDocumentos($clienteId, $documentos, $operacaoId);
    }

    /**
     * Processar uploads de documentos do cliente (atualização)
     * Substitui documentos existentes ou adiciona novos
     * Considera se a empresa atual é a criadora ou não
     *
     * @param int $clienteId
     * @param array $documentos Array com 'documento_cliente', 'selfie_documento', 'anexos'
     * @return void
     */
    protected function processarDocumentosAtualizacao(int $clienteId, array $documentos): void
    {
        // Buscar cliente globalmente (sem escopo de empresa) para permitir processamento de documentos
        // de clientes de outras empresas
        $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->findOrFail($clienteId);
        $empresaId = auth()->user()->empresa_id;
        $isEmpresaCriadora = $cliente->isEmpresaCriadora($empresaId);
        
        // Se é a empresa criadora, trabalha com documentos originais (empresa_id = null)
        // Se não é, trabalha com documentos específicos da empresa (empresa_id = empresa_atual)
        $empresaIdDocumento = $isEmpresaCriadora ? null : $empresaId;
        
        // Processar documento do cliente (substituir se existir)
        if (isset($documentos['documento_cliente']) && $documentos['documento_cliente']->isValid()) {
            // Buscar documento existente (considerando empresa_id)
            $documentoExistente = ClientDocument::where('cliente_id', $clienteId)
                ->where('categoria', 'documento')
                ->where('empresa_id', $empresaIdDocumento)
                ->first();

            // Deletar arquivo antigo se existir
            if ($documentoExistente && $documentoExistente->arquivo_path) {
                Storage::disk('public')->delete($documentoExistente->arquivo_path);
            }

            // Salvar novo documento
            $path = $documentos['documento_cliente']->store('clientes/documentos', 'public');
            
            if ($documentoExistente) {
                // Atualizar documento existente
                $documentoExistente->update([
                    'arquivo_path' => $path,
                    'nome_arquivo' => $documentos['documento_cliente']->getClientOriginalName(),
                ]);
            } else {
                // Criar novo documento
                ClientDocument::create([
                    'cliente_id' => $clienteId,
                    'empresa_id' => $empresaIdDocumento,
                    'categoria' => 'documento',
                    'tipo' => 'documento_identidade',
                    'arquivo_path' => $path,
                    'nome_arquivo' => $documentos['documento_cliente']->getClientOriginalName(),
                ]);
            }
        }

        // Processar selfie com documento (substituir se existir)
        if (isset($documentos['selfie_documento']) && $documentos['selfie_documento']->isValid()) {
            // Buscar selfie existente (considerando empresa_id)
            $selfieExistente = ClientDocument::where('cliente_id', $clienteId)
                ->where('categoria', 'selfie')
                ->where('empresa_id', $empresaIdDocumento)
                ->first();

            // Deletar arquivo antigo se existir
            if ($selfieExistente && $selfieExistente->arquivo_path) {
                Storage::disk('public')->delete($selfieExistente->arquivo_path);
            }

            // Salvar nova selfie
            $path = $documentos['selfie_documento']->store('clientes/selfies', 'public');
            
            if ($selfieExistente) {
                // Atualizar selfie existente
                $selfieExistente->update([
                    'arquivo_path' => $path,
                    'nome_arquivo' => $documentos['selfie_documento']->getClientOriginalName(),
                ]);
            } else {
                // Criar nova selfie
                ClientDocument::create([
                    'cliente_id' => $clienteId,
                    'empresa_id' => $empresaIdDocumento,
                    'categoria' => 'selfie',
                    'tipo' => 'selfie_documento',
                    'arquivo_path' => $path,
                    'nome_arquivo' => $documentos['selfie_documento']->getClientOriginalName(),
                ]);
            }
        }

        // Processar anexos adicionais (sempre adiciona novos, não substitui)
        if (isset($documentos['anexos']) && is_array($documentos['anexos'])) {
            foreach ($documentos['anexos'] as $anexo) {
                if ($anexo && $anexo->isValid()) {
                    $path = $anexo->store('clientes/anexos', 'public');
                    ClientDocument::create([
                        'cliente_id' => $clienteId,
                        'empresa_id' => $empresaIdDocumento,
                        'categoria' => 'anexo',
                        'tipo' => 'anexo',
                        'arquivo_path' => $path,
                        'nome_arquivo' => $anexo->getClientOriginalName(),
                    ]);
                }
            }
        }
    }

    /**
     * Vincular cliente a uma empresa
     * Cria um vínculo para que o cliente apareça na lista da empresa
     *
     * @param int $clienteId
     * @param int $empresaId
     * @param int|null $vinculadoPor ID do usuário que está fazendo o vínculo
     * @return EmpresaClienteVinculo
     */
    public function vincularClienteEmpresa(int $clienteId, int $empresaId, ?int $vinculadoPor = null): EmpresaClienteVinculo
    {
        return DB::transaction(function () use ($clienteId, $empresaId, $vinculadoPor) {
            // Verificar se o cliente existe
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->findOrFail($clienteId);
            
            // Verificar se já existe vínculo
            $vinculoExistente = EmpresaClienteVinculo::where('empresa_id', $empresaId)
                ->where('cliente_id', $clienteId)
                ->first();
            
            if ($vinculoExistente) {
                // Se já existe e foi deletado (soft delete), restaurar
                if ($vinculoExistente->trashed()) {
                    $vinculoExistente->restore();
                    $vinculoExistente->update([
                        'vinculado_por' => $vinculadoPor ?? auth()->id(),
                    ]);
                    return $vinculoExistente->fresh();
                }
                // Se já existe e está ativo, retornar
                return $vinculoExistente;
            }
            
            // Criar novo vínculo
            $vinculo = EmpresaClienteVinculo::create([
                'empresa_id' => $empresaId,
                'cliente_id' => $clienteId,
                'vinculado_por' => $vinculadoPor ?? auth()->id(),
            ]);
            
            // Auditoria
            self::auditar(
                'vincular_cliente_empresa',
                $vinculo,
                [],
                $vinculo->toArray(),
                "Cliente ID: {$clienteId}, Empresa ID: {$empresaId}"
            );
            
            return $vinculo;
        });
    }

    /**
     * Remove o vínculo cliente ↔ operação (operation_clients), desde que não exista empréstimo
     * naquela operação (qualquer status; exclui apenas soft-deletados).
     * Remove também a ficha em operacao_dados_clientes e documentos escopados à operação.
     *
     * @throws ValidationException
     */
    public function desvincularClienteOperacao(int $clienteId, int $operacaoId): void
    {
        DB::transaction(function () use ($clienteId, $operacaoId) {
            $vinculo = OperationClient::where('cliente_id', $clienteId)
                ->where('operacao_id', $operacaoId)
                ->first();

            if (! $vinculo) {
                throw ValidationException::withMessages([
                    'operacao_id' => 'Cliente não está vinculado a esta operação.',
                ]);
            }

            if (Emprestimo::where('cliente_id', $clienteId)->where('operacao_id', $operacaoId)->exists()) {
                throw ValidationException::withMessages([
                    'operacao_id' => 'Não é possível remover o vínculo: já existe empréstimo registrado nesta operação (incluindo não ativos).',
                ]);
            }

            $oldValues = $vinculo->toArray();

            OperacaoDadosCliente::where('cliente_id', $clienteId)
                ->where('operacao_id', $operacaoId)
                ->delete();

            ClientDocument::where('cliente_id', $clienteId)
                ->where('operacao_id', $operacaoId)
                ->get()
                ->each(fn (ClientDocument $doc) => $doc->delete());

            $vinculo->delete();

            self::auditar(
                'desvincular_cliente_operacao',
                $vinculo,
                $oldValues,
                [],
                "Cliente ID: {$clienteId}, operação ID: {$operacaoId}"
            );
        });
    }

    /**
     * Desvincular cliente de uma empresa
     *
     * @param int $clienteId
     * @param int $empresaId
     * @return bool
     */
    public function desvincularClienteEmpresa(int $clienteId, int $empresaId): bool
    {
        return DB::transaction(function () use ($clienteId, $empresaId) {
            $vinculo = EmpresaClienteVinculo::where('empresa_id', $empresaId)
                ->where('cliente_id', $clienteId)
                ->first();
            
            if (!$vinculo) {
                return false;
            }
            
            $oldValues = $vinculo->toArray();
            
            // Soft delete
            $vinculo->delete();
            
            // Auditoria
            self::auditar(
                'desvincular_cliente_empresa',
                $vinculo,
                $oldValues,
                [],
                "Cliente ID: {$clienteId}, Empresa ID: {$empresaId}"
            );
            
            return true;
        });
    }

    /**
     * IDs de operações da empresa (inclui inativas e soft-deleted) para reuso de documentos:
     * anexos antigos podem apontar para operação apagada e ainda assim pertencer à mesma empresa.
     */
    private function idsOperacoesDaEmpresaParaReusoDocumentos(int $empresaId): \Illuminate\Support\Collection
    {
        return Operacao::withoutGlobalScope(EmpresaScope::class)
            ->withTrashed()
            ->where('empresa_id', $empresaId)
            ->pluck('id');
    }

    /**
     * Resolve caminho gravado em `client_documents.arquivo_path` para o disco `public` (storage/app/public).
     */
    private function caminhoRelativoExistenteNoDiscoPublico(?string $arquivoPath): ?string
    {
        if ($arquivoPath === null || $arquivoPath === '') {
            return null;
        }
        $normalized = str_replace('\\', '/', trim($arquivoPath));
        $normalized = ltrim($normalized, '/');
        $candidates = array_values(array_unique(array_filter([
            $normalized,
            preg_replace('#^storage/#', '', $normalized),
            preg_replace('#^public/storage/#', '', $normalized),
        ], static fn ($p) => $p !== null && $p !== '')));

        foreach ($candidates as $rel) {
            if (Storage::disk('public')->exists($rel)) {
                return $rel;
            }
        }

        return null;
    }

    /**
     * Indica se já existe registro de documento/selfie reutilizável (mesma empresa ou legado sem operacao_id).
     * Usado só para relaxar validação de upload no cadastro — não exige arquivo no disco (path legado/S3/etc.).
     */
    public function possuiDocumentoReutilizavelNaEmpresa(int $clienteId, int $empresaId, string $categoria): bool
    {
        if (! in_array($categoria, ['documento', 'selfie'], true)) {
            return false;
        }

        $operacaoIds = $this->idsOperacoesDaEmpresaParaReusoDocumentos($empresaId);

        return ClientDocument::query()
            ->where('cliente_id', $clienteId)
            ->where('categoria', $categoria)
            ->whereNotNull('arquivo_path')
            ->where('arquivo_path', '!=', '')
            ->where(function ($q) use ($operacaoIds) {
                $q->whereNull('operacao_id');
                if ($operacaoIds->isNotEmpty()) {
                    $q->orWhereIn('operacao_id', $operacaoIds);
                }
            })
            ->exists();
    }

    /**
     * Copia documento, selfie e anexos já existentes em operações da mesma empresa (ou legado sem operacao_id) para a operação destino.
     * Não copia documento/selfie se o formulário enviou arquivo novo na mesma request (evita duplicata antes do processar uploads).
     */
    public function copiarDocumentosReusoParaOperacao(
        int $clienteId,
        int $operacaoDestinoId,
        int $empresaId,
        ?UploadedFile $documentoUpload = null,
        ?UploadedFile $selfieUpload = null
    ): void {
        $operacaoIds = $this->idsOperacoesDaEmpresaParaReusoDocumentos($empresaId);

        foreach (['documento', 'selfie'] as $categoria) {
            if ($categoria === 'documento' && $documentoUpload instanceof UploadedFile) {
                continue;
            }
            if ($categoria === 'selfie' && $selfieUpload instanceof UploadedFile) {
                continue;
            }

            $docNoDestino = ClientDocument::query()
                ->where('cliente_id', $clienteId)
                ->where('operacao_id', $operacaoDestinoId)
                ->where('categoria', $categoria)
                ->orderByDesc('id')
                ->first();
            if ($docNoDestino && $this->caminhoRelativoExistenteNoDiscoPublico($docNoDestino->arquivo_path)) {
                continue;
            }

            $source = ClientDocument::query()
                ->where('cliente_id', $clienteId)
                ->where('categoria', $categoria)
                ->whereNotNull('arquivo_path')
                ->where('arquivo_path', '!=', '')
                ->where(function ($q) use ($operacaoIds) {
                    $q->whereNull('operacao_id');
                    if ($operacaoIds->isNotEmpty()) {
                        $q->orWhereIn('operacao_id', $operacaoIds);
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->first(function (ClientDocument $row) {
                    return (bool) $this->caminhoRelativoExistenteNoDiscoPublico($row->arquivo_path);
                });

            if (! $source) {
                continue;
            }

            $srcPath = $this->caminhoRelativoExistenteNoDiscoPublico($source->arquivo_path);
            if (! $srcPath) {
                continue;
            }

            $dir = $categoria === 'selfie' ? 'clientes/selfies' : 'clientes/documentos';
            $ext = pathinfo($srcPath, PATHINFO_EXTENSION) ?: ($categoria === 'selfie' ? 'jpg' : 'pdf');
            $newPath = $dir.'/reuso_'.uniqid('', true).'.'.$ext;

            if (! Storage::disk('public')->copy($srcPath, $newPath)) {
                continue;
            }

            ClientDocument::create([
                'cliente_id' => $clienteId,
                'empresa_id' => null,
                'operacao_id' => $operacaoDestinoId,
                'categoria' => $categoria,
                'tipo' => $source->tipo ?: ($categoria === 'selfie' ? 'selfie_documento' : 'documento_identidade'),
                'arquivo_path' => $newPath,
                'nome_arquivo' => $source->nome_arquivo ?: basename($newPath),
            ]);
        }

        $nomesAnexoJaNaDestino = ClientDocument::query()
            ->where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoDestinoId)
            ->where('categoria', 'anexo')
            ->pluck('nome_arquivo')
            ->filter()
            ->all();
        $nomesAnexoDestino = array_fill_keys($nomesAnexoJaNaDestino, true);

        $sourceAnexos = ClientDocument::query()
            ->where('cliente_id', $clienteId)
            ->where('categoria', 'anexo')
            ->whereNotNull('arquivo_path')
            ->where('arquivo_path', '!=', '')
            ->where(function ($q) use ($operacaoIds) {
                $q->whereNull('operacao_id');
                if ($operacaoIds->isNotEmpty()) {
                    $q->orWhereIn('operacao_id', $operacaoIds);
                }
            })
            ->where(function ($q) use ($operacaoDestinoId) {
                $q->whereNull('operacao_id')->orWhere('operacao_id', '!=', $operacaoDestinoId);
            })
            ->orderBy('id')
            ->get();

        foreach ($sourceAnexos as $src) {
            $nome = $src->nome_arquivo ?: basename((string) $src->arquivo_path);
            if ($nome !== '' && isset($nomesAnexoDestino[$nome])) {
                continue;
            }

            $srcPath = $this->caminhoRelativoExistenteNoDiscoPublico($src->arquivo_path);
            if (! $srcPath) {
                continue;
            }

            $ext = pathinfo($srcPath, PATHINFO_EXTENSION) ?: 'pdf';
            $newPath = 'clientes/anexos/reuso_'.uniqid('', true).'.'.$ext;

            if (! Storage::disk('public')->copy($srcPath, $newPath)) {
                continue;
            }

            ClientDocument::create([
                'cliente_id' => $clienteId,
                'empresa_id' => null,
                'operacao_id' => $operacaoDestinoId,
                'categoria' => 'anexo',
                'tipo' => $src->tipo ?: 'anexo',
                'arquivo_path' => $newPath,
                'nome_arquivo' => $nome,
            ]);

            if ($nome !== '') {
                $nomesAnexoDestino[$nome] = true;
            }
        }
    }
}

