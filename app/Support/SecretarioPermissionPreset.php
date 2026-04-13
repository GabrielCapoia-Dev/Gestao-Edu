<?php

namespace App\Support;

class SecretarioPermissionPreset
{
    public static function base(): array
    {
        return [
            'Listar Alunos',
            'Listar Pedidos',
            'Listar Turmas',
            'Listar Professores',
            'Criar Alunos',
            'Criar Pedidos',
            'Criar Turmas',
            'Criar Professores',
            'Editar Alunos',
            'Editar Nome do Professor',
            'Editar Especializações de Professores',
            'Editar Turmas',
            'Editar Professores',
            'Excluir Alunos',
            'Exportar Alunos',
            'Exportar Turmas',
            'Exportar Professores',
            'Filtrar Alunos por Escola',
            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Visualizar Detalhes de Aluno',
            'Visualizar Especializações de Professores',
            'Visualizar Detalhes de Professor',
            'Visualizar Notificações',
            'Visualizar Histórico de Pedidos',
            'Visualizar Arquivos de Pedidos',
            'Visualizar Notificação: Pedido Reaberto',
            'Avaliar Pedidos',
        ];
    }

    public static function gestaoEscolar(): array
    {
        return [
            'Listar Gestão de Inventário',
            'Listar Pedidos de Inventário',
            'Criar Pedidos de Inventário',
            'Conferir Pedidos de Inventário',
            'Listar Balanços de Inventário',
            'Criar Balanços de Inventário',
            'Iniciar Balanços de Inventário',
            'Registrar Contagem de Balanços de Inventário',
            'Concluir Balanços de Inventário',
            'Adiar Balanços de Inventário',
            'Cancelar Balanços de Inventário',
            'Exportar Relatórios',
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
