<?php

namespace App\Exceptions;

use DomainException;

class ItemEmBalancoException extends DomainException
{
    public static function porCodigo(string $itemNome, string $codigoBalanco): self
    {
        return new self("O item {$itemNome} está bloqueado pelo Balanço {$codigoBalanco}.");
    }
}
