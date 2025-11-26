<?php

namespace Database\Seeders;

use App\Models\DominioEmail;
use App\Models\Laudo;
use App\Models\Serie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Limpa cache das permissões do Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista de permissões que serão atribuídas à role Admin
        $permissionsList = [
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
            'Listar Séries',
            'Criar Séries',
            'Editar Séries',
            'Excluir Séries',
            'Listar Escolas',
            'Criar Escolas',
            'Editar Escolas',
            'Excluir Escolas',
            'Listar Turmas',
            'Criar Turmas',
            'Editar Turmas',
            'Excluir Turmas',
            'Listar Alunos',
            'Criar Alunos',
            'Editar Alunos',
            'Excluir Alunos',
            'Listar Professores',
            'Criar Professores',
            'Editar Professores',
            'Excluir Professores',
            'Listar Laudos',
            'Criar Laudos',
            'Editar Laudos',
            'Excluir Laudos',
        ];

        $permissionsSecretario = [
            'Listar Usuários',
            'Criar Usuários',
            'Editar Usuários',
            'Excluir Usuários',
            'Listar Professores',
            'Criar Professores',
            'Editar Professores',
            'Excluir Professores',
            'Listar Turmas',
            'Criar Turmas',
            'Editar Turmas',
            'Excluir Turmas',
            'Listar Alunos',
            'Criar Alunos',
            'Editar Alunos',
            'Excluir Alunos',
        ];

        $password = "Senha@123";

        // Criação das permissões
        foreach ($permissionsList as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Criação da rule Admin
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $secretarioRole = Role::firstOrCreate(['name' => 'Secretário']);

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
                'email_approved' => true
            ]
        );
        $secretarioUser = User::firstOrCreate(
            ['email' => 'secretario@secretario.com'],
            [
                'codigo' => 101,
                'name' => 'Secretário',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true
            ]
        );

        $adminUser->assignRole($adminRole);
        $secretarioUser->assignRole($secretarioRole);

        /**
         * Criar domínios de email
         */

        $emailPermissionsList = [
            [
                'gmail.com',
                'edu.umuarama.pr.gov.br',
                'umuarama.pr.gov.br',
            ],
            [
                'Geral',
                'Educação',
                'Administrativo'
            ]
        ];

        foreach ($emailPermissionsList[0] as $index => $dominio) {
            $setor = $emailPermissionsList[1][$index] ?? 'Geral';

            DominioEmail::create([
                'dominio_email' => $dominio,
                'setor' => $setor,
                'status' => 1,
            ]);
        }

        /**
         * Criar séries
         */
        $seriesList = [
            'Infantil 4',
            'Infantil 5',
            '1º Ano',
            '2º Ano',
            '3º Ano',
            '4º Ano',
            '5º Ano',
        ];

        foreach ($seriesList as $seriesName) {
            $codigo = $this->gerarCodigoSerie($seriesName);

            // se já existe pelo nome, atualiza o código; senão, cria
            Serie::updateOrCreate(
                ['nome' => $seriesName],
                ['codigo' => $codigo]
            );
        }

        /**
         * Criar séries
         */
        $laudoList = [
            'Deficiência Intelectual',
            'Transtorno do Espectro Autista (TEA)',
            'Deficiência Física/Motora',
            'Deficiência Visual',
            'Cegueira',
            'Baixa Visão',
            'Visão Monocular',
            'Deficiência Auditiva',
            'Surdez',
            'Surdocegueira',
            'Deficiência Múltipla',
            'Altas Habilidades/Superdotação',
            'Transtorno de Déficit de Atenção e Hiperatividade (TDAH)',
            'Transtorno Opositor Desafiador (TOD)',
            'Transtornos Específicos da Aprendizagem (Dislexia, Discalculia, Disgrafia, etc.)',
            'Transtornos da Comunicação (linguagem, fala, fluência, etc.)',
            'Transtornos Motores do Neurodesenvolvimento (dispraxia, coordenação motora, etc.)',
            'Atraso Global do Desenvolvimento',
            'Atraso no Desenvolvimento Neuropsicomotor',
            'Transtornos Mentais e do Comportamento com impacto funcional significativo'
        ];

        foreach ($laudoList as $laudo) {

            Laudo::updateOrCreate(
                ['nome' => $laudo],
            );
        }

        $this->call([
            EscolaSeeder::class,
            TurmaSeeder::class,
        ]);
    }

    private function gerarCodigoSerie(string $nome): ?string
    {
        // Infantil 4/5 => SI4 / SI5
        if (preg_match('/^Infantil\s*(4|5)$/iu', $nome, $m)) {
            return 'SI' . $m[1];
        }

        // 1º ao 5º Ano => S1A ... S5A
        if (preg_match('/^([1-5])º\s*Ano$/iu', $nome, $m)) {
            return 'S' . $m[1] . 'A';
        }

        // fallback se aparecer algo fora do padrão
        return null;
    }
}
