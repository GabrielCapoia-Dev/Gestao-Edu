<?php

namespace App\Services\Avaliacoes;

use InvalidArgumentException;

class AvaliacaoPersistencia
{
    public const DRIVER_JSON = 'json';
    public const DRIVER_SHADOW = 'shadow';
    public const DRIVER_RELACIONAL = 'relacional';

    public function driver(): string
    {
        $driver = (string) config('avaliacoes_persistencia.driver', self::DRIVER_JSON);

        if (! in_array($driver, [self::DRIVER_JSON, self::DRIVER_SHADOW, self::DRIVER_RELACIONAL], true)) {
            throw new InvalidArgumentException("Driver de persistência de avaliações inválido: {$driver}.");
        }

        return $driver;
    }

    public function gravaRelacional(): bool
    {
        return in_array($this->driver(), [self::DRIVER_SHADOW, self::DRIVER_RELACIONAL], true);
    }

    public function gravaDocumentoLegado(): bool
    {
        return in_array($this->driver(), [self::DRIVER_JSON, self::DRIVER_SHADOW], true);
    }

    public function leRelacional(): bool
    {
        return $this->driver() === self::DRIVER_RELACIONAL;
    }
}
