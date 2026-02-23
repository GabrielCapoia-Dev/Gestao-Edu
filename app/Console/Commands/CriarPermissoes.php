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

            'Listar Alunos',
            'Listar Relatórios',
            'Listar Retenção',
            'Listar Tipo Manutenção',
            'Listar Tipo Status',
            'Listar Pedidos',

            'Criar Alunos',
            'Criar Tipo Manutenção',
            'Criar Tipo Status',
            'Criar Pedidos',

            'Editar Alunos',
            'Editar Pedidos',
            'Editar Escola do Aluno',
            'Editar Campos da Escola',
            'Editar Codigo da Escola',
            'Editar Escola da Turma',
            'Editar Escola do Usuario',
            'Editar Escola do Professor',
            'Editar Matricula do Professor',
            'Editar Nome do Professor',
            'Editar Especializações de Professores',
            'Editar Dados da Turma',
            'Editar Turma do Aluno',
            'Editar Status do Aluno',
            'Editar CGM do Aluno',
            'Editar Tipo Manutenção',
            'Editar Tipo Status',
            'Editar Setor do Usuário',

            'Excluir Alunos',
            'Excluir Laudos',
            'Excluir Tipo Manutenção',
            'Excluir Tipo Status',
            'Excluir Pedidos',

            'Excluir Alunos em Massa',
            'Excluir Laudos em Massa',
            'Excluir Turmas em Massa',
            'Excluir Professores em Massa',
            'Excluir Tipos de Manutenção em Massa',
            'Excluir Tipo Status em Massa',
            'Excluir Pedidos em Massa',


            'Exportar Alunos',
            'Exportar Turmas',
            'Exportar Escolas',
            'Exportar Relatórios',
            'Exportar Professores',

            'Aplicar Permissoes',

            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Filtrar Alunos por Escola',

            'Visualizar Detalhes de Aluno',
            'Visualizar Especializações de Professores',
            'Visualizar Detalhes de Professor',
            'Visualizar Setor do Usuário',

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
