<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacaoDocumentoObrigatorio extends Model
{
    protected $table = 'operacao_documentos_obrigatorios';

    protected $fillable = ['operacao_id', 'tipo_documento'];

    /**
     * Tipos de documento que podem ser obrigatórios (chave = name do input, label para exibição).
     */
    public static function tiposDisponiveis(): array
    {
        return [
            'documento_cliente' => 'Documento do cliente (RG/CNH/Comprovante)',
            'selfie_documento'  => 'Selfie com documento',
        ];
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }
}
