<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\AlunoRetencao;
use App\Models\Laudo;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Faker\Factory as Faker;

class AlunoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('pt_BR');

        // 15 primeiros nomes (mistos)
        $primeiros = [
            'Maria',
            'Ana',
            'João',
            'Gabriel',
            'Pedro',
            'Lucas',
            'Luiza',
            'Julia',
            'Miguel',
            'Guilherme',
            'Mariana',
            'Matheus',
            'Beatriz',
            'Rafael',
            'Felipe',
        ];

        // 15 nomes do meio (bem comuns no BR)
        $meios = [
            'Aparecida',
            'Clara',
            'Eduarda',
            'Fernanda',
            'Sofia',
            'Carolina',
            'Vitória',
            'Cristina',
            'Letícia',
            'Bianca',
            'Augusto',
            'Henrique',
            'Eduardo',
            'André',
            'César',
        ];

        // 15 sobrenomes
        $sobrenomes = [
            'Silva',
            'Santos',
            'Oliveira',
            'Souza',
            'Rodrigues',
            'Ferreira',
            'Alves',
            'Pereira',
            'Lima',
            'Gomes',
            'Ribeiro',
            'Carvalho',
            'Almeida',
            'Costa',
            'Martins',
        ];

        $motivosRetencaoPossiveis = [
            'Faltas',
            'Aprendizagem',
        ];

        // Status de acompanhamento compatíveis com os ENUMs
        $statusAcompanhamentoEnum = [
            'Sim, Lista de Espera',
            'Sim, Em Atendimento',
            'Sim, Desistente',
            'Sim, Desligado',
            'Não',
        ];

        $vezesRetidoEnum = ['1 vez', '2 vezes', '3 vezes', '4 ou mais'];

        $laudos = Laudo::all();

        // Agrupa professores por escola + turno para achar o SRM certo depois
        $professoresPorEscolaTurno = Professor::all()
            ->groupBy(function (Professor $professor) {
                return $professor->id_escola . '|' . $professor->turno;
            });

        $turmas = Turma::with('escola')->get();

        foreach ($turmas as $turma) {
            // Quantidade de alunos por turma
            $quantidadeAlunos = $faker->numberBetween(0, 2);

            for ($i = 0; $i < $quantidadeAlunos; $i++) {
                // Sexo compatível com o ENUM
                $sexo = Arr::random(['Masculino', 'Feminino']);

                $primeiro = Arr::random($primeiros);
                $meio = Arr::random($meios);
                $sobrenome = Arr::random($sobrenomes);

                $nome = trim(collect([$primeiro, $meio, $sobrenome])->implode(' '));

                // Idade escolar entre ~5 e 14 anos
                $dataNascimento = $faker->dateTimeBetween('-14 years', '-5 years');

                // Probabilidade de frequentar SRM
                $frequentaSrm = $faker->boolean(40); // boolean (ok com migration)

                // Dificuldade de aprendizagem (boolean)
                $dificuldadeAprendizagem = $frequentaSrm
                    ? true
                    : $faker->boolean(20);

                // Encaminhamentos (ENUM 'Sim' / 'Nao')
                $encSme = $frequentaSrm && $faker->boolean(40) ? 'Sim' : 'Nao';
                $encCaei = $frequentaSrm && $faker->boolean(50) ? 'Sim' : 'Nao';

                // Status dos profissionais (ENUM: 'Sim', 'Nao', 'Lista de Espera')
                $statusFono = $frequentaSrm && $faker->boolean(50)
                    ? Arr::random($statusAcompanhamentoEnum)
                    : 'Nao';

                $statusPsico = $frequentaSrm && $faker->boolean(50)
                    ? Arr::random($statusAcompanhamentoEnum)
                    : 'Nao';

                $statusPsicoped = $frequentaSrm && $faker->boolean(50)
                    ? Arr::random($statusAcompanhamentoEnum)
                    : 'Nao';


                // Avanço CAEI (ENUM 'Sim', 'Nao', 'Nao está em atendimento')
                if ($encCaei === 'Sim') {
                    $avancoCaei = Arr::random(['Sim', 'Nao']);
                } else {
                    $avancoCaei = 'Nao está em atendimento';
                }

                // Professor SRM do mesmo turno/escola (se houver)
                $keyProfessor = $turma->id_escola . '|' . $turma->turno;

                /** @var \Illuminate\Support\Collection|null $professoresDoContexto */
                $professoresDoContexto = $professoresPorEscolaTurno->get($keyProfessor);

                $professorSrm = $professoresDoContexto
                    ? $professoresDoContexto
                    ->where('professor_srm', true)
                    ->whenEmpty(function ($col) use ($professoresDoContexto) {
                        // se não tiver professor_srm, pega qualquer do contexto
                        return $professoresDoContexto;
                    })
                    ->random()
                    : null;

                $aluno = Aluno::create([
                    'id_professor'              => $professorSrm?->id,
                    'id_turma'                  => $turma->id,
                    'cgm'                       => $faker->unique()->numerify('##########'),
                    'nome'                      => $nome,
                    'sexo'                      => $sexo,
                    'data_nascimento'           => $dataNascimento->format('Y-m-d'),
                    'dificuldade_aprendizagem'  => $dificuldadeAprendizagem,
                    'frequenta_srm'             => $frequentaSrm,
                    'encaminhado_para_sme'      => $encSme,
                    'encaminhado_para_caei'     => $encCaei,
                    'status_fonoaudiologo'      => $statusFono,
                    'status_psicologo'          => $statusPsico,
                    'ja_foi_retido'             => 'Nao',
                    'status_psicopedagogo'      => $statusPsicoped,
                    'avanco_caei'               => $avancoCaei,
                ]);


                // ===== Laudos (1 a 3, dependendo) =====
                if ($laudos->isNotEmpty() && $frequentaSrm && $faker->boolean(70)) {
                    $qtdLaudos = $faker->numberBetween(1, min(3, $laudos->count()));
                    $aluno->laudos()->attach(
                        $laudos->random($qtdLaudos)->pluck('id')->all()
                    );
                }

                // ===== Retenções (AlunoRetencao) =====
                if ($faker->boolean(20)) {
                    $vezesSelecionado = Arr::random($vezesRetidoEnum);

                    // quantidade de elementos nos arrays só para dar variedade
                    switch ($vezesSelecionado) {
                        case '1 vez':
                            $vezesCount = 1;
                            break;
                        case '2 vezes':
                            $vezesCount = 2;
                            break;
                        case '3 vezes':
                            $vezesCount = 3;
                            break;
                        default: // '4 ou mais'
                            $vezesCount = $faker->numberBetween(4, 5);
                            break;
                    }

                    $anosRetidos = [];
                    $motivosRetidos = [];
                    $anoAtual = now()->year;

                    for ($r = 0; $r < $vezesCount; $r++) {
                        $anosRetidos[] = $faker->numberBetween($anoAtual - 5, $anoAtual - 1);
                        $motivosRetidos[] = Arr::random($motivosRetencaoPossiveis);
                    }

                    AlunoRetencao::create([
                        'id_aluno'     => $aluno->id,
                        'id_serie'     => $turma->id_serie,
                        'vezes_retido' => $vezesSelecionado,
                        'ano_retido'   => $anosRetidos,
                        'motivo_retido' => $motivosRetidos,
                    ]);

                    $aluno->update([
                        'ja_foi_retido' => 'Sim',
                    ]);
                }
            }
        }

        // limpa o unique do Faker
        $faker->unique(true);
    }
}
