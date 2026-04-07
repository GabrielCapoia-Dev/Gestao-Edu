<?php

namespace App\Console\Commands;

use App\Support\SecretarioPermissionPreset;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CriarPermissoes extends Command
{
    protected $signature = 'permissoes:criar';

    protected $description = 'Cria permissões e vincula à role Admin';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissoes = [
            // LISTAR
            'Listar Alunos',
            'Listar Relatórios: Professor por Componente e Turma',
            'Listar Relatórios: Componentes com Professores Faltando',
            'Listar Relatórios: Dashboard',
            'Listar Retenção',
            'Listar Tipo Manutenção',
            'Listar Tipo Status',
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Listar Empresa Contratada',
            'Listar Usuários',
            'Listar Níveis de Acesso',
            'Listar Permissões de Execução',
            'Listar Dominios de Email',
            'Listar Escolas',
            'Listar Turmas',
            'Listar Séries',
            'Listar Professores',
            'Listar Laudos',
            'Listar Funções Administrativas',
            'Listar Equipe Gestora',
            'Listar Alternativas',
            'Listar Tipos de Avaliações',
            'Listar Contratos',
            'Listar Pedidos: Merenda',
            'Listar Inventários',
            'Listar Gestão de Inventário',
            'Listar Pedidos de Inventário',
            'Listar Balanços de Inventário',
            'Listar Gestão de Estoque',
            'Listar Balanços de Estoque',
            'Listar Gestão de Margens',
            'Listar Componente Curricular',

            // CRIAR
            'Criar Empresa Contratada',
            'Criar Alunos',
            'Criar Tipo Manutenção',
            'Criar Tipo Status',
            'Criar Pedidos',
            'Criar Usuários',
            'Criar Níveis de Acesso',
            'Criar Permissões de Execução',
            'Criar Dominios de Email',
            'Criar Séries',
            'Criar Escolas',
            'Criar Turmas',
            'Criar Professores',
            'Criar Laudos',
            'Criar Alternativas',
            'Criar Funções Administrativas',
            'Criar Equipe Gestora',
            'Criar Tipos de Avaliações',
            'Criar Contratos',
            'Criar Pedidos: Merenda',
            'Criar Inventários',
            'Criar Pedidos de Inventário',
            'Criar Balanços de Inventário',
            'Criar Balanços de Estoque',
            'Criar Componente Curricular',

            // EDITAR
            'Editar Empresa Contratada',
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
            'Editar Usuários',
            'Editar Níveis de Acesso',
            'Editar Permissões de Execução',
            'Editar Dominios de Email',
            'Editar Séries',
            'Editar Escolas',
            'Editar Turmas',
            'Editar Laudos',
            'Editar Professores',
            'Editar Equipe Gestora',
            'Editar Funções Administrativas',
            'Editar Alternativas',
            'Editar Tipos de Avaliações',
            'Editar Contratos',
            'Editar Pedidos: Merenda',
            'Editar Componente Curricular',
            'Editar Dados do Professor',

            // EXCLUIR
            'Excluir Empresa Contratada',
            'Excluir Alunos',
            'Excluir Laudos',
            'Excluir Tipo Manutenção',
            'Excluir Tipo Status',
            'Excluir Pedidos',
            'Excluir Usuários',
            'Excluir Níveis de Acesso',
            'Excluir Permissões de Execução',
            'Excluir Dominios de Email',
            'Excluir Séries',
            'Excluir Escolas',
            'Excluir Turmas',
            'Excluir Professores',
            'Excluir Laudos de Aluno',
            'Excluir Alternativas',
            'Excluir Equipe Gestora',
            'Excluir Funções Administrativas',
            'Excluir Tipos de Avaliações',
            'Excluir Contratos',
            'Excluir Componente Curricular',
            'Excluir Pedidos: Merenda',
            'Excluir Itens',

            // EXCLUIR EM MASSA
            'Excluir Empresa Contratada em Massa',
            'Excluir Alunos em Massa',
            'Excluir Laudos em Massa',
            'Excluir Turmas em Massa',
            'Excluir Professores em Massa',
            'Excluir Tipos de Manutenção em Massa',
            'Excluir Tipo Status em Massa',
            'Excluir Pedidos em Massa',
            'Excluir Alternativas em Massa',
            'Excluir Equipe Gestora em Massa',
            'Excluir Funções Administrativas em Massa',
            'Excluir Tipos de Avaliações em Massa',
            'Excluir Itens em Massa',

            // EXPORTAR
            'Exportar Alunos',
            'Exportar Turmas',
            'Exportar Escolas',
            'Exportar Relatórios',
            'Exportar Professores',
            'Exportar Arquivos Pedido',
            'Exportar Laudos de Aluno',
            'Exportar Relatório de Alunos',
            'Exportar Componente Curricular',

            // VISUALIZAR
            'Visualizar Histórico dos Alunos',
            'Visualizar Professores',
            'Visualizar Notificações',
            'Visualizar Detalhes de Aluno',
            'Visualizar Especializações de Professores',
            'Visualizar Detalhes de Professor',
            'Visualizar Setor do Usuário',
            'Visualizar Status: Encaminhado ao Setor',
            'Visualizar Histórico de Pedidos',
            'Visualizar Arquivos de Pedidos',
            'Visualizar Pedidos por Status',
            'Visualizar Painel Personalizado',
            'Visualizar Feedback de Pedidos',
            'Visualizar Notificação: Vencimento de Pedidos',
            'Visualizar Notificação: Pedidos Atrasados',
            'Visualizar Notificação: Pedidos Emergenciais',
            'Visualizar Notificação: Pedido Reaberto',
            'Visualizar Notificação: Balanço de Estoque',
            'Visualizar Laudos de Aluno',
            'Visualizar Tela de Inicio',

            // FILTROS
            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Filtrar Alunos por Escola',

            // OUTROS
            'Aplicar Permissoes',
            'Anexar Laudos de Aluno',
            'Avaliar Pedidos',
            'Aprovar Pedidos de Inventário',
            'Gerar Romaneios de Inventário',
            'Conferir Pedidos de Inventário',
            'Iniciar Balanços de Inventário',
            'Registrar Contagem de Balanços de Inventário',
            'Concluir Balanços de Inventário',
            'Adiar Balanços de Inventário',
            'Cancelar Balanços de Inventário',
            'Iniciar Balanços de Estoque',
            'Registrar Contagem de Balanços de Estoque',
            'Concluir Balanços de Estoque',
            'Adiar Balanços de Estoque',
            'Cancelar Balanços de Estoque',
        ];

        $this->info('Criando permissões...');

        foreach ($permissoes as $nome) {
            $permission = Permission::firstOrCreate(['name' => $nome]);

            if ($permission->wasRecentlyCreated) {
                $this->line("✔ Criada: {$nome}");
            }
        }

        $adminRole = Role::where('name', 'Admin')->first();

        if (! $adminRole) {
            $this->error('Role Admin não encontrada.');

            return Command::FAILURE;
        }

        $adminRole->givePermissionTo($permissoes);
        Role::firstOrCreate(['name' => 'Secretário'])
            ->syncPermissions(SecretarioPermissionPreset::all());

        $this->info('Permissões vinculadas à role Admin com sucesso.');

        return Command::SUCCESS;
    }
}
