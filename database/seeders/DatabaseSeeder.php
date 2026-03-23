<?php

namespace Database\Seeders;

use App\Models\DominioEmail;
use App\Models\Laudo;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Serie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Limpa cache das permissões do Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista de permissões que serão atribuídas à role Admin
        $permissionsList = [

            'Listar Alunos',
            'Listar Relatórios',
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
            'Listar Alunos',
            'Listar Professores',
            'Listar Laudos',

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
            'Criar Alunos',
            'Criar Professores',
            'Criar Laudos',


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
            'Editar Alunos',
            'Editar Laudos',
            'Editar Professores',


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
            'Excluir Alunos',
            'Excluir Laudos',
            'Excluir Professores',
            'Excluir Laudos de Aluno',


            'Excluir Empresa Contratada em Massa',
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
            'Exportar Arquivos Pedido',
            'Exportar Laudos de Aluno',
            'Exportar Relatório de Alunos',

            'Aplicar Permissoes',

            'Anexar Laudos de Aluno',

            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Filtrar Alunos por Escola',

            'Visualizar Detalhes de Aluno',
            'Visualizar Especializações de Professores',
            'Visualizar Detalhes de Professor',
            'Visualizar Setor do Usuário',
            'Visualizar Status: Encaminhado ao Setor',
            'Visualizar Notificações',
            'Visualizar Histórico de Pedidos',
            'Visualizar Arquivos de Pedidos',
            'Visualizar Pedidos por Status',
            'Visualizar Painel Personalizado',
            'Visualizar Feedback de Pedidos',
            'Visualizar Notificação: Vencimento de Pedidos',
            'Visualizar Notificação: Pedidos Atrasados',
            'Visualizar Notificação: Pedidos Emergenciais',
            'Visualizar Notificação: Pedido Reaberto',
            'Visualizar Laudos de Aluno',


            'Avaliar Pedidos',

        ];

        $permissionsSecretario = [

            'Listar Alunos',
            'Listar Retenção',
            'Listar Pedidos',
            'Listar Turmas',
            'Listar Alunos',
            'Listar Professores',

            'Criar Alunos',
            'Criar Pedidos',
            'Criar Turmas',
            'Criar Alunos',
            'Criar Professores',


            'Editar Alunos',
            'Editar Nome do Professor',
            'Editar Especializações de Professores',
            'Editar Turmas',
            'Editar Alunos',
            'Editar Professores',


            'Exportar Alunos',
            'Exportar Turmas',
            'Exportar Professores',
            'Exportar Laudos de Aluno',
            'Exportar Relatório de Alunos',

            'Anexar Laudos de Aluno',

            'Filtrar Professores por Escola',
            'Filtrar Professores por Componente',
            'Filtrar Professores por Serie',
            'Filtrar Turmas por Escola',
            'Filtrar Alunos por Escola',

            'Visualizar Detalhes de Aluno',
            'Visualizar Especializações de Professores',
            'Visualizar Detalhes de Professor',
            'Visualizar Notificações',
            'Visualizar Histórico de Pedidos',
            'Visualizar Arquivos de Pedidos',
            'Visualizar Notificação: Pedido Reaberto',
            'Visualizar Laudos de Aluno',

            'Avaliar Pedidos',
        ];

        $password = "Senha@123";

        // Criação das permissões
        foreach ($permissionsList as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Criação das roles
        $adminRole          = Role::firstOrCreate(['name' => 'Admin']);
        $secretarioRole     = Role::firstOrCreate(['name' => 'Secretário']);
        $administrativoRole = Role::firstOrCreate(['name' => 'Administrativo']);

        // Atribui todas as permissões à role Admin
        $adminRole->syncPermissions($permissionsList);
        $secretarioRole->syncPermissions($permissionsSecretario);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'codigo' => 100,
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );
        $secretarioUser = User::firstOrCreate(
            ['email' => 'secretario@secretario.com'],
            [
                'codigo' => 101,
                'name' => 'Secretário',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );

        $adminUser->syncRoles([$adminRole]);
        $secretarioUser->syncRoles([$secretarioRole]);

        // Executa command que cria permissões adicionais e vincula ao Admin
        Artisan::call('permissoes:criar');

        $this->command->info(Artisan::output());

        $permissionsAdministrativo = [
            'Listar Pedidos',
            'Listar Tipo Manutenção',
            'Listar Todos os Pedidos',
            'Criar Tipo Manutenção',
            'Editar Pedidos',
            'Editar Tipo Manutenção',
            'Excluir Tipo Manutenção',
            'Excluir Tipos de Manutenção em Massa',
        ];

        $administrativoRole->syncPermissions($permissionsAdministrativo);

        $administrativoUser = User::firstOrCreate(
            ['email' => 'administrativo@administrativo.com'],
            [
                'codigo' => 102,
                'name' => 'Administrativo',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );

        $administrativoUser->syncRoles([$administrativoRole]);

        /**
         * Domínios de email — usando firstOrCreate para evitar duplicata
         */
        $dominios = [
            ['dominio' => 'gmail.com',                  'setor' => 'Geral'],
            ['dominio' => 'edu.umuarama.pr.gov.br',     'setor' => 'Educação'],
            ['dominio' => 'umuarama.pr.gov.br',         'setor' => 'Administrativo'],
        ];

        foreach ($dominios as $item) {
            DominioEmail::firstOrCreate(
                ['dominio_email' => $item['dominio']],
                ['setor' => $item['setor'], 'status' => 1]
            );
        }

        // /**
        //  * Laudos
        //  */
        // $laudoList = [
        //     'Deficiência Intelectual',
        //     'Transtorno do Espectro Autista (TEA)',
        //     'Deficiência Física/Motora',
        //     'Deficiência Visual',
        //     'Cegueira',
        //     'Baixa Visão',
        //     'Visão Monocular',
        //     'Deficiência Auditiva',
        //     'Surdez',
        //     'Surdocegueira',
        //     'Deficiência Múltipla',
        //     'Altas Habilidades/Superdotação',
        //     'Transtorno de Déficit de Atenção e Hiperatividade (TDAH)',
        //     'Transtorno Opositor Desafiador (TOD)',
        //     'Transtornos Específicos da Aprendizagem (Dislexia, Discalculia, Disgrafia, etc.)',
        //     'Transtornos da Comunicação (linguagem, fala, fluência, etc.)',
        //     'Transtornos Motores do Neurodesenvolvimento (dispraxia, coordenação motora, etc.)',
        //     'Atraso Global do Desenvolvimento',
        //     'Atraso no Desenvolvimento Neuropsicomotor',
        //     'Transtornos Mentais e do Comportamento com impacto funcional significativo',
        // ];

        // foreach ($laudoList as $laudo) {
        //     Laudo::firstOrCreate(['nome' => $laudo]);
        // }


        $this->call([
            // EscolaSeeder::class,
            // SecretarioUnidadesSeeder::class,
            // // AlunoPlanilhaSeeder::class,
            // // TurmaSeeder::class,
            // // ProfessorSeeder::class,
            // // AlunoSeeder::class,

            SetorSeeder::class,
            TipoStatusSeeder::class,
            TipoManutencaoSeeder::class,
            // EmpresaContratadaSeeder::class,
            // PedidoSeeder::class,

            // ItensSeeder::class,
            // ContratoSeeder::class,
        ]);



        // // 2. Criar Séries
        // $this->command->info('Criando séries...');
        // $seriesList = [
        //     ['codigo' => 'SER001', 'nome' => 'Berçário'],
        //     ['codigo' => 'SER002', 'nome' => 'Infantil 1'],
        //     ['codigo' => 'SER003', 'nome' => 'Infantil 2'],
        //     ['codigo' => 'SER004', 'nome' => 'Infantil 3'],
        //     ['codigo' => 'SER005', 'nome' => 'Infantil 4'],
        //     ['codigo' => 'SER006', 'nome' => 'Infantil 5'],
        //     ['codigo' => 'SER007', 'nome' => '1º Ano'],
        //     ['codigo' => 'SER008', 'nome' => '2º Ano'],
        //     ['codigo' => 'SER009', 'nome' => '3º Ano'],
        //     ['codigo' => 'SER010', 'nome' => '4º Ano'],
        //     ['codigo' => 'SER011', 'nome' => '5º Ano'],
        // ];

        // foreach ($seriesList as $serie) {
        //     Serie::firstOrCreate(['codigo' => $serie['codigo']], ['nome' => $serie['nome']]);
        // }

        // $series = Serie::all();

        // // 3. Criar Componentes Curriculares
        // $this->command->info('Criando componentes curriculares...');
        // $this->call(ComponenteCurricularSeeder::class);


        // // 4. Criar Professores
        // $this->command->info('Criando professores...');
        // $professores = Professor::factory(30)->create();

        // // 5. Criar Turmas
        // $this->command->info('Criando turmas...');
        // if (Turma::count() === 0) {
        //     Turma::factory(20)->create();
        // }
        // $turmas = Turma::all();

        // // 6. Associar Professores aos Componentes das Turmas
        // $this->command->info('Associando professores aos componentes das turmas...');
        // foreach ($turmas as $turma) {

        //     if ($turma->componentes()->exists()) {
        //         continue;
        //     }

        //     $componentes = $turma->serie->componentesCurriculares;
        //     $professoresDaEscola = Professor::where('id_escola', $turma->id_escola)->get();

        //     foreach ($componentes as $componente) {
        //         // 80% de chance de ter professor, 20% de chance de ficar vago
        //         $temProfessor = rand(1, 100) <= 80;

        //         if ($temProfessor && $professoresDaEscola->isNotEmpty()) {
        //             $professorAleatorio = $professoresDaEscola->random();

        //             $turma->componentes()->attach($componente->id, [
        //                 'professor_id' => $professorAleatorio->id,
        //                 'tem_professor' => true,
        //             ]);
        //         } else {
        //             // Componente sem professor
        //             $turma->componentes()->attach($componente->id, [
        //                 'professor_id' => null,
        //                 'tem_professor' => false,
        //             ]);
        //         }
        //     }
        // }
    }

    private function gerarCodigoSerie(string $nome): ?string
    {
        if (preg_match('/^Infantil\s*(4|5)$/iu', $nome, $m)) {
            return 'SI' . $m[1];
        }

        if (preg_match('/^([1-5])º\s*Ano$/iu', $nome, $m)) {
            return 'S' . $m[1] . 'A';
        }

        return null;
    }
}
