<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CriarPermissoes extends Command
{
    protected $signature = 'permissoes:criar';

    protected $description = 'Cria permissões vincular à role';

    public function handle(): int
    {
        // Limpa cache de permissões
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissoes = [
            'Excluir Itens em Massa',
            'Excluir Itens',
            'Visualizar Histórico dos Alunos',
            'Visualizar Professores',

            'Listar Alunos',
            'Listar Relatórios',
            'Listar Funções Administrativas',
            'Listar Equipe Gestora',
            'Listar Alternativas',
            'Listar Tipos de Avaliações',

            'Criar Alunos',
            'Criar Alternativas',
            'Criar Funções Administrativas',
            'Criar Equipe Gestora',
            'Criar Tipos de Avaliações',

            'Editar Alunos',
            'Editar Escola do Aluno',
            'Editar Campos da Escola',
            'Editar Escola da Turma',
            'Editar Escola do Professor',
            'Editar Matricula do Professor',
            'Editar Nome do Professor',
            'Editar Dados do Professor',
            'Editar Dados da Turma',
            'Editar Turma do Aluno',
            'Editar Status do Aluno',
            'Editar Equipe Gestora',
            'Editar Funções Administrativas',
            'Editar CGM do Aluno',
            'Editar Alternativas',
            'Editar Tipos de Avaliações',

            'Excluir Alunos',
            'Excluir Alternativas',
            'Excluir Equipe Gestora',
            'Excluir Funções Administrativas',
            'Excluir Tipos de Avaliações',

            'Excluir Alunos em Massa',
            'Excluir Turmas em Massa',
            'Excluir Alternativas em Massa',
            'Excluir Equipe Gestora em Massa',
            'Excluir Funções Administrativas em Massa',
            'Excluir Tipos de Avaliações em Massa',
            'Excluir Professores em Massa',

            'Exportar Alunos',
            'Exportar Turmas',
            'Exportar Escolas',
            'Exportar Relatórios',
            'Exportar Professores',
            'Exportar Componentes',

            'Aplicar Permissoes',

            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Filtrar Alunos da Escola',
        ];

        $this->info('Criando permissões...');

        foreach ($permissoes as $nome) {

            $permission = Permission::firstOrCreate(['name' => $nome]);

            if ($permission->wasRecentlyCreated) {
                $this->line("✔ Criada: {$nome}");
            }
        }


        $adminRole = Role::where('name', 'Admin')->first();

        if (!$adminRole) {
            $this->error('Role Admin não encontrada.');
            return Command::FAILURE;
        }

        $adminRole->givePermissionTo($permissoes);

        $this->info('Permissões vinculadas à role Admin com sucesso ✅');

        return Command::SUCCESS;
    }
}
