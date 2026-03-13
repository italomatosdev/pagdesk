<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'operacao_id', // Operação principal do usuário (opcional)
        'empresa_id', // Empresa do usuário (obrigatório, exceto super admin)
        'is_super_admin', // Indica se é super admin do sistema
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relacionamento: Um usuário pode ter múltiplos papéis
     */
    public function roles()
    {
        return $this->belongsToMany(\App\Modules\Core\Models\Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * Verificar se usuário tem um papel específico
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Verificar se usuário tem algum dos papéis informados
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Verificar se usuário tem permissão específica
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    /**
     * Relacionamento: Operação principal do usuário (legado - manter para compatibilidade)
     */
    public function operacao()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Operacao::class, 'operacao_id');
    }

    /**
     * Relacionamento: Operações do usuário (many-to-many)
     * Um usuário pode estar em várias operações
     */
    public function operacoes()
    {
        return $this->belongsToMany(\App\Modules\Core\Models\Operacao::class, 'operacao_user', 'user_id', 'operacao_id')
            ->withTimestamps();
    }

    /**
     * Obter IDs das operações vinculadas ao usuário
     * 
     * @return array
     */
    public function getOperacoesIds(): array
    {
        // Garantir que o relacionamento está carregado
        if (!$this->relationLoaded('operacoes')) {
            $this->load('operacoes');
        }
        return $this->operacoes->pluck('id')->toArray();
    }

    /**
     * Verificar se o usuário tem acesso a uma operação específica
     * 
     * @param int $operacaoId
     * @return bool
     */
    public function temAcessoOperacao(int $operacaoId): bool
    {
        // Super Admin e administradores têm acesso a todas as operações
        if ($this->isSuperAdmin() || $this->hasRole('administrador')) {
            return true;
        }

        return $this->operacoes()->where('operacoes.id', $operacaoId)->exists();
    }

    /**
     * Aplicar filtro de operações permitidas em uma query
     * Para administradores, não aplica filtro (vê tudo)
     * Para outros usuários, filtra apenas pelas operações vinculadas
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $column Nome da coluna (padrão: 'operacao_id')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function aplicarFiltroOperacoes($query, string $column = 'operacao_id')
    {
        // Administradores veem tudo
        if ($this->hasRole('administrador')) {
            return $query;
        }

        $operacoesIds = $this->getOperacoesIds();
        
        if (empty($operacoesIds)) {
            // Se não tem operações vinculadas, retorna query vazia
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $operacoesIds);
    }

    /**
     * Obter URL do avatar do usuário
     * Se não tiver avatar, retorna URL da imagem padrão
     * 
     * @return string
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/avatars/' . $this->avatar);
        }
        
        // Retorna imagem padrão (avatar-3.jpg do template)
        return asset('build/images/users/avatar-3.jpg');
    }

    /**
     * Obter iniciais do nome para usar como fallback no avatar
     * Retorna até 2 iniciais (ex: "IM" para "Ítalo Matos")
     * 
     * @return string
     */
    public function getInitialAttribute(): string
    {
        if (empty($this->name)) {
            return '?';
        }

        $names = preg_split('/\s+/', trim($this->name));
        $names = array_filter($names);
        
        if (empty($names)) {
            return '?';
        }

        $first = mb_strtoupper(mb_substr($names[0], 0, 1, 'UTF-8'), 'UTF-8');
        
        if (count($names) > 1) {
            $last = mb_strtoupper(mb_substr(end($names), 0, 1, 'UTF-8'), 'UTF-8');
            return $first . $last;
        }

        return $first;
    }

    /**
     * Relacionamento: Usuário pertence a uma empresa
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Empresa::class, 'empresa_id');
    }

    /**
     * Verificar se é super admin
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Enviar notificação de redefinição de senha (e-mail em português).
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
