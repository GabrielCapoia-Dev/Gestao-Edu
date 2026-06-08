<?php

namespace Database\Seeders;

use App\Models\Escola;
use App\Models\Professor;
use App\Models\ProfessorEspecializacao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProfessorSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = array_keys(Professor::turnosOptions());

        $especializacoesNomes = [
            'Magisterio',
            'Licenciatura',
            'Bacharelado',
            'Pos Graduacao',
            'Doutorado',
            'Mestrado',
        ];

        $escolas = Escola::query()->get(['id', 'nome']);

        // Continua a sequência caso já existam professores
        $matricSeq = ((int) Professor::max('matricula')) + 1;

        foreach ($escolas as $escola) {

            $schoolSlug = Str::slug($escola->nome ?: "escola-{$escola->id}", '-');

            foreach ($turnos as $turno) {

                // Define qual professor será SRM nesse turno
                $idxSrm = random_int(0, count($especializacoesNomes) - 1);

                foreach ($especializacoesNomes as $idx => $espNome) {

                    $professorSrm      = ($idx === $idxSrm);
                    $profissionalApoio = !$professorSrm && (bool) random_int(0, 1);

                    $nomeBase = sprintf(
                        '%s %s %s',
                        ['Ana','Bruno','Carla','Diego','Eduarda','Felipe','Gustavo','Helena','Igor','Júlia'][$matricSeq % 10],
                        ['Silva','Souza','Oliveira','Santos','Pereira','Lima','Ferreira','Costa','Almeida','Gomes'][$matricSeq % 10],
                        ['Junior','Filho','Neto','da Costa','de Souza','do Carmo','da Silva','Monteiro','Faria','Furtado'][$matricSeq % 10]
                    );

                    $nome = trim(preg_replace('/\s+/', ' ', $nomeBase));

                    $nomeSlug = Str::slug($nome, '.');

                    $email = strtolower("{$nomeSlug}.{$matricSeq}@{$schoolSlug}.edu.local");

                    $matricula = str_pad((string) $matricSeq, 8, '0', STR_PAD_LEFT);

                    $matricSeq++;

                    $professor = Professor::updateOrCreate(
                        [
                            'matricula' => $matricula,
                        ],
                        [
                            'id_escola'          => $escola->id,
                            'nome'               => $nome,
                            'email'              => $email,
                            'turno'              => $turno,
                            'professor_srm'      => $professorSrm,
                            'profissional_apoio' => $profissionalApoio,
                        ]
                    );

                    $isEducacaoEspecial = (bool) random_int(0, 1);

                    ProfessorEspecializacao::updateOrCreate(
                        [
                            'id_professor' => $professor->id,
                            'tipo'         => $espNome,
                        ],
                        [
                            'descricao_especializacao' => $isEducacaoEspecial
                                ? "Formação em {$espNome} com foco em Educação Especial"
                                : "Formação em {$espNome}",
                            'especializacao_educacao_especial' => $isEducacaoEspecial,
                            'anexo_especializacao_path' => null,
                        ]
                    );

                    $extras = collect($especializacoesNomes)
                        ->reject(fn($n) => $n === $espNome)
                        ->random(random_int(0, 2));

                    foreach ($extras as $extraTipo) {

                        $extraIsEduc = (bool) random_int(0, 1);

                        ProfessorEspecializacao::updateOrCreate(
                            [
                                'id_professor' => $professor->id,
                                'tipo'         => $extraTipo,
                            ],
                            [
                                'descricao_especializacao' => $extraIsEduc
                                    ? "Formação em {$extraTipo} com foco em Educação Especial"
                                    : "Formação em {$extraTipo}",
                                'especializacao_educacao_especial' => $extraIsEduc,
                                'anexo_especializacao_path' => null,
                            ]
                        );
                    }
                }
            }
        }
    }
}
