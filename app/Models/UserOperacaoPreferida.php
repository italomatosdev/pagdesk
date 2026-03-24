<?php

namespace App\Models;

use App\Modules\Core\Models\Operacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOperacaoPreferida extends Model
{
    protected $table = 'user_operacao_preferida';

    protected $fillable = [
        'user_id',
        'operacao_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }
}
