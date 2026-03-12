<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmprestimoGarantiaAnexo extends Model
{
    use HasFactory;

    protected $table = 'emprestimo_garantia_anexos';

    protected $fillable = [
        'garantia_id',
        'nome_arquivo',
        'caminho',
        'tipo',
        'tamanho',
    ];

    /**
     * Extensões de imagem aceitas
     */
    public const EXTENSOES_IMAGEM = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    /**
     * Extensões de documento aceitas
     */
    public const EXTENSOES_DOCUMENTO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    /**
     * Relacionamento: Anexo pertence a uma garantia
     */
    public function garantia()
    {
        return $this->belongsTo(EmprestimoGarantia::class, 'garantia_id');
    }

    /**
     * Obter URL do arquivo
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->caminho);
    }

    /**
     * Verificar se é imagem
     */
    public function isImagem(): bool
    {
        return $this->tipo === 'imagem';
    }

    /**
     * Verificar se é documento
     */
    public function isDocumento(): bool
    {
        return $this->tipo === 'documento';
    }

    /**
     * Obter extensão do arquivo
     */
    public function getExtensaoAttribute(): string
    {
        return strtolower(pathinfo($this->nome_arquivo, PATHINFO_EXTENSION));
    }

    /**
     * Obter tamanho formatado
     */
    public function getTamanhoFormatadoAttribute(): string
    {
        if (!$this->tamanho) {
            return 'N/A';
        }

        $bytes = $this->tamanho;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Obter ícone baseado no tipo/extensão
     */
    public function getIconeAttribute(): string
    {
        if ($this->isImagem()) {
            return 'bx bx-image';
        }

        return match($this->extensao) {
            'pdf' => 'bx bxs-file-pdf',
            'doc', 'docx' => 'bx bxs-file-doc',
            'xls', 'xlsx' => 'bx bxs-file',
            default => 'bx bx-file',
        };
    }

    /**
     * Determinar tipo baseado na extensão
     */
    public static function determinarTipo(string $extensao): string
    {
        $extensao = strtolower($extensao);
        
        if (in_array($extensao, self::EXTENSOES_IMAGEM)) {
            return 'imagem';
        }

        return 'documento';
    }

    /**
     * Deletar arquivo do storage ao excluir registro
     */
    protected static function booted(): void
    {
        static::deleting(function ($anexo) {
            if (Storage::disk('public')->exists($anexo->caminho)) {
                Storage::disk('public')->delete($anexo->caminho);
            }
        });
    }
}
