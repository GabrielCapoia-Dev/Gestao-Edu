<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CriarPermissoes extends Command
{
    protected $signature = 'permissoes:criar';

    protected $description = 'Cria permissoes base e sincroniza niveis de acesso por contexto';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->basePermissions();
        $permissionGroups = $this->permissionGroups($permissions);
        $rolePresets = $this->rolePresets($permissions, $permissionGroups);

        $this->normalizarPermissoesComMojibake($permissions);
        $this->normalizarNiveisComMojibake(array_keys($rolePresets));

        $this->info('Criando permissoes...');

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $this->line("Criada: {$permissionName}");
            }
        }

        $this->info('Sincronizando niveis de acesso...');

        foreach ($rolePresets as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $this->sincronizarSetorDaRole($role, $roleName);

            $role->syncPermissions($rolePermissions);

            $this->line("Nivel sincronizado: {$roleName} (".count($rolePermissions).' permissoes)');
        }

        $this->sincronizarAdminComTodasAsPermissoes();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Permissoes e niveis de acesso sincronizados com sucesso.');

        return Command::SUCCESS;
    }

    private function basePermissions(): array
    {
        return [
            'Listar Alunos',
            'Listar Relatórios: Professor por Componente e Turma',
            'Listar Relatórios: Componentes com Professores Faltando',
            'Listar Relatórios: Dashboard',
            'Listar Tipo Manutenção',
            'Listar Tipo Status',
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Listar Empresa Contratada',
            'Listar Empresas Inativas',
            'Listar Usuários',
            'Listar Níveis de Acesso',
            'Listar Permissões de Execução',
            'Listar Dominios de Email',
            'Listar Escolas',
            'Listar Turmas',
            'Listar Séries',
            'Listar Professores',
            'Listar Funções Administrativas',
            'Listar Equipe Gestora',
            'Listar Alternativas',
            'Listar Pautas',
            'Listar Avaliações',
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
            'Listar Itens',
            'Listar Componente Curricular',
            'Listar Setores',
            'Criar Empresa Contratada',
            'Criar Alunos',
            'Realizar Transferencia de Aluno',
            'Realizar Remanejamento de Aluno',
            'Gerar Parecer de Transferencia',
            'Notificar Status Pendente',
            'Notificar Impedimento de Matricula por Falta de Transferencia',
            'Gerenciar Impedimento de Matricula por Falta de Transferencia',
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
            'Criar Alternativas',
            'Criar Pautas',
            'Criar Avaliações',
            'Criar Funções Administrativas',
            'Criar Equipe Gestora',
            'Criar Tipos de Avaliações',
            'Criar Contratos',
            'Criar Pedidos: Merenda',
            'Criar Inventários',
            'Criar Pedidos de Inventário',
            'Criar Balanços de Inventário',
            'Criar Balanços de Estoque',
            'Criar Itens',
            'Criar Componente Curricular',
            'Criar Setores',
            'Editar Empresa Contratada',
            'Editar Alunos',
            'Editar Pedidos',
            'Editar Campos da Escola',
            'Editar Codigo da Escola',
            'Editar Escola do Aluno',
            'Editar Escola da Turma',
            'Editar Escola do Usuario',
            'Editar Escola do Professor',
            'Editar Matricula do Professor',
            'Editar Nome do Professor',
            'Editar Especializações de Professores',
            'Editar Dados da Turma',
            'Editar Turmas',
            'Editar Tipo Manutenção',
            'Editar Tipo Status',
            'Editar Setor do Usuário',
            'Editar Usuários',
            'Editar Níveis de Acesso',
            'Editar Permissões de Execução',
            'Editar Dominios de Email',
            'Editar Séries',
            'Editar Escolas',
            'Editar Professores',
            'Editar Equipe Gestora',
            'Editar Funções Administrativas',
            'Editar Alternativas',
            'Editar Pautas',
            'Editar Avaliações',
            'Editar Tipos de Avaliações',
            'Editar Contratos',
            'Editar Pedidos: Merenda',
            'Editar Itens',
            'Editar Componente Curricular',
            'Editar Dados do Professor',
            'Editar Setores',
            'Excluir Empresa Contratada',
            'Excluir Alunos',
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
            'Excluir Alternativas',
            'Excluir Pautas',
            'Excluir Avaliações',
            'Excluir Equipe Gestora',
            'Excluir Funções Administrativas',
            'Excluir Tipos de Avaliações',
            'Excluir Contratos',
            'Excluir Setores',
            'Excluir Componente Curricular',
            'Excluir Pedidos: Merenda',
            'Excluir Itens',
            'Excluir Empresa Contratada em Massa',
            'Excluir Alunos em Massa',
            'Excluir Turmas em Massa',
            'Excluir Professores em Massa',
            'Excluir Tipos de Manutenção em Massa',
            'Excluir Tipo Status em Massa',
            'Excluir Pedidos em Massa',
            'Excluir Alternativas em Massa',
            'Excluir Equipe Gestora em Massa',
            'Excluir Funções Administrativas em Massa',
            'Excluir Tipos de Avaliações em Massa',
            'Excluir Setores em Massa',
            'Excluir Itens em Massa',
            'Exportar Alunos',
            'Exportar Turmas',
            'Exportar Escolas',
            'Exportar Relatórios',
            'Exportar Avaliações',
            'Exportar Professores',
            'Exportar Arquivos Pedido',
            'Exportar Componente Curricular',
            'Visualizar Detalhes de Aluno',
            'Visualizar Professores',
            'Visualizar Usuarios Online',
            'Visualizar Notificações',
            'Criar Notificações',
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
            'Visualizar Tela de Inicio',
            'Acessar Painel',
            'Baixar App',
            'Filtrar Alunos por Escola',
            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Aplicar Permissoes',
            'Avaliar Pedidos',
            'Encaminhar Pedidos para Setor',
            'Enviar Pedidos para Empresa',
            'Vincular Pedidos Adicionais',
            'Responder Avaliações',
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
    }

    private function permissionGroups(array $permissions): array
    {
        return [
            'acesso' => $this->onlyPermissions($permissions, [
                'Listar Usuários',
                'Criar Usuários',
                'Editar Usuários',
                'Excluir Usuários',
                'Listar Níveis de Acesso',
                'Criar Níveis de Acesso',
                'Editar Níveis de Acesso',
                'Excluir Níveis de Acesso',
                'Listar Permissões de Execução',
                'Criar Permissões de Execução',
                'Editar Permissões de Execução',
                'Excluir Permissões de Execução',
                'Listar Dominios de Email',
                'Criar Dominios de Email',
                'Editar Dominios de Email',
                'Excluir Dominios de Email',
                'Editar Escola do Usuario',
                'Editar Setor do Usuário',
                'Visualizar Setor do Usuário',
                'Visualizar Usuarios Online',
                'Listar Setores',
                'Criar Setores',
                'Editar Setores',
                'Excluir Setores',
                'Excluir Setores em Massa',
                'Aplicar Permissoes',
            ]),
            'alunos' => $this->onlyPermissions($permissions, [
                'Listar Alunos',
                'Criar Alunos',
                'Editar Alunos',
                'Excluir Alunos',
                'Excluir Alunos em Massa',
                'Exportar Alunos',
                'Visualizar Detalhes de Aluno',
                'Filtrar Alunos por Escola',
                'Realizar Transferencia de Aluno',
                'Realizar Remanejamento de Aluno',
                'Gerar Parecer de Transferencia',
                'Notificar Status Pendente',
                'Notificar Impedimento de Matricula por Falta de Transferencia',
                'Gerenciar Impedimento de Matricula por Falta de Transferencia',
            ]),
            'professores_e_turmas' => $this->onlyPermissions($permissions, [
                'Listar Escolas',
                'Criar Escolas',
                'Editar Escolas',
                'Editar Campos da Escola',
                'Editar Codigo da Escola',
                'Excluir Escolas',
                'Exportar Escolas',
                'Listar Turmas',
                'Criar Turmas',
                'Editar Turmas',
                'Editar Dados da Turma',
                'Editar Escola da Turma',
                'Excluir Turmas',
                'Excluir Turmas em Massa',
                'Exportar Turmas',
                'Filtrar Turmas por Escola',
                'Listar Séries',
                'Criar Séries',
                'Editar Séries',
                'Excluir Séries',
                'Listar Professores',
                'Criar Professores',
                'Editar Professores',
                'Editar Escola do Professor',
                'Editar Matricula do Professor',
                'Editar Nome do Professor',
                'Editar Especializações de Professores',
                'Editar Dados do Professor',
                'Excluir Professores',
                'Excluir Professores em Massa',
                'Exportar Professores',
                'Visualizar Professores',
                'Visualizar Especializações de Professores',
                'Visualizar Detalhes de Professor',
                'Filtrar Professores por Escola',
                'Filtrar Professores por Componente',
                'Filtrar Professores por Serie',
                'Listar Alternativas',
                'Criar Alternativas',
                'Editar Alternativas',
                'Excluir Alternativas',
                'Listar Pautas',
                'Criar Pautas',
                'Editar Pautas',
                'Excluir Pautas',
                'Listar Avaliações',
                'Criar Avaliações',
                'Editar Avaliações',
                'Excluir Avaliações',
            ]),
            'pedidos' => $this->onlyPermissions($permissions, [
                'Listar Pedidos',
                'Listar Todos os Pedidos',
                'Listar Tipo Manutenção',
                'Listar Tipo Status',
                'Criar Pedidos',
                'Criar Tipo Manutenção',
                'Criar Tipo Status',
                'Editar Pedidos',
                'Editar Tipo Manutenção',
                'Editar Tipo Status',
                'Excluir Pedidos',
                'Excluir Pedidos em Massa',
                'Excluir Tipo Manutenção',
                'Excluir Tipos de Manutenção em Massa',
                'Excluir Tipo Status',
                'Excluir Tipo Status em Massa',
                'Exportar Arquivos Pedido',
                'Visualizar Status: Encaminhado ao Setor',
                'Visualizar Histórico de Pedidos',
                'Visualizar Arquivos de Pedidos',
                'Visualizar Pedidos por Status',
                'Visualizar Feedback de Pedidos',
                'Visualizar Notificação: Vencimento de Pedidos',
                'Visualizar Notificação: Pedidos Atrasados',
                'Visualizar Notificação: Pedidos Emergenciais',
                'Visualizar Notificação: Pedido Reaberto',
                'Avaliar Pedidos',
                'Encaminhar Pedidos para Setor',
                'Enviar Pedidos para Empresa',
                'Vincular Pedidos Adicionais',
            ]),
            'merenda' => $this->onlyPermissions($permissions, [
                'Listar Pedidos: Merenda',
                'Criar Pedidos: Merenda',
                'Editar Pedidos: Merenda',
                'Excluir Pedidos: Merenda',
            ]),
            'inventario' => $this->onlyPermissions($permissions, [
                'Listar Inventários',
                'Criar Inventários',
                'Listar Gestão de Inventário',
                'Listar Pedidos de Inventário',
                'Criar Pedidos de Inventário',
                'Aprovar Pedidos de Inventário',
                'Gerar Romaneios de Inventário',
                'Conferir Pedidos de Inventário',
                'Listar Balanços de Inventário',
                'Criar Balanços de Inventário',
                'Iniciar Balanços de Inventário',
                'Registrar Contagem de Balanços de Inventário',
                'Concluir Balanços de Inventário',
                'Adiar Balanços de Inventário',
                'Cancelar Balanços de Inventário',
            ]),
            'estoque' => $this->onlyPermissions($permissions, [
                'Listar Gestão de Estoque',
                'Listar Balanços de Estoque',
                'Criar Balanços de Estoque',
                'Listar Gestão de Margens',
                'Iniciar Balanços de Estoque',
                'Registrar Contagem de Balanços de Estoque',
                'Concluir Balanços de Estoque',
                'Adiar Balanços de Estoque',
                'Cancelar Balanços de Estoque',
                'Visualizar Notificação: Balanço de Estoque',
            ]),
            'cadastros_gerais' => $this->onlyPermissions($permissions, [
                'Listar Empresa Contratada',
                'Listar Empresas Inativas',
                'Criar Empresa Contratada',
                'Editar Empresa Contratada',
                'Excluir Empresa Contratada',
                'Excluir Empresa Contratada em Massa',
                'Listar Contratos',
                'Criar Contratos',
                'Editar Contratos',
                'Excluir Contratos',
                'Listar Componente Curricular',
                'Criar Componente Curricular',
                'Editar Componente Curricular',
                'Excluir Componente Curricular',
                'Exportar Componente Curricular',
                'Listar Funções Administrativas',
                'Criar Funções Administrativas',
                'Editar Funções Administrativas',
                'Excluir Funções Administrativas',
                'Excluir Funções Administrativas em Massa',
                'Listar Equipe Gestora',
                'Criar Equipe Gestora',
                'Editar Equipe Gestora',
                'Excluir Equipe Gestora',
                'Excluir Equipe Gestora em Massa',
                'Listar Alternativas',
                'Criar Alternativas',
                'Editar Alternativas',
                'Excluir Alternativas',
                'Excluir Alternativas em Massa',
                'Listar Pautas',
                'Criar Pautas',
                'Editar Pautas',
                'Excluir Pautas',
                'Listar Avaliações',
                'Criar Avaliações',
                'Editar Avaliações',
                'Excluir Avaliações',
                'Listar Tipos de Avaliações',
                'Criar Tipos de Avaliações',
                'Editar Tipos de Avaliações',
                'Excluir Tipos de Avaliações',
                'Excluir Tipos de Avaliações em Massa',
                'Excluir Itens',
                'Excluir Itens em Massa',
            ]),
            'relatorios_e_painel' => $this->onlyPermissions($permissions, [
                'Listar Relatórios: Professor por Componente e Turma',
                'Listar Relatórios: Componentes com Professores Faltando',
                'Listar Relatórios: Dashboard',
                'Exportar Relatórios',
                'Exportar Avaliações',
                'Visualizar Notificações',
                'Visualizar Painel Personalizado',
                'Visualizar Tela de Inicio',
                'Baixar App',
            ]),
            'acesso_painel' => $this->onlyPermissions($permissions, [
                'Visualizar Tela de Inicio',
            ]),
            'visualizacao_turmas_alunos' => $this->onlyPermissions($permissions, [
                'Listar Turmas',
                'Listar Alunos',
                'Responder Avaliações',
            ]),
            'administrativo' => $this->onlyPermissions($permissions, [
                'Listar Pedidos',
                'Listar Todos os Pedidos',
                'Listar Tipo Manutenção',
                'Criar Tipo Manutenção',
                'Editar Pedidos',
                'Editar Tipo Manutenção',
                'Excluir Tipo Manutenção',
                'Excluir Tipos de Manutenção em Massa',
            ]),
        ];
    }

    private function rolePresets(array $permissions, array $groups): array
    {
        return [
            'Admin' => $permissions,
            'Secretário' => $this->mergeGroups($groups, [
                'alunos',
                'professores_e_turmas',
                'pedidos',
                'relatorios_e_painel',
                'inventario',
            ]),
            'Administrativo' => $groups['administrativo'],
            'Gestao de Usuarios e Acessos' => $groups['acesso'],
            'Gestao Pedagogica' => $this->mergeGroups($groups, [
                'alunos',
                'professores_e_turmas',
                'relatorios_e_painel',
            ]),
            'Gestao de Pedidos' => $this->mergeGroups($groups, [
                'pedidos',
                'relatorios_e_painel',
            ]),
            'Manutenção: Educação' => $this->onlyPermissions($permissions, [
                'Listar Pedidos',
                'Criar Pedidos',
                'Editar Pedidos',
                'Avaliar Pedidos',
                'Encaminhar Pedidos para Setor',
                'Vincular Pedidos Adicionais',
                'Listar Tipo ManutenÃ§Ã£o',
                'Visualizar HistÃ³rico de Pedidos',
                'Visualizar Arquivos de Pedidos',
                'Visualizar Pedidos por Status',
                'Visualizar Feedback de Pedidos',
                'Exportar Arquivos Pedido',
                'Exportar RelatÃ³rios',
            ]),
            'Manutenção: Obras' => $this->onlyPermissions($permissions, [
                'Listar Pedidos',
                'Editar Pedidos',
                'Enviar Pedidos para Empresa',
                'Vincular Pedidos Adicionais',
                'Listar Tipo ManutenÃ§Ã£o',
                'Visualizar HistÃ³rico de Pedidos',
                'Visualizar Arquivos de Pedidos',
                'Visualizar Pedidos por Status',
                'Visualizar Feedback de Pedidos',
                'Exportar Arquivos Pedido',
                'Exportar RelatÃ³rios',
            ]),
            'Gestao de Merenda' => $groups['merenda'],
            'Gestao de Inventario' => $this->mergeGroups($groups, [
                'inventario',
                'relatorios_e_painel',
            ]),
            'Gestao de Estoque' => $this->mergeGroups($groups, [
                'estoque',
                'relatorios_e_painel',
            ]),
            'Gestao de Cadastros Gerais' => $groups['cadastros_gerais'],
            'Relatorios e Painel' => $groups['relatorios_e_painel'],
            'Acessar Painel' => $groups['acesso_painel'],
            'Professor' => $groups['visualizacao_turmas_alunos'],
            'Visualizar Turmas e Alunos' => $groups['visualizacao_turmas_alunos'],
        ];
    }

    private function mergeGroups(array $groups, array $groupNames): array
    {
        $merged = [];

        foreach ($groupNames as $groupName) {
            $merged = [...$merged, ...($groups[$groupName] ?? [])];
        }

        return array_values(array_unique($merged));
    }

    private function sincronizarSetorDaRole(Role $role, string $roleName): void
    {
        $setor = match ($roleName) {
            'Manutenção: Educação' => $this->setorIdPorNome('Educação'),
            'Manutenção: Obras' => $this->setorIdPorNome('Obras'),
            default => null,
        };

        if ($setor || $role->setor_id) {
            $role->forceFill(['setor_id' => $setor])->save();
        }
    }

    private function setorIdPorNome(string $nome): ?int
    {
        $aliases = [$nome];

        if (function_exists('mb_convert_encoding')) {
            $aliases[] = mb_convert_encoding($nome, 'UTF-8', 'ISO-8859-1');

            if (str_contains($nome, 'Ã') || str_contains($nome, 'Â')) {
                $aliases[] = mb_convert_encoding($nome, 'ISO-8859-1', 'UTF-8');
            }
        }

        return DB::table('setor')
            ->whereIn('nome', array_values(array_unique($aliases)))
            ->value('id');
    }

    private function onlyPermissions(array $permissions, array $selectedPermissions): array
    {
        $aliases = [];

        foreach ($selectedPermissions as $permission) {
            $aliases[] = $permission;

            if (function_exists('mb_convert_encoding')) {
                $aliases[] = mb_convert_encoding($permission, 'UTF-8', 'ISO-8859-1');

                if (str_contains($permission, 'Ã') || str_contains($permission, 'Â')) {
                    $aliases[] = mb_convert_encoding($permission, 'ISO-8859-1', 'UTF-8');
                }
            }
        }

        return array_values(array_intersect($permissions, array_values(array_unique($aliases))));
    }

    private function normalizarPermissoesComMojibake(array $permissions): void
    {
        foreach ($this->aliasesComMojibake($permissions) as $legacyName => $correctName) {
            $legacyPermission = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', $legacyName)
                ->first();

            if (! $legacyPermission) {
                continue;
            }

            $correctPermission = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', $correctName)
                ->first();

            if (! $correctPermission) {
                $legacyPermission->forceFill(['name' => $correctName])->save();

                continue;
            }

            if ($legacyPermission->is($correctPermission)) {
                continue;
            }

            DB::transaction(function () use ($legacyPermission, $correctPermission): void {
                foreach (DB::table('role_has_permissions')->where('permission_id', $legacyPermission->id)->get() as $row) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $correctPermission->id,
                        'role_id' => $row->role_id,
                    ]);
                }

                foreach (DB::table('model_has_permissions')->where('permission_id', $legacyPermission->id)->get() as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $correctPermission->id,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ]);
                }

                DB::table('role_has_permissions')->where('permission_id', $legacyPermission->id)->delete();
                DB::table('model_has_permissions')->where('permission_id', $legacyPermission->id)->delete();
                $legacyPermission->delete();
            });
        }
    }

    private function normalizarNiveisComMojibake(array $roles): void
    {
        foreach ($this->aliasesComMojibake($roles) as $legacyName => $correctName) {
            $legacyRole = Role::query()
                ->where('guard_name', 'web')
                ->where('name', $legacyName)
                ->first();

            if (! $legacyRole) {
                continue;
            }

            $correctRole = Role::query()
                ->where('guard_name', 'web')
                ->where('name', $correctName)
                ->first();

            if (! $correctRole) {
                $legacyRole->forceFill(['name' => $correctName])->save();

                continue;
            }

            if ($legacyRole->is($correctRole)) {
                continue;
            }

            DB::transaction(function () use ($legacyRole, $correctRole): void {
                foreach (DB::table('role_has_permissions')->where('role_id', $legacyRole->id)->get() as $row) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $row->permission_id,
                        'role_id' => $correctRole->id,
                    ]);
                }

                foreach (DB::table('model_has_roles')->where('role_id', $legacyRole->id)->get() as $row) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $correctRole->id,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ]);
                }

                DB::table('role_has_permissions')->where('role_id', $legacyRole->id)->delete();
                DB::table('model_has_roles')->where('role_id', $legacyRole->id)->delete();
                $legacyRole->delete();
            });
        }
    }

    private function aliasesComMojibake(array $names): array
    {
        $aliases = [];

        foreach ($names as $name) {
            foreach ($this->gerarAliasesComMojibake($name) as $legacyName) {
                $aliases[$legacyName] = $name;
            }
        }

        foreach ($this->aliasesManuais() as $legacyName => $correctName) {
            if (in_array($correctName, $names, true)) {
                $aliases[$legacyName] = $correctName;
            }
        }

        return $aliases;
    }

    private function aliasesManuais(): array
    {
        return [
            "Realizar Transfer\u{00EA}ncia de Aluno" => 'Realizar Transferencia de Aluno',
            'Realizar Tranferencia de Aluno' => 'Realizar Transferencia de Aluno',
            "Gerar Parecer de Transfer\u{00EA}ncia" => 'Gerar Parecer de Transferencia',
            "Notificar Status Pendente" => 'Notificar Status Pendente',
            "Notificar Impedimento de Matr\u{00ED}cula por Falta de Transfer\u{00EA}ncia" => 'Notificar Impedimento de Matricula por Falta de Transferencia',
            "Gerenciar Impedimento de Matr\u{00ED}cula por Falta de Transfer\u{00EA}ncia" => 'Gerenciar Impedimento de Matricula por Falta de Transferencia',
        ];
    }

    private function gerarAliasesComMojibake(string $name): array
    {
        if (! function_exists('mb_convert_encoding')) {
            return [];
        }

        $legacyName = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');

        return collect([
            $legacyName,
            str_replace("\u{00C3}\u{00A3}o", "\u{00C3}o", $legacyName),
        ])
            ->filter(fn (string $alias): bool => $alias !== $name)
            ->unique()
            ->values()
            ->all();
    }

    private function sincronizarAdminComTodasAsPermissoes(): void
    {
        $admin = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->values()
            ->all();

        $admin->syncPermissions($allPermissions);

        $this->line('Nivel sincronizado: Admin ('.count($allPermissions).' permissoes totais)');
    }
}
