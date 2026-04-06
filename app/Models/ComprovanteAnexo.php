<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComprovanteAnexo extends Model
{
    protected $table = 'comprovante_anexos';

    protected $fillable = [
        'anexavel_type',
        'anexavel_id',
        'context',
        'path',
        'original_name',
        'uploaded_by',
        'empresa_id',
    ];

    public function anexavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function urlAsset(): string
    {
        return asset('storage/'.$this->path);
    }
}
