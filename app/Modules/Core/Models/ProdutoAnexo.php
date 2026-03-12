<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProdutoAnexo extends Model
{
    protected $table = 'produto_anexos';

    protected $fillable = [
        'produto_id',
        'nome_arquivo',
        'caminho',
        'tipo',
        'ordem',
        'tamanho',
    ];

    protected $casts = [
        'ordem' => 'integer',
    ];

    public const EXTENSOES_IMAGEM = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->caminho);
    }

    public function isImagem(): bool
    {
        return $this->tipo === 'imagem';
    }

    public function isDocumento(): bool
    {
        return $this->tipo === 'documento';
    }

    public function getExtensaoAttribute(): string
    {
        return strtolower(pathinfo($this->nome_arquivo, PATHINFO_EXTENSION));
    }

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

    public static function determinarTipo(string $extensao): string
    {
        $extensao = strtolower($extensao);
        return in_array($extensao, self::EXTENSOES_IMAGEM) ? 'imagem' : 'documento';
    }

    protected static function booted(): void
    {
        static::deleting(function ($anexo) {
            if (Storage::disk('public')->exists($anexo->caminho)) {
                Storage::disk('public')->delete($anexo->caminho);
            }
        });
    }
}
