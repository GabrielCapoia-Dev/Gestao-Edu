<?php

namespace App\Models;

use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PublicoAlvo extends Model
{
    protected $table = 'publicos_alvo';

    protected $fillable = [
        'modo_correspondencia',
        'todos_usuarios',
        'escopo_global',
    ];

    protected function casts(): array
    {
        return [
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::class,
            'todos_usuarios' => 'boolean',
            'escopo_global' => 'boolean',
        ];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'publico_alvo_user');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'publico_alvo_role');
    }

    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'publico_alvo_permission');
    }

    public function funcoesAdministrativas(): BelongsToMany
    {
        return $this->belongsToMany(
            FuncaoAdministrativa::class,
            'publico_alvo_funcao_administrativa',
        );
    }

    public function escolas(): BelongsToMany
    {
        return $this->belongsToMany(Escola::class, 'publico_alvo_escola');
    }

    public function setores(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'publico_alvo_setor');
    }

    public function escopoEscolas(): BelongsToMany
    {
        return $this->belongsToMany(Escola::class, 'publico_alvo_escopo_escola');
    }

    public function escopoSetores(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'publico_alvo_escopo_setor');
    }
}
