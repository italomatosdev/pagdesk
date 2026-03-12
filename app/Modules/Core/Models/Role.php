<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Relacionamento: Um papel pode ter muitos usuários
     */
    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'role_user', 'role_id', 'user_id');
    }

    /**
     * Relacionamento: Um papel tem muitas permissões
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id');
    }
}

