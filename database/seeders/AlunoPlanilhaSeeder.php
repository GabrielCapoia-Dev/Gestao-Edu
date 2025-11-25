<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class AlunoPlanilhaSeeder extends Seeder
{
    // URL direta em CSV da aba "Matriculados"
    private const aluno_sheet = 'https://docs.google.com/spreadsheets/d/1S6ThiBaNmfnIbdM_bGH9Tr_O49yrxO2nehd7ecNphB8/export?format=csv&gid=527021056';

    // Tamanho do lote para UPSERT (evitar erro de "too many placeholders")
    private const UPSERT_CHUNK_SIZE = 500;

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

        $csv    = $response->body();
        $linhas = $this->parseCsv($csv);

        if (empty($linhas)) {
            $this->command->warn('CSV vazio ou sem dados.');
            return;
        }

        // Cabeçalho
        $header = array_map('trim', array_shift($linhas));

        // Mapeia índices importantes
        $idxEscola    = array_search('Escola', $header);
        $idxSeriacao  = array_search('Seriação', $header);
        $idxTurma     = array_search('Turma', $header);
        $idxTurno     = array_search('Turno', $header);
        $idxCgm       = array_search('CGM', $header);
        $idxNomeAluno = array_search('Nome do Aluno', $header);
        $idxDataNasc  = array_search('Data de Nasc', $header);
        $idxSexo      = array_search('Sexo', $header);

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

        // ========= PRÉ-CARGA DE TABELAS =========

        // Escolas por nome
        $escolasPorNome = Escola::all()->keyBy('nome');

        // Séries por nome (pra evitar query em cada linha)
        $seriesPorNome = Serie::all()->keyBy('nome');

        // CGMs existentes no banco (pra saber se é criação ou atualização)
        $existingPairs = Aluno::select('cgm', 'id_turma')
            ->get()
            ->mapWithKeys(fn($a) => [$a->cgm . '|' . $a->id_turma => true])
            ->all();


        // ========= CONTADORES E ESTRUTURAS =========

        $totalLinhasBase         = 0;
        $totalLinhasSemSeriacao  = 0;
        $totalLinhasIgnoradas    = 0;

        $totalCriadas            = 0;
        $totalAtualizadas        = 0;

        $motivosIgnorados = [
            'dados_minimos'          => 0,
            'sem_seriacao_srm'       => 0, // seriação vazia ou lixo (não "Sem Seriação")
            'escola_nao_encontrada'  => 0,
            'serie_nao_encontrada'   => 0,
            'sem_turma_ou_turno'     => 0,
            'data_nasc_invalida'     => 0,
            'data_nasc_vazia'        => 0,
        ];

        // Dados agregados para upsert
        // cgm => payload (última ocorrência vence)
        $rowsBase = [];

        // CGMs que têm linha "Sem Seriação"
        $frequentaSrmCgms = []; // set: cgm => true

        // Para log de repetidos (apenas base, seriação != "Sem Seriação")
        $cgmsBaseCount          = []; // cgm => qtd
        $primeiraOcorrenciaBase = []; // cgm => dados da 1ª ocorrência
        $alunosRepetidos        = []; // cgm => [nome, ocorrencias[]]

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

            $identAluno = "Linha {$linhaReal} (CGM '{$cgm}', Nome '{$nomeAluno}')";

            // 1) Dados mínimos
            if ($escolaNome === '' || $cgm === '' || $nomeAluno === '') {
                $this->command->warn("{$identAluno} ignorada: faltando Escola/CGM/Nome (dados mínimos).");
                $totalLinhasIgnoradas++;
                $motivosIgnorados['dados_minimos']++;
                continue;
            }

            // 2) Sem Seriação → só frequenta_srm depois, em lote (não conta como repetido)
            if ($seriacao === 'Sem Seriação') {
                $frequentaSrmCgms[$cgm] = true;
                $totalLinhasSemSeriacao++;
                continue;
            }

            // 2b) Seriação vazia (ruim, não é o caso de "Sem Seriação")
            if ($seriacao === '') {
                $this->command->warn("{$identAluno} ignorada: seriação vazia (sem informação de série).");
                $totalLinhasIgnoradas++;
                $motivosIgnorados['sem_seriacao_srm']++;
                continue;
            }

            // 2c) Contagem de repetidos (apenas base)
            if ($cgm !== '') {
                if (! isset($cgmsBaseCount[$cgm])) {
                    $cgmsBaseCount[$cgm] = 0;
                    $primeiraOcorrenciaBase[$cgm] = [
                        'linha'    => $linhaReal,
                        'escola'   => $escolaNome,
                        'seriacao' => $seriacao,
                        'turma'    => $turmaLetra,
                        'turno'    => $turno,
                        'nome'     => $nomeAluno,
                    ];
                }

                $cgmsBaseCount[$cgm]++;

                if ($cgmsBaseCount[$cgm] === 2) {
                    // cria registro quando vira repetido
                    $alunosRepetidos[$cgm] = [
                        'nome'        => $nomeAluno ?: $primeiraOcorrenciaBase[$cgm]['nome'],
                        'ocorrencias' => [
                            $primeiraOcorrenciaBase[$cgm],
                        ],
                    ];
                }

                if ($cgmsBaseCount[$cgm] >= 2) {
                    $alunosRepetidos[$cgm]['ocorrencias'][] = [
                        'linha'    => $linhaReal,
                        'escola'   => $escolaNome,
                        'seriacao' => $seriacao,
                        'turma'    => $turmaLetra,
                        'turno'    => $turno,
                        'nome'     => $nomeAluno,
                    ];
                }
            }

            // 3) Escola
            /** @var \App\Models\Escola|null $escola */
            $escola = $escolasPorNome[$escolaNome] ?? null;

            if (! $escola) {
                $this->command->warn("{$identAluno} ignorada: escola '{$escolaNome}' não encontrada no banco.");
                $totalLinhasIgnoradas++;
                $motivosIgnorados['escola_nao_encontrada']++;
                continue;
            }

            // 4) Série (lookup em memória, criando se não existir)
            /** @var \App\Models\Serie|null $serie */
            $serie = $seriesPorNome[$seriacao] ?? null;

            if (! $serie) {
                // Cria a série automaticamente se não existir
                $this->command->warn(
                    "{$identAluno}: série/seriação '{$seriacao}' não encontrada na tabela 'series'. " .
                        "Criando série automaticamente."
                );

                $serie = Serie::firstOrCreate(['nome' => $seriacao]);

                // Atualiza o cache em memória para próximas linhas com a mesma seriação
                $seriesPorNome[$seriacao] = $serie;
            }

            // 5) Turma / turno
            if ($turmaLetra === '' || $turno === '') {
                $this->command->warn("{$identAluno} ignorada: turma ou turno vazio.");
                $totalLinhasIgnoradas++;
                $motivosIgnorados['sem_turma_ou_turno']++;
                continue;
            }

            // 6) Data de nascimento
            if ($dataNascStr === '') {
                $this->command->warn("{$identAluno} ignorada: aluno sem data de nascimento.");
                $totalLinhasIgnoradas++;
                $motivosIgnorados['data_nasc_vazia']++;
                continue;
            }

            try {
                $dataNascimento = Carbon::createFromFormat('d/m/Y', $dataNascStr)->format('Y-m-d');
            } catch (\Throwable $e) {
                $this->command->warn("{$identAluno} ignorada: data de nascimento inválida '{$dataNascStr}'.");
                $totalLinhasIgnoradas++;
                $motivosIgnorados['data_nasc_invalida']++;
                continue;
            }

            // 7) Sexo
            $sexo = match ($sexoSigla) {
                'M'      => 'Masculino',
                'F'      => 'Feminino',
                default  => 'Masculino',
            };

            // 8) Turma
            /** @var \App\Models\Turma $turma */
            $turma = Turma::firstOrCreate(
                [
                    'id_escola' => $escola->id,
                    'id_serie'  => $serie->id,
                    'turma'     => $turmaLetra,
                    'turno'     => $turno,
                ]
            );

            // 9) Monta payload de base (sem frequenta_srm)
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

            $rowsBase[] = array_merge(['cgm' => $cgm], $payload);
            $totalLinhasBase++;
        }

        // ========= UPSERT EM LOTE (CHUNKS) =========

        if (! empty($rowsBase)) {
            // colunas de update = chaves do payload
            $updateColumns = array_keys(reset($rowsBase));

            // Transformar rowsBase (cgm => payload) em lista de linhas completas
            $rowsToUpsert = [];
            foreach ($rowsBase as $row) {
                $key = $row['cgm'] . '|' . $row['id_turma'];

                if (isset($existingPairs[$key])) {
                    $totalAtualizadas++;
                } else {
                    $totalCriadas++;
                }

                $rowsToUpsert[] = $row;
            }


            // Quebra em chunks para não estourar limite do MySQL
            foreach (array_chunk($rowsToUpsert, self::UPSERT_CHUNK_SIZE) as $chunk) {
                Aluno::upsert(
                    $chunk,
                    ['cgm', 'id_turma'],  // chave de conflito composta
                    $updateColumns
                );
            }
        }

        // ========= MARCA frequenta_srm EM LOTE =========

        if (! empty($frequentaSrmCgms)) {
            Aluno::whereIn('cgm', array_keys($frequentaSrmCgms))
                ->update(['frequenta_srm' => true]);
        }

        // ========= LOG FINAL =========

        $totalProcessadas = $totalLinhasBase + $totalLinhasSemSeriacao;

        $this->command->info("Importação concluída (otimizada).");
        $this->command->line("Linhas base processadas (não 'Sem Seriação'): {$totalLinhasBase}");
        $this->command->line("Linhas 'Sem Seriação' lidas:                  {$totalLinhasSemSeriacao}");
        $this->command->line("Linhas ignoradas:                            {$totalLinhasIgnoradas}");
        $this->command->line("Alunos criados (por CGM):                    {$totalCriadas}");
        $this->command->line("Alunos atualizados (por CGM):                {$totalAtualizadas}");
        $this->command->line("Total de linhas consideradas processadas:    {$totalProcessadas}");

        // Motivos ignorados
        $labelsMotivos = [
            'dados_minimos'          => 'dados mínimos ausentes (Escola / CGM / Nome)',
            'sem_seriacao_srm'       => 'seriação vazia/ruim (sem "Sem Seriação")',
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
                "  - {$qtde} linha(s) ignorada(s) por: {$descricao}."
            );
        }

        // ========= LOG DE ALUNOS REPETIDOS =========

        $this->command->info('Alunos repetidos (mesmo CGM em mais de uma linha de base, ignorando "Sem Seriação"):');

        if (empty($alunosRepetidos)) {
            $this->command->line('  Nenhum aluno repetido encontrado nessas condições.');
        } else {
            foreach ($alunosRepetidos as $cgm => $info) {
                $qtdOcorrencias = count($info['ocorrencias']);
                $this->command->line("  - CGM {$cgm} ({$info['nome']}) — {$qtdOcorrencias} ocorrências:");

                foreach ($info['ocorrencias'] as $oc) {
                    $this->command->line(
                        sprintf(
                            "      • Linha %d | Escola=%s | Seriação=%s | Turma=%s | Turno=%s",
                            $oc['linha'],
                            $oc['escola'],
                            $oc['seriacao'],
                            $oc['turma'],
                            $oc['turno'],
                        )
                    );
                }
            }
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
