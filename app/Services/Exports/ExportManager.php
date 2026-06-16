<?php

namespace App\Services\Exports;

use App\Contracts\Exports\ExportHandler;
use InvalidArgumentException;

class ExportManager
{
    public function hasHandler(string $type): bool
    {
        return is_string(config("exports.handlers.{$type}"));
    }

    public function handlerFor(string $type): ExportHandler
    {
        $handlerClass = config("exports.handlers.{$type}");

        if (! is_string($handlerClass) || ! class_exists($handlerClass)) {
            throw new InvalidArgumentException("Nenhum processador de exportação foi configurado para [{$type}].");
        }

        $handler = app($handlerClass);

        if (! $handler instanceof ExportHandler) {
            throw new InvalidArgumentException("O processador [{$handlerClass}] deve implementar ExportHandler.");
        }

        return $handler;
    }
}
