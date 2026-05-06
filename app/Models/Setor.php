<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Setor extends Model
{
    protected $table = 'setor';

    protected $fillable = [
        'nome',
        'status',
        'recebe_pedidos_iniciais',
        'encaminha_pedido_para_setor_ids',
        'alterado_por',
        'ativo',
        'registro_anterior_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'recebe_pedidos_iniciais' => 'boolean',
        'encaminha_pedido_para_setor_ids' => 'array',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            if (Auth::check()) {
                $model->alterado_por = Auth::user()->name;
            }
        });
    }

    public function registroAnterior()
    {
        return $this->belongsTo(self::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(self::class, 'registro_anterior_id');
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeRecebePedidosIniciais(Builder $query): Builder
    {
        return $query->where('recebe_pedidos_iniciais', true);
    }

    public static function setorGeral(): ?self
    {
        $nomesEducacao = ['Educação'];

        if (function_exists('mb_convert_encoding')) {
            $nomesEducacao[] = mb_convert_encoding('Educação', 'UTF-8', 'ISO-8859-1');
        }

        $educacao = static::query()
            ->ativos()
            ->whereIn('nome', array_values(array_unique($nomesEducacao)))
            ->first();

        if ($educacao) {
            return $educacao;
        }

        return static::query()
            ->ativos()
            ->recebePedidosIniciais()
            ->latest('id')
            ->first()
            ?? static::query()->ativos()->orderBy('id')->first();
    }

    public function ehSetorGeral(): bool
    {
        return (bool) $this->recebe_pedidos_iniciais;
    }

    public function podeGerenciarSetor(?self $setor): bool
    {
        if (! $setor) {
            return false;
        }

        if ($this->ehSetorGeral()) {
            return true;
        }

        return $this->is($setor);
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
            ->orderBy('nome')
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

            $outroSetorGeralExiste = static::query()
                ->where('recebe_pedidos_iniciais', true)
                ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
                ->exists();

            if ($outroSetorGeralExiste) {
                throw ValidationException::withMessages([
                    'recebe_pedidos_iniciais' => 'Somente um setor pode receber os pedidos iniciais.',
                ]);
            }

            return;
        }

        if ($ids === []) {
            throw ValidationException::withMessages([
                'encaminha_pedido_para_setor_ids' => 'Selecione ao menos um setor para encaminhamento.',
            ]);
        }

        if ($this->exists && in_array((int) $this->getKey(), $ids, true)) {
            throw ValidationException::withMessages([
                'encaminha_pedido_para_setor_ids' => 'Um setor não pode encaminhar pedidos para ele mesmo.',
            ]);
        }

        $this->encaminha_pedido_para_setor_ids = $ids;
    }
}
