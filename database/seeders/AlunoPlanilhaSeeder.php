<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AlunoPlanilhaSeeder extends Seeder
{
    // URL direta em CSV da aba "Matriculados"
    private const aluno_sheet = 'https://docs.google.com/spreadsheets/d/1S6ThiBaNmfnIbdM_bGH9Tr_O49yrxO2nehd7ecNphB8/export?format=csv&gid=527021056';

    public function run(): void
    {
        $url = config('services.alunos_sheet_csv', self::aluno_sheet);

        if (! $url) {
            $this->command->error('URL da planilha de alunos não configurada (ALUNOS_SHEET_CSV).');
            return;
        }

        $this->command->info("Baixando CSV da planilha: {$url}");

        $response = Http::get($url);

        if (! $response->ok()) {
            $this->command->error('Falha ao baixar CSV da planilha de alunos.');
            return;
        }

        $csv = $response->body();
        $linhas = $this->parseCsv($csv);

        if (empty($linhas)) {
            $this->command->warn('CSV vazio ou sem dados.');
            return;
        }

        // Cabeçalho
        $header = array_map('trim', array_shift($linhas));

        // Mapeia índices importantes
        $idxEscola      = array_search('Escola', $header);
        $idxSeriacao    = array_search('Seriação', $header);
        $idxTurma       = array_search('Turma', $header);
        $idxTurno       = array_search('Turno', $header);
        $idxCgm         = array_search('CGM', $header);
        $idxNomeAluno   = array_search('Nome do Aluno', $header);
        $idxDataNasc    = array_search('Data de Nasc', $header);
        $idxSexo        = array_search('Sexo', $header);

        if (
            $idxEscola === false ||
            $idxSeriacao === false ||
            $idxTurma === false ||
            $idxTurno === false ||
            $idxCgm === false ||
            $idxNomeAluno === false ||
            $idxDataNasc === false ||
            $idxSexo === false
        ) {
            $this->command->error('Cabeçalho da planilha não corresponde aos nomes esperados.');
            return;
        }

        // Precarrega escolas para não ficar batendo no banco toda hora
        $escolasPorNome = Escola::all()->keyBy('nome');

        $totalProcessadas = 0;
        $totalCriadas     = 0;
        $totalAtualizadas = 0;
        $totalIgnoradas   = 0;

        // contador por motivo (pra resumo no final)
        $motivosIgnorados = [
            'dados_minimos'          => 0,
            'sem_seriacao_srm'       => 0, // aqui agora só casos em que não achou o aluno base
            'escola_nao_encontrada'  => 0,
            'serie_nao_encontrada'   => 0,
            'sem_turma_ou_turno'     => 0,
            'data_nasc_invalida'     => 0,
            'data_nasc_vazia'        => 0,
        ];

        foreach ($linhas as $linhaNumero => $linha) {
            $linhaReal = $linhaNumero + 2; // +2 por causa do cabeçalho

            $escolaNome   = trim($linha[$idxEscola] ?? '');
            $seriacao     = trim($linha[$idxSeriacao] ?? '');
            $turmaLetra   = trim($linha[$idxTurma] ?? '');
            $turno        = trim($linha[$idxTurno] ?? '');
            $cgm          = trim($linha[$idxCgm] ?? '');
            $nomeAluno    = trim($linha[$idxNomeAluno] ?? '');
            $dataNascStr  = trim($linha[$idxDataNasc] ?? '');
            $sexoSigla    = strtoupper(trim($linha[$idxSexo] ?? ''));

            // helper só pros logs ficarem padronizados
            $identAluno = "Linha {$linhaReal} (CGM '{$cgm}', Nome '{$nomeAluno}')";

            // 1) Linha vazia / sem dados mínimos
            if ($escolaNome === '' || $cgm === '' || $nomeAluno === '') {
                $totalIgnoradas++;
                $motivosIgnorados['dados_minimos']++;
                continue;
            }

            /**
             * 2) CASO ESPECIAL: "Sem Seriação" = registro da SRM.
             *
             * Nesses casos:
             *  - NÃO criamos nova turma nem mexemos em série.
             *  - APENAS marcamos o aluno existente como frequenta_srm = true.
             *  - Se não existir aluno com esse CGM, ignoramos e contamos.
             */
            if ($seriacao === 'Sem Seriação') {
                $aluno = Aluno::where('cgm', $cgm)->first();

                if ($aluno) {
                    $aluno->update(['frequenta_srm' => true]);
                    $totalProcessadas++;
                    $totalAtualizadas++;
                } else {
                    // Caso raro: veio SRM mas não tem linha "base" cadastrada
                    $this->command->warn(
                        "{$identAluno} SRM: 'Sem Seriação' mas aluno base (por CGM) não encontrado para marcar frequenta_srm."
                    );
                    $totalIgnoradas++;
                    $motivosIgnorados['sem_seriacao_srm']++;
                }

                continue; // pula para a próxima linha
            }

            // 2b) Seriação realmente vazia = dado ruim -> ignorar
            if ($seriacao === '') {
                $this->command->warn(
                    "{$identAluno} ignorada: seriação vazia."
                );
                $totalIgnoradas++;
                $motivosIgnorados['sem_seriacao_srm']++;
                continue;
            }

            /** @var \App\Models\Escola|null $escola */
            $escola = $escolasPorNome[$escolaNome] ?? null;

            if (! $escola) {
                $totalIgnoradas++;
                $motivosIgnorados['escola_nao_encontrada']++;
                continue;
            }

            // 3) Procura série com base APENAS na tabela `series`
            /** @var \App\Models\Serie|null $serie */
            $serie = Serie::where('nome', $seriacao)->first();

            if (! $serie) {
                $totalIgnoradas++;
                $motivosIgnorados['serie_nao_encontrada']++;
                continue;
            }

            // 4) Turma / turno obrigatórios
            if ($turmaLetra === '' || $turno === '') {
                $totalIgnoradas++;
                $motivosIgnorados['sem_turma_ou_turno']++;
                continue;
            }

            // Encontra ou cria turma (se não existir, cria)
            /** @var \App\Models\Turma $turma */
            $turma = Turma::firstOrCreate(
                [
                    'id_escola' => $escola->id,
                    'id_serie'  => $serie->id,
                    'turma'     => $turmaLetra,
                    'turno'     => $turno,
                ],
                [
                    // Campos adicionais da turma, se houver
                ],
            );

            // 5) Mapeia sexo
            $sexo = match ($sexoSigla) {
                'M'   => 'Masculino',
                'F'   => 'Feminino',
                default => 'Masculino', // fallback
            };

            // 6) Converte data de nascimento
            $dataNascimento = null;
            if ($dataNascStr !== '') {
                try {
                    // Se estiver dd/mm/aaaa
                    $dataNascimento = Carbon::createFromFormat('d/m/Y', $dataNascStr)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $this->command->warn(
                        "{$identAluno} ignorada: data de nascimento inválida '{$dataNascStr}'."
                    );
                    $totalIgnoradas++;
                    $motivosIgnorados['data_nasc_invalida']++;
                    continue;
                }
            } else {
                $this->command->warn(
                    "{$identAluno} ignorada: aluno sem data de nascimento."
                );
                $totalIgnoradas++;
                $motivosIgnorados['data_nasc_vazia']++;
                continue;
            }

            // Monta payload para o aluno (sem 'frequenta_srm' pra não sobrescrever!)
            $payload = [
                'id_turma'              => $turma->id,
                'id_professor'          => null,
                'nome'                  => $nomeAluno,
                'sexo'                  => $sexo,
                'data_nascimento'       => $dataNascimento,

                'dificuldade_aprendizagem' => false,

                'encaminhado_para_sme'     => 'Nao',
                'ja_foi_retido'            => 'Nao',
                'encaminhado_para_caei'    => 'Nao',

                'status_fonoaudiologo'     => 'Não',
                'status_psicologo'         => 'Não',
                'status_psicopedagogo'     => 'Não',

                'avanco_caei'              => 'Nao está em atendimento',
            ];

            // updateOrCreate para não duplicar CGM
            $aluno = Aluno::updateOrCreate(
                ['cgm' => $cgm],
                $payload
            );

            $totalProcessadas++;

            if ($aluno->wasRecentlyCreated) {
                $totalCriadas++;
            } else {
                $totalAtualizadas++;
            }
        }

        $this->command->info("Importação concluída.");
        $this->command->line("Linhas processadas:  {$totalProcessadas}");
        $this->command->line("Alunos criados:      {$totalCriadas}");
        $this->command->line("Alunos atualizados:  {$totalAtualizadas}");
        $this->command->line("Linhas ignoradas:    {$totalIgnoradas}");

        // Labels bonitinhos pra cada motivo
        $labelsMotivos = [
            'dados_minimos'          => 'dados mínimos ausentes (Escola / CGM / Nome)',
            'sem_seriacao_srm'       => 'registros com "Sem Seriação" ou sem seriação que não puderam ser vinculados',
            'escola_nao_encontrada'  => 'escola não encontrada no banco',
            'serie_nao_encontrada'   => 'série/seriação não encontrada na tabela "series"',
            'sem_turma_ou_turno'     => 'turma ou turno vazio',
            'data_nasc_invalida'     => 'data de nascimento em formato inválido',
            'data_nasc_vazia'        => 'data de nascimento ausente',
        ];

        $this->command->info('Resumo dos motivos das linhas ignoradas:');
        foreach ($motivosIgnorados as $motivo => $qtde) {
            if ($qtde === 0) {
                continue;
            }

            $descricao = $labelsMotivos[$motivo] ?? $motivo;

            $this->command->line(
                "  - {$qtde} aluno(s) ignorado(s) por: {$descricao}."
            );
        }
    }

    /**
     * Converte CSV em array de linhas/colunas.
     */
    private function parseCsv(string $csv): array
    {
        $linhas = [];

        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $csv);
        rewind($fh);

        while (($row = fgetcsv($fh, 0, ',')) !== false) {
            $linhas[] = $row;
        }

        fclose($fh);

        return $linhas;
    }
}
