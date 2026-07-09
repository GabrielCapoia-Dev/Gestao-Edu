<?php

namespace App\Models;

/**
 * Alias de compatibilidade para {@see Pessoa}.
 *
 * Preferir `Pessoa` em código novo. A tabela física continua `servidores`.
 *
 * @deprecated Use App\Models\Pessoa
 */
class Servidor extends Pessoa
{
}
