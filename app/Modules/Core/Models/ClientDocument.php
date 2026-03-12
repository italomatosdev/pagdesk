<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_documents';

    protected $fillable = [
        'cliente_id',
        'empresa_id', // null = documento original (empresa criadora), preenchido = documento específico da empresa
        'categoria', // 'documento', 'selfie', 'anexo'
        'tipo',
        'numero',
        'orgao_emissor',
        'data_emissao',
        'data_vencimento',
        'arquivo_path',
        'nome_arquivo',
        'observacoes',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
    ];

    /**
     * Relacionamento: Um documento pertence a um cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relacionamento: Um documento pode pertencer a uma empresa específica
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Verificar se é documento original (da empresa criadora)
     */
    public function isDocumentoOriginal(): bool
    {
        return $this->empresa_id === null;
    }

    /**
     * Verificar se é documento específico de uma empresa
     */
    public function isDocumentoEmpresa(): bool
    {
        return $this->empresa_id !== null;
    }

    /**
     * Accessor: URL do arquivo
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->arquivo_path) {
            return null;
        }
        return asset('storage/' . $this->arquivo_path);
    }

    /**
     * Verificar se é documento obrigatório
     */
    public function isDocumento(): bool
    {
        return $this->categoria === 'documento';
    }

    /**
     * Verificar se é selfie
     */
    public function isSelfie(): bool
    {
        return $this->categoria === 'selfie';
    }

    /**
     * Verificar se é anexo
     */
    public function isAnexo(): bool
    {
        return $this->categoria === 'anexo';
    }

    /**
     * Verificar se o arquivo é uma imagem
     *
     * @return bool
     */
    public function isImagem(): bool
    {
        if (!$this->arquivo_path && !$this->nome_arquivo) {
            return false;
        }

        // Pegar extensão do arquivo
        $extensao = strtolower(pathinfo($this->arquivo_path ?? $this->nome_arquivo, PATHINFO_EXTENSION));
        
        // Extensões de imagem suportadas
        $extensoesImagem = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        
        return in_array($extensao, $extensoesImagem);
    }
}

