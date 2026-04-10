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
        'encaminha_pedido_para_setor_id',
        'alterado_por',
        'ativo',
        'registro_anterior_id',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'recebe_pedidos_iniciais' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            if (Auth::check()) {
                $model->alterado_por = Auth::user()->name;
            }

            $model->validarFluxoPedidos();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Histórico
    |--------------------------------------------------------------------------
    */

    public function registroAnterior()
    {
        return $this->belongsTo(self::class, 'registro_anterior_id');
    }

    public function historico()
    {
        return $this->hasMany(self::class, 'registro_anterior_id');
    }

    public function encaminhaPedidoParaSetor()
    {
        return $this->belongsTo(self::class, 'encaminha_pedido_para_setor_id');
    }

    public function setoresGerenciados()
    {
        return $this->hasMany(self::class, 'encaminha_pedido_para_setor_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

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
        return static::query()
            ->ativos()
            ->recebePedidosIniciais()
            ->latest('id')
            ->first();
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

    protected function validarFluxoPedidos(): void
    {
        $this->recebe_pedidos_iniciais = (bool) $this->recebe_pedidos_iniciais;

        if ($this->recebe_pedidos_iniciais) {
            $this->encaminha_pedido_para_setor_id = null;

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

        if (! $this->encaminha_pedido_para_setor_id) {
            throw ValidationException::withMessages([
                'encaminha_pedido_para_setor_id' => 'Selecione o setor para o qual este setor encaminha os pedidos.',
            ]);
        }

        if ($this->exists && $this->encaminha_pedido_para_setor_id === $this->getKey()) {
            throw ValidationException::withMessages([
                'encaminha_pedido_para_setor_id' => 'Um setor não pode encaminhar pedidos para ele mesmo.',
            ]);
        }
    }
}
