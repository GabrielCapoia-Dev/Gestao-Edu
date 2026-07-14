<?php

namespace App\Support;

class SecretarioPermissionPreset
{
    public static function base(): array
    {
        return [];
    }

    public static function gestaoEscolar(): array
    {
        return [
            'Listar GestÃ£o de InventÃ¡rio',
            'Listar Pedidos de InventÃ¡rio',
            'Criar Pedidos de InventÃ¡rio',
            'Conferir Pedidos de InventÃ¡rio',
            'Listar BalanÃ§os de InventÃ¡rio',
            'Criar BalanÃ§os de InventÃ¡rio',
            'Iniciar BalanÃ§os de InventÃ¡rio',
            'Registrar Contagem de BalanÃ§os de InventÃ¡rio',
            'Concluir BalanÃ§os de InventÃ¡rio',
            'Adiar BalanÃ§os de InventÃ¡rio',
            'Cancelar BalanÃ§os de InventÃ¡rio',
            'Acompanhar AvaliaÃ§Ãµes',
            'Exportar RelatÃ³rios',
        ];
    }

    public static function all(): array
    {
        return array_values(array_unique([
            ...static::base(),
            ...static::gestaoEscolar(),
        ]));
    }
}
