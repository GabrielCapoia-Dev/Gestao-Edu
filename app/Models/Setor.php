<?php

namespace App\Models;

use App\Models\Enums\SetorContexto;
use App\Services\SetorHierarchyService;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Setor extends Model
{
    protected $table = 'setor';

    protected $fillable = [
        'parent_id',
        'path',
        'depth',
        'sort_order',
        'is_default_root',
        'nome',
        'contexto',
        'exige_vinculo_escola',
        'status',
        'recebe_pedidos_iniciais',
        'encaminha_pedido_para_setor_ids',
        'alterado_por',
        'ativo',
        'registro_anterior_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'depth' => 'integer',
        'sort_order' => 'integer',
        'is_default_root' => 'boolean',
        'recebe_pedidos_iniciais' => 'boolean',
        'encaminha_pedido_para_setor_ids' => 'array',
        'exige_vinculo_escola' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Setor $model): void {
            if (filled($model->contexto)) {
                $contexto = SetorContexto::tryFrom((string) $model->contexto);
                $model->exige_vinculo_escola = $contexto?->exigeVinculoEscola() ?? (bool) $model->exige_vinculo_escola;
            }

            if ($model->is_default_root) {
                $model->parent_id = null;
                $model->recebe_pedidos_iniciais = true;
            }

            $model->validarFluxoPedidos();
            app(SetorHierarchyService::class)->assertValidParent($model, $model->parent_id);

            if (Auth::check()) {
                $model->alterado_por = Auth::user()->name;

                if (filled($model->parent_id)) {
                    app(UserSetorAccessService::class)->assertCanUseSetor(Auth::user(), (int) $model->parent_id);
                }
            }
        });

        static::saved(function (Setor $model): void {
            app(SetorHierarchyService::class)->refreshNode($model);
        });
    }

    public function contextoEnum(): ?SetorContexto
    {
        return SetorContexto::tryFrom((string) $this->contexto);
    }

    public function exigeVinculoEscola(): bool
    {
        return (bool) $this->exige_vinculo_escola;
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('nome');
    }

    public function registroAnterior()
    {
        return $this->belongsTo(self::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(self::class, 'registro_anterior_id');
    }

    public function acessosConcedidos()
    {
        return $this->hasMany(SetorAcesso::class, 'setor_origem_id');
    }

    public function acessosRecebidos()
    {
        return $this->hasMany(SetorAcesso::class, 'setor_alvo_id');
    }

    public function setoresListaveis(): BelongsToMany
    {
        return $this->setoresPorCapacidade('pode_listar');
    }

    public function setoresEditaveis(): BelongsToMany
    {
        return $this->setoresPorCapacidade('pode_editar');
    }

    public function setoresCancelaveis(): BelongsToMany
    {
        return $this->setoresPorCapacidade('pode_cancelar');
    }

    public function setoresEncaminhaveis(): BelongsToMany
    {
        return $this->setoresPorCapacidade('pode_encaminhar');
    }

    public function descendantsQuery(bool $includeSelf = false): Builder
    {
        return app(SetorHierarchyService::class)->descendantsQuery($this, $includeSelf);
    }

    public function selfAndDescendantIds(): array
    {
        return app(SetorHierarchyService::class)->selfAndDescendantIds($this);
    }

    public function ancestorIds(bool $includeSelf = true): array
    {
        return app(SetorHierarchyService::class)->ancestorIds($this, $includeSelf);
    }

    public function isAncestorOf(self|int|null $setor, bool $includeSelf = true): bool
    {
        return app(SetorHierarchyService::class)->isAncestorOf($this, $setor, $includeSelf);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeRecebePedidosIniciais(Builder $query): Builder
    {
        return $query->where('recebe_pedidos_iniciais', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrderedTree(Builder $query): Builder
    {
        return $query
            ->orderBy('path')
            ->orderBy('sort_order')
            ->orderBy('nome');
    }

    public static function setorGeral(): ?self
    {
        return app(SetorHierarchyService::class)->defaultRoot();
    }

    public function ehSetorGeral(): bool
    {
        return (bool) ($this->is_default_root || $this->recebe_pedidos_iniciais || blank($this->parent_id));
    }

    public function podeGerenciarSetor(?self $setor): bool
    {
        if (! $setor) {
            return false;
        }

        return $this->isAncestorOf($setor);
    }

    public function getNomeCompletoAttribute(): string
    {
        return app(SetorHierarchyService::class)->fullPathLabel($this) ?? $this->nome;
    }

    public function getSetoresDestinoAttribute()
    {
        $ids = collect($this->encaminha_pedido_para_setor_ids ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return static::query()
            ->whereIn('id', $ids)
            ->orderedTree()
            ->get();
    }

    public function temSetoresDestino(): bool
    {
        return count($this->encaminha_pedido_para_setor_ids ?? []) > 0;
    }

    protected function validarFluxoPedidos(): void
    {
        $this->recebe_pedidos_iniciais = (bool) $this->recebe_pedidos_iniciais;
        $ids = collect($this->encaminha_pedido_para_setor_ids ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($this->recebe_pedidos_iniciais) {
            $this->encaminha_pedido_para_setor_ids = [];

            return;
        }

        if ($this->exists && in_array((int) $this->getKey(), $ids, true)) {
            throw ValidationException::withMessages([
                'encaminha_pedido_para_setor_ids' => 'Um setor não pode encaminhar pedidos para ele mesmo.',
            ]);
        }

        $this->encaminha_pedido_para_setor_ids = $ids;
    }

    private function setoresPorCapacidade(string $capability): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'setor_acessos',
            'setor_origem_id',
            'setor_alvo_id',
        )
            ->withPivot([
                'pode_listar',
                'pode_editar',
                'pode_cancelar',
                'pode_encaminhar',
            ])
            ->wherePivot($capability, true)
            ->withTimestamps();
    }
}
