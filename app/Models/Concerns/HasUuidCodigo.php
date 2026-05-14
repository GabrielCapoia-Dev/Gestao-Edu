<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuidCodigo
{
    protected static function bootHasUuidCodigo(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->codigo)) {
                return;
            }

            $model->codigo = static::gerarCodigoUuid();
        });
    }

    public static function gerarCodigoUuid(): string
    {
        do {
            $codigo = (string) Str::uuid();
        } while (static::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
