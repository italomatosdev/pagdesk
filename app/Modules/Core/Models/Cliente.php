<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'tipo_pessoa',
        'documento',
        'nome',
        'telefone',
        'email',
        'data_nascimento',
        'responsavel_nome',
        'responsavel_cpf',
        'responsavel_rg',
        'responsavel_cnh',
        'responsavel_cargo',
        'endereco',
        'numero',
        'cidade',
        'estado',
        'cep',
        'observacoes',
        'empresa_id', // Empresa que cadastrou o cliente
        'sandbox', // Dados fictícios para ambiente de testes (Super Admin)
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'sandbox' => 'boolean',
    ];

    /**
     * Boot do model - aplicar Global Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\EmpresaScope);
    }

    /**
     * Buscar cliente por documento (CPF ou CNPJ sem formatação) - SEM escopo de empresa (para consulta cruzada)
     */
    public static function buscarPorDocumento(string $documento): ?self
    {
        // Remove formatação do documento
        $documentoLimpo = preg_replace('/[^0-9]/', '', $documento);
        
        // Busca SEM escopo de empresa (para consulta cruzada)
        return self::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('documento', $documentoLimpo)
            ->first();
    }

    /**
     * Buscar cliente por CPF (mantido para compatibilidade)
     * @deprecated Use buscarPorDocumento() ao invés disso
     */
    public static function buscarPorCpf(string $cpf): ?self
    {
        return self::buscarPorDocumento($cpf);
    }

    /**
     * Relacionamento: Um cliente tem muitos documentos
     * Retorna documentos baseado na empresa atual:
     * - Empresa criadora: apenas documentos originais (empresa_id = null)
     * - Outras empresas: documentos originais + documentos específicos da empresa
     */
    public function documentos()
    {
        $empresaId = auth()->check() ? auth()->user()->empresa_id : null;
        
        // Se não há empresa autenticada, retorna todos os documentos
        if (!$empresaId) {
            return $this->hasMany(ClientDocument::class, 'cliente_id');
        }
        
        // Se a empresa atual é a criadora, mostra apenas documentos originais
        if ($this->isEmpresaCriadora($empresaId)) {
            return $this->hasMany(ClientDocument::class, 'cliente_id')
                ->whereNull('empresa_id');
        }
        
        // Se não é a criadora, mostra documentos originais + documentos da empresa atual
        // Ordena para que documentos específicos da empresa venham primeiro
        return $this->hasMany(ClientDocument::class, 'cliente_id')
            ->where(function ($query) use ($empresaId) {
                $query->whereNull('empresa_id')
                      ->orWhere('empresa_id', $empresaId);
            })
            ->orderByRaw('CASE WHEN empresa_id IS NULL THEN 1 ELSE 0 END'); // Documentos específicos primeiro
    }

    /**
     * Obter documento por categoria, priorizando documentos específicos da empresa
     */
    public function getDocumentoPorCategoria(string $categoria): ?ClientDocument
    {
        $empresaId = auth()->check() ? auth()->user()->empresa_id : null;
        
        // Carregar documentos se ainda não foram carregados
        if (!$this->relationLoaded('documentos')) {
            $this->load('documentos');
        }
        
        // Se não há empresa autenticada ou é a criadora, retorna o primeiro encontrado
        if (!$empresaId || $this->isEmpresaCriadora($empresaId)) {
            return $this->documentos->where('categoria', $categoria)->first();
        }
        
        // Para outras empresas, prioriza documento específico da empresa
        $documentoEspecifico = $this->documentos
            ->where('categoria', $categoria)
            ->where('empresa_id', $empresaId)
            ->first();
        
        // Se não encontrou específico, retorna o original
        return $documentoEspecifico ?? $this->documentos
            ->where('categoria', $categoria)
            ->whereNull('empresa_id')
            ->first();
    }

    /**
     * Relacionamento: Um cliente pode estar vinculado a muitas operações
     */
    public function operationClients()
    {
        return $this->hasMany(OperationClient::class, 'cliente_id');
    }

    /**
     * Fichas cadastrais por operação (`operacao_dados_clientes`).
     */
    public function operacaoDadosClientes()
    {
        return $this->hasMany(OperacaoDadosCliente::class, 'cliente_id');
    }

    /**
     * Relacionamento: Um cliente tem muitos empréstimos
     */
    public function emprestimos()
    {
        return $this->hasMany(\App\Modules\Loans\Models\Emprestimo::class, 'cliente_id');
    }

    /**
     * Relacionamento: Um cliente pertence a uma empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Relacionamento: Um cliente pode ter dados específicos por empresa
     */
    public function dadosEmpresa()
    {
        return $this->hasMany(ClienteDadosEmpresa::class, 'cliente_id');
    }

    /**
     * Relacionamento: Empresas vinculadas a este cliente (many-to-many)
     */
    public function empresasVinculadas()
    {
        return $this->hasMany(EmpresaClienteVinculo::class, 'cliente_id');
    }

    /**
     * Verificar se o cliente está vinculado à empresa atual
     */
    public function isVinculadoEmpresa(?int $empresaId = null): bool
    {
        if (!$empresaId) {
            $empresaId = auth()->check() ? auth()->user()->empresa_id : null;
        }
        
        if (!$empresaId) {
            return false;
        }
        
        return $this->empresasVinculadas()
            ->where('empresa_id', $empresaId)
            ->exists();
    }

    /**
     * Obter dados específicos de uma empresa
     */
    public function dadosPorEmpresa(?int $empresaId = null): ?ClienteDadosEmpresa
    {
        if (!$empresaId) {
            $empresaId = auth()->check() ? auth()->user()->empresa_id : null;
        }
        
        if (!$empresaId) {
            return null;
        }

        return $this->dadosEmpresa()
            ->where('empresa_id', $empresaId)
            ->first();
    }

    /**
     * Verificar se a empresa atual é a criadora do cliente
     */
    public function isEmpresaCriadora(?int $empresaId = null): bool
    {
        if (!$empresaId) {
            $empresaId = auth()->check() ? auth()->user()->empresa_id : null;
        }
        
        return $empresaId && $this->empresa_id == $empresaId;
    }

    /**
     * Verificar se é pessoa física
     */
    public function isPessoaFisica(): bool
    {
        return $this->tipo_pessoa === 'fisica';
    }

    /**
     * Verificar se é pessoa jurídica
     */
    public function isPessoaJuridica(): bool
    {
        return $this->tipo_pessoa === 'juridica';
    }

    /**
     * Accessor: Documento formatado (CPF ou CNPJ)
     */
    public function getDocumentoFormatadoAttribute(): string
    {
        if (!$this->documento) {
            return '';
        }

        if ($this->isPessoaFisica()) {
            return \App\Helpers\ValidacaoDocumento::formatarCpf($this->documento);
        } else {
            return \App\Helpers\ValidacaoDocumento::formatarCnpj($this->documento);
        }
    }

    /**
     * Accessor: Documento mascarado (ex.: ***.***.***-12 para CPF) para exibição em telas sensíveis (ex.: mural de devedores).
     */
    public function getDocumentoMascaradoAttribute(): string
    {
        if (!$this->documento) {
            return '';
        }
        $doc = preg_replace('/[^0-9]/', '', $this->documento);
        if (strlen($doc) === 11) {
            return '***.***.***-' . substr($doc, -2);
        }
        if (strlen($doc) === 14) {
            return '**.***.***/****-' . substr($doc, -2);
        }
        return '***';
    }

    /**
     * Accessor: CPF formatado (mantido para compatibilidade)
     * @deprecated Use documento_formatado ao invés disso
     */
    public function getCpfFormatadoAttribute(): string
    {
        if ($this->isPessoaFisica()) {
            return $this->documento_formatado;
        }
        return '';
    }

    /**
     * Formatar telefone para exibição
     */
    public function getTelefoneFormatadoAttribute(): ?string
    {
        if (!$this->telefone) {
            return null;
        }

        // Remove todos os caracteres não numéricos
        $telefone = preg_replace('/[^0-9]/', '', $this->telefone);

        // Formata baseado no tamanho
        if (strlen($telefone) == 11) {
            // Celular: (XX) XXXXX-XXXX
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
        } elseif (strlen($telefone) == 10) {
            // Fixo: (XX) XXXX-XXXX
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
        }

        // Se não tiver 10 ou 11 dígitos, retorna sem formatação
        return $this->telefone;
    }

    /**
     * Obter número de telefone limpo (apenas dígitos) para WhatsApp
     */
    public function getTelefoneLimpoAttribute(): ?string
    {
        if (!$this->telefone) {
            return null;
        }

        // Remove todos os caracteres não numéricos
        $telefone = preg_replace('/[^0-9]/', '', $this->telefone);

        // Se começar com 0, remove
        if (strlen($telefone) > 0 && $telefone[0] == '0') {
            $telefone = substr($telefone, 1);
        }

        return $telefone;
    }

    /**
     * Gerar link do WhatsApp
     */
    public function getWhatsappLinkAttribute(): ?string
    {
        $telefoneLimpo = $this->telefone_limpo;
        
        if (!$telefoneLimpo) {
            return null;
        }

        // Garante que o número tenha código do país (55 para Brasil)
        // Se não começar com 55, adiciona
        if (strlen($telefoneLimpo) >= 10 && !str_starts_with($telefoneLimpo, '55')) {
            $telefoneLimpo = '55' . $telefoneLimpo;
        }

        return 'https://wa.me/' . $telefoneLimpo;
    }

    /**
     * Verificar se o cliente tem telefone válido para WhatsApp
     */
    public function temWhatsapp(): bool
    {
        return !empty($this->telefone_limpo);
    }

    /**
     * Accessor: CPF do responsável formatado
     */
    public function getResponsavelCpfFormatadoAttribute(): ?string
    {
        if (!$this->responsavel_cpf) {
            return null;
        }
        return \App\Helpers\ValidacaoDocumento::formatarCpf($this->responsavel_cpf);
    }

    /**
     * Cache para dados da empresa (evita múltiplas queries)
     */
    protected ?ClienteDadosEmpresa $cachedDadosEmpresa = null;

    /**
     * Obter dados da empresa atual (com cache)
     * Usa getOriginal() para evitar loop infinito nos accessors
     */
    protected function getDadosEmpresaAtual(): ?ClienteDadosEmpresa
    {
        // Se a empresa atual é a criadora, não usar override
        $empresaId = auth()->check() ? auth()->user()->empresa_id : null;
        if ($empresaId && $this->empresa_id == $empresaId) {
            return null; // Empresa criadora sempre usa dados originais
        }
        
        if ($this->cachedDadosEmpresa === null) {
            if ($empresaId) {
                // Primeiro tenta usar o relacionamento já carregado (se existir)
                if ($this->relationLoaded('dadosEmpresa')) {
                    $this->cachedDadosEmpresa = $this->getRelation('dadosEmpresa')
                        ->where('empresa_id', $empresaId)
                        ->first();
                }
                
                // Se não encontrou no relacionamento carregado, busca no banco
                if (!$this->cachedDadosEmpresa) {
                    $this->cachedDadosEmpresa = $this->dadosEmpresa()
                        ->where('empresa_id', $empresaId)
                        ->first();
                }
            }
        }
        return $this->cachedDadosEmpresa;
    }

    /**
     * Accessor: Nome (com override por empresa)
     */
    public function getNomeAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->nome ? $dadosEmpresa->nome : $value;
    }

    /**
     * Accessor: Telefone (com override por empresa)
     */
    public function getTelefoneAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->telefone ? $dadosEmpresa->telefone : $value;
    }

    /**
     * Accessor: Email (com override por empresa)
     */
    public function getEmailAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->email ? $dadosEmpresa->email : $value;
    }

    /**
     * Accessor: Data de nascimento (com override por empresa)
     */
    public function getDataNascimentoAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        
        // Se houver override, usar ele
        if ($dadosEmpresa && $dadosEmpresa->data_nascimento) {
            // Garantir que retorna Carbon
            if ($dadosEmpresa->data_nascimento instanceof \Carbon\Carbon) {
                return $dadosEmpresa->data_nascimento;
            }
            try {
                return \Carbon\Carbon::parse($dadosEmpresa->data_nascimento);
            } catch (\Exception $e) {
                // Se falhar, usar valor original
            }
        }
        
        // Usar valor original - sempre converter para Carbon se houver valor
        if (!$value) {
            return null;
        }
        
        if ($value instanceof \Carbon\Carbon) {
            return $value;
        }
        
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Accessor: Endereço (com override por empresa)
     */
    public function getEnderecoAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->endereco ? $dadosEmpresa->endereco : $value;
    }

    /**
     * Accessor: Número (com override por empresa)
     */
    public function getNumeroAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->numero ? $dadosEmpresa->numero : $value;
    }

    /**
     * Accessor: Cidade (com override por empresa)
     */
    public function getCidadeAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->cidade ? $dadosEmpresa->cidade : $value;
    }

    /**
     * Accessor: Estado (com override por empresa)
     */
    public function getEstadoAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->estado ? $dadosEmpresa->estado : $value;
    }

    /**
     * Accessor: CEP (com override por empresa)
     */
    public function getCepAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->cep ? $dadosEmpresa->cep : $value;
    }

    /**
     * Accessor: Observações (com override por empresa)
     */
    public function getObservacoesAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->observacoes ? $dadosEmpresa->observacoes : $value;
    }

    /**
     * Accessor: Nome do responsável (com override por empresa)
     */
    public function getResponsavelNomeAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->responsavel_nome ? $dadosEmpresa->responsavel_nome : $value;
    }

    /**
     * Accessor: CPF do responsável (com override por empresa)
     */
    public function getResponsavelCpfAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->responsavel_cpf ? $dadosEmpresa->responsavel_cpf : $value;
    }

    /**
     * Accessor: RG do responsável (com override por empresa)
     */
    public function getResponsavelRgAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->responsavel_rg ? $dadosEmpresa->responsavel_rg : $value;
    }

    /**
     * Accessor: CNH do responsável (com override por empresa)
     */
    public function getResponsavelCnhAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->responsavel_cnh ? $dadosEmpresa->responsavel_cnh : $value;
    }

    /**
     * Accessor: Cargo do responsável (com override por empresa)
     */
    public function getResponsavelCargoAttribute($value)
    {
        $dadosEmpresa = $this->getDadosEmpresaAtual();
        return $dadosEmpresa && $dadosEmpresa->responsavel_cargo ? $dadosEmpresa->responsavel_cargo : $value;
    }
}

