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
        $turnos = ['Manhã', 'Tarde', 'Noite'];

        // Tipos de especialização que vamos usar no campo 'tipo'
        $especializacoesNomes = [
            'Magisterio',
            'Licenciatura',
            'Bacharelado',
            'Pos Graduacao',
            'Doutorado',
            'Mestrado',
        ];

        $escolas = Escola::query()->get(['id', 'nome']);

        $matricSeq = 1; // sequencial global para matrícula/e-mail

        foreach ($escolas as $escola) {
            $schoolSlug = Str::slug($escola->nome ?: "escola-{$escola->id}", '-');

            foreach ($turnos as $turno) {
                // Para ESTE turno na escola, escolhe 1 índice que será o SRM
                $idxSrm = random_int(0, count($especializacoesNomes) - 1);

                foreach ($especializacoesNomes as $idx => $espNome) {

                    // === Regra: 1 professor SRM por turno ===
                    $professorSrm      = ($idx === $idxSrm);
                    $profissionalApoio = ! $professorSrm && (bool) random_int(0, 1);

                    // Nome & e-mail “estáveis”
                    $nomeBase = sprintf(
                        '%s %s %s',
                        ['Ana', 'Bruno', 'Carla', 'Diego', 'Eduarda', 'Felipe', 'Gustavo', 'Helena', 'Igor', 'Júlia'][$matricSeq % 10],
                        ['Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Lima', 'Ferreira', 'Costa', 'Almeida', 'Gomes'][$matricSeq % 10],
                        ['Junior', 'Filho', 'Neto', 'da Costa', 'de Souza', 'do Carmo', 'da Silva', 'Monteiro', 'Faria', 'Furtado'][$matricSeq % 10]
                    );
                    $nome = trim(preg_replace('/\s+/', ' ', $nomeBase));

                    $nomeSlug = Str::slug($nome, '.');
                    $email    = strtolower("{$nomeSlug}.{$matricSeq}@{$schoolSlug}.edu.local");

                    // Matrícula única (numérica)
                    $matricula = str_pad((string) $matricSeq, 8, '0', STR_PAD_LEFT);
                    $matricSeq++;

                    // cria o professor (sem campo 'especializacao_educacao_especial' na tabela professores)
                    $professor = Professor::query()->create([
                        'id_escola'          => $escola->id,
                        'matricula'          => $matricula,
                        'nome'               => $nome,
                        'email'              => $email,
                        'turno'              => $turno,
                        'professor_srm'      => $professorSrm,
                        'profissional_apoio' => $profissionalApoio,
                    ]);

                    // Define se ESTA especialização é de Educação Especial
                    $isEducacaoEspecial = (bool) random_int(0, 1);

                    // === Cria pelo menos UMA especialização para esse professor ===
                    ProfessorEspecializacao::create([
                        'id_professor'                    => $professor->id,
                        'tipo'                            => $espNome,
                        'descricao_especializacao'        => $isEducacaoEspecial
                            ? "Formação em {$espNome} com foco em Educação Especial"
                            : "Formação em {$espNome}",
                        'especializacao_educacao_especial' => $isEducacaoEspecial,
                        'anexo_especializacao_path'       => null, // sem arquivo real no seeder
                    ]);

                    // Se quiser, dá pra incluir extras (comentado por padrão)
                    $extras = collect($especializacoesNomes)
                        ->reject(fn($n) => $n === $espNome)
                        ->random(random_int(0, 2));

                    foreach ($extras as $extraTipo) {
                        $extraIsEduc = (bool) random_int(0, 1);

                        ProfessorEspecializacao::create([
                            'id_professor'                    => $professor->id,
                            'tipo'                            => $extraTipo,
                            'descricao_especializacao'        => $extraIsEduc
                                ? "Formação em {$extraTipo} com foco em Educação Especial"
                                : "Formação em {$extraTipo}",
                            'especializacao_educacao_especial' => $extraIsEduc,
                            'anexo_especializacao_path'       => null,
                        ]);
                    }
                }
            }
        }
    }
}
