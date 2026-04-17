<?php

namespace Database\Seeders;

use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoResposta;
use App\Models\Aluno;
use App\Models\ComponenteCurricular;
use App\Models\DominioEmail;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AvaliacaoFluxoSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('permissoes:criar');

        DominioEmail::firstOrCreate(
            ['dominio_email' => 'edu.umuarama.pr.gov.br'],
            ['setor' => 'Educação', 'status' => true]
        );

        $escolaCentro = Escola::updateOrCreate(
            ['codigo' => 'ESCAVC'],
            [
                'nome' => 'Escola Municipal Centro Avaliações',
                'email' => 'escola.centro@edu.umuarama.pr.gov.br',
                'telefone' => '(44) 3900-1001',
            ]
        );

        $escolaJardim = Escola::updateOrCreate(
            ['codigo' => 'ESCAVJ'],
            [
                'nome' => 'Escola Municipal Jardim Avaliações',
                'email' => 'escola.jardim@edu.umuarama.pr.gov.br',
                'telefone' => '(44) 3900-1002',
            ]
        );

        $serie5 = Serie::updateOrCreate(
            ['codigo' => 'SERAVA5'],
            ['nome' => '5º Ano']
        );

        $serie6 = Serie::updateOrCreate(
            ['codigo' => 'SERAVA6'],
            ['nome' => '6º Ano']
        );

        $componenteMatematica = ComponenteCurricular::updateOrCreate(
            ['codigo' => 'COMP-MAT-AV'],
            ['nome' => 'Matemática']
        );

        $componenteHistoria = ComponenteCurricular::updateOrCreate(
            ['codigo' => 'COMP-HIS-AV'],
            ['nome' => 'História']
        );

        $componenteGeografia = ComponenteCurricular::updateOrCreate(
            ['codigo' => 'COMP-GEO-AV'],
            ['nome' => 'Geografia']
        );

        $componenteCiencias = ComponenteCurricular::updateOrCreate(
            ['codigo' => 'COMP-CIE-AV'],
            ['nome' => 'Ciências']
        );

        $serie5->componentesCurriculares()->syncWithoutDetaching([
            $componenteMatematica->id,
            $componenteHistoria->id,
            $componenteGeografia->id,
            $componenteCiencias->id,
        ]);

        $serie6->componentesCurriculares()->syncWithoutDetaching([
            $componenteMatematica->id,
            $componenteHistoria->id,
            $componenteGeografia->id,
            $componenteCiencias->id,
        ]);

        $turmaCentroA = Turma::updateOrCreate(
            ['codigo' => 'TRMAVC5A'],
            [
                'nome' => 'A',
                'turno' => 'manha',
                'id_serie' => $serie5->id,
                'id_escola' => $escolaCentro->id,
            ]
        );

        $turmaCentroB = Turma::updateOrCreate(
            ['codigo' => 'TRMAVC6B'],
            [
                'nome' => 'B',
                'turno' => 'tarde',
                'id_serie' => $serie6->id,
                'id_escola' => $escolaCentro->id,
            ]
        );

        $turmaJardimA = Turma::updateOrCreate(
            ['codigo' => 'TRMAVJ5A'],
            [
                'nome' => 'A',
                'turno' => 'manha',
                'id_serie' => $serie5->id,
                'id_escola' => $escolaJardim->id,
            ]
        );

        $gestorUser = User::updateOrCreate(
            ['email' => 'gestor.avaliacoes@edu.umuarama.pr.gov.br'],
            [
                'id_escola' => $escolaCentro->id,
                'name' => 'Gestor de Avaliações',
                'password' => Hash::make('Senha@123'),
                'email_approved' => true,
                'email_verified_at' => now(),
            ]
        );

        $professorMatUser = User::updateOrCreate(
            ['email' => 'prof.matematica@edu.umuarama.pr.gov.br'],
            [
                'name' => 'Professor Matemática',
                'password' => Hash::make('Senha@123'),
                'email_approved' => true,
                'email_verified_at' => now(),
            ]
        );

        $professorHistUser = User::updateOrCreate(
            ['email' => 'prof.historia@edu.umuarama.pr.gov.br'],
            [
                'name' => 'Professor História',
                'password' => Hash::make('Senha@123'),
                'email_approved' => true,
                'email_verified_at' => now(),
            ]
        );

        $roleGestaoPedagogica = Role::findOrCreate('Gestao Pedagogica', 'web');
        $roleAcessarPainel = Role::findOrCreate('Acessar Painel', 'web');
        $roleVisualizarTurmasAlunos = Role::findOrCreate('Visualizar Turmas e Alunos', 'web');

        $gestorUser->syncRoles([$roleGestaoPedagogica]);
        $professorMatUser->syncRoles([$roleAcessarPainel, $roleVisualizarTurmasAlunos]);
        $professorHistUser->syncRoles([$roleAcessarPainel, $roleVisualizarTurmasAlunos]);

        $professorMat = Professor::updateOrCreate(
            ['id_escola' => $escolaCentro->id, 'matricula' => 'PROF-MAT-01'],
            [
                'user_id' => $professorMatUser->id,
                'nome' => 'Professor Matemática',
                'email' => 'prof.matematica@edu.umuarama.pr.gov.br',
                'telefone' => '(44) 99999-0001',
            ]
        );

        $professorHist = Professor::updateOrCreate(
            ['id_escola' => $escolaCentro->id, 'matricula' => 'PROF-HIS-01'],
            [
                'user_id' => $professorHistUser->id,
                'nome' => 'Professor História',
                'email' => 'prof.historia@edu.umuarama.pr.gov.br',
                'telefone' => '(44) 99999-0002',
            ]
        );

        $this->vincularComponenteProfessor($turmaCentroA, $componenteMatematica, $professorMat);
        $this->vincularComponenteProfessor($turmaCentroA, $componenteHistoria, $professorHist);
        $this->vincularComponenteProfessor($turmaCentroA, $componenteGeografia, $professorHist);
        $this->vincularComponenteProfessor($turmaCentroA, $componenteCiencias, $professorMat);

        $this->vincularComponenteProfessor($turmaCentroB, $componenteMatematica, $professorMat);
        $this->vincularComponenteProfessor($turmaCentroB, $componenteHistoria, $professorHist);
        $this->vincularComponenteProfessor($turmaCentroB, $componenteGeografia, $professorHist);
        $this->vincularComponenteProfessor($turmaCentroB, $componenteCiencias, $professorMat);

        $this->vincularComponenteProfessor($turmaJardimA, $componenteMatematica, $professorMat);
        $this->vincularComponenteProfessor($turmaJardimA, $componenteHistoria, $professorHist);
        $this->vincularComponenteProfessor($turmaJardimA, $componenteGeografia, $professorHist);
        $this->vincularComponenteProfessor($turmaJardimA, $componenteCiencias, $professorMat);

        $alunosCentroA = $this->criarAlunosDaTurma($turmaCentroA, 'A', 12);
        $alunosCentroB = $this->criarAlunosDaTurma($turmaCentroB, 'B', 10);
        $this->criarAlunosDaTurma($turmaJardimA, 'J', 10);

        $alternativaExcelente = Alternativa::updateOrCreate(
            ['nome' => 'Excelente'],
            ['tem_observacao' => false, 'observacao' => null, 'status' => true]
        );
        $alternativaBom = Alternativa::updateOrCreate(
            ['nome' => 'Bom'],
            ['tem_observacao' => false, 'observacao' => null, 'status' => true]
        );
        $alternativaRegular = Alternativa::updateOrCreate(
            ['nome' => 'Regular'],
            ['tem_observacao' => false, 'observacao' => null, 'status' => true]
        );
        $alternativaInsuficiente = Alternativa::updateOrCreate(
            ['nome' => 'Insuficiente'],
            ['tem_observacao' => false, 'observacao' => null, 'status' => true]
        );
        $alternativaDiscursiva = Alternativa::updateOrCreate(
            ['nome' => 'Resposta Discursiva'],
            ['tem_observacao' => true, 'observacao' => 'Alternativa para texto livre do professor.', 'status' => true]
        );

        $pautaMatematica = Pauta::updateOrCreate(
            ['texto' => 'Resolve operações com números naturais e decimais.'],
            [
                'componente_curricular_id' => $componenteMatematica->id,
                'status' => true,
            ]
        );

        $pautaHistoria = Pauta::updateOrCreate(
            ['texto' => 'Compreende fatos históricos e suas relações com o presente.'],
            [
                'componente_curricular_id' => $componenteHistoria->id,
                'status' => true,
            ]
        );

        $pautaGeografia = Pauta::updateOrCreate(
            ['texto' => 'Interpreta mapas e localização no espaço geográfico.'],
            [
                'componente_curricular_id' => $componenteGeografia->id,
                'status' => true,
            ]
        );

        $pautaCiencias = Pauta::updateOrCreate(
            ['texto' => 'Aplica conceitos científicos em situações do cotidiano.'],
            [
                'componente_curricular_id' => $componenteCiencias->id,
                'status' => true,
            ]
        );

        $pautaParticipacao = Pauta::updateOrCreate(
            ['texto' => 'Participação e engajamento do aluno durante as aulas.'],
            [
                'componente_curricular_id' => null,
                'status' => true,
            ]
        );

        $alternativasBaseIds = [
            $alternativaExcelente->id,
            $alternativaBom->id,
            $alternativaRegular->id,
            $alternativaInsuficiente->id,
            $alternativaDiscursiva->id,
        ];

        $pautaMatematica->alternativas()->sync($alternativasBaseIds);
        $pautaHistoria->alternativas()->sync($alternativasBaseIds);
        $pautaGeografia->alternativas()->sync($alternativasBaseIds);
        $pautaCiencias->alternativas()->sync($alternativasBaseIds);
        $pautaParticipacao->alternativas()->sync($alternativasBaseIds);

        $avaliacaoDiagnostica = Avaliacao::updateOrCreate(
            ['nome' => 'Diagnóstica 1º Bimestre 2026'],
            [
                'data_inicio' => now()->subDays(5)->toDateString(),
                'data_fim' => now()->addDays(20)->toDateString(),
                'status' => Avaliacao::STATUS_ATIVA,
            ]
        );
        $avaliacaoDiagnostica->pautas()->sync([
            $pautaMatematica->id,
            $pautaHistoria->id,
            $pautaGeografia->id,
            $pautaParticipacao->id,
        ]);
        $avaliacaoDiagnostica->turmas()->sync([
            $turmaCentroA->id,
            $turmaCentroB->id,
            $turmaJardimA->id,
        ]);

        $avaliacaoFormativa = Avaliacao::updateOrCreate(
            ['nome' => 'Formativa Ciências e Matemática 2026'],
            [
                'data_inicio' => now()->subDays(1)->toDateString(),
                'data_fim' => now()->addDays(15)->toDateString(),
                'status' => Avaliacao::STATUS_ATIVA,
            ]
        );
        $avaliacaoFormativa->pautas()->sync([
            $pautaMatematica->id,
            $pautaCiencias->id,
            $pautaParticipacao->id,
        ]);
        $avaliacaoFormativa->turmas()->sync([
            $turmaCentroA->id,
            $turmaJardimA->id,
        ]);

        $avaliacaoEncerrada = Avaliacao::updateOrCreate(
            ['nome' => 'Avaliação Encerrada 2025'],
            [
                'data_inicio' => now()->subMonths(6)->toDateString(),
                'data_fim' => now()->subMonths(5)->toDateString(),
                'status' => Avaliacao::STATUS_ENCERRADA,
            ]
        );
        $avaliacaoEncerrada->pautas()->sync([
            $pautaMatematica->id,
            $pautaHistoria->id,
        ]);
        $avaliacaoEncerrada->turmas()->sync([$turmaCentroA->id]);

        foreach ($alunosCentroA->take(6) as $aluno) {
            AvaliacaoResposta::updateOrCreate(
                [
                    'avaliacao_id' => $avaliacaoDiagnostica->id,
                    'pauta_id' => $pautaMatematica->id,
                    'turma_id' => $turmaCentroA->id,
                    'aluno_id' => $aluno->id,
                ],
                [
                    'professor_id' => $professorMat->id,
                    'alternativa_id' => $alternativaBom->id,
                    'observacao' => 'Resposta inicial registrada pelo seeder.',
                    'respondido_em' => now()->subHours(3),
                ]
            );
        }

        foreach ($alunosCentroB->take(4) as $aluno) {
            AvaliacaoResposta::updateOrCreate(
                [
                    'avaliacao_id' => $avaliacaoDiagnostica->id,
                    'pauta_id' => $pautaHistoria->id,
                    'turma_id' => $turmaCentroB->id,
                    'aluno_id' => $aluno->id,
                ],
                [
                    'professor_id' => $professorHist->id,
                    'alternativa_id' => $alternativaRegular->id,
                    'observacao' => 'Avaliação parcial para testes de continuidade.',
                    'respondido_em' => now()->subHours(2),
                ]
            );
        }

        if ($this->command) {
            $this->command->info('Seeder de avaliações concluído.');
            $this->command->line('Usuário gestor: gestor.avaliacoes@edu.umuarama.pr.gov.br | Senha: Senha@123');
            $this->command->line('Professor matemática: prof.matematica@edu.umuarama.pr.gov.br | Senha: Senha@123');
            $this->command->line('Professor história: prof.historia@edu.umuarama.pr.gov.br | Senha: Senha@123');
        }
    }

    private function vincularComponenteProfessor(Turma $turma, ComponenteCurricular $componente, Professor $professor): void
    {
        $turma->componentes()->syncWithoutDetaching([
            $componente->id => [
                'professor_id' => $professor->id,
                'tem_professor' => true,
            ],
        ]);
    }

    private function criarAlunosDaTurma(Turma $turma, string $prefixo, int $quantidade): \Illuminate\Support\Collection
    {
        $alunos = collect();

        for ($i = 1; $i <= $quantidade; $i++) {
            $cgm = 'CGM-' . $prefixo . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $aluno = Aluno::updateOrCreate(
                ['cgm' => $cgm],
                [
                    'nome' => 'Aluno ' . $prefixo . ' ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'data_nascimento' => now()->subYears(10)->subDays($i)->toDateString(),
                    'id_turma' => $turma->id,
                ]
            );

            $alunos->push($aluno);
        }

        return $alunos;
    }
}
