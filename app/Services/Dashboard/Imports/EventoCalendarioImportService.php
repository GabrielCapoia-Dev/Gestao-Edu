<?php

namespace App\Services\Dashboard\Imports;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\ImportacaoEventoCalendarioAcao;
use App\Models\Enums\ImportacaoEventoCalendarioStatus;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\ImportacaoEventoCalendario;
use App\Models\ImportacaoEventoCalendarioLinha;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use App\Services\Dashboard\DashboardUserContextFactory;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class EventoCalendarioImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'fonte_externa',
        'identificador_externo',
        'titulo',
        'descricao',
        'categoria',
        'data_evento',
        'periodo',
        'hora_inicio',
        'hora_fim',
        'link_acao',
        'texto_botao',
        'cor',
        'enviar_escolas_especificas',
        'escolas_codigos',
        'series_codigos',
        'turnos',
        'turmas_codigos',
        'precisa_transporte',
    ];

    public function __construct(
        private readonly EventoCalendarioService $eventos,
        private readonly EventoCalendarioEscolaService $escolasEvento,
    ) {}

    public function preview(UploadedFile $arquivo, User $ator): ImportacaoEventoCalendario
    {
        Gate::forUser($ator)->authorize('create', ImportacaoEventoCalendario::class);
        $this->assertArquivo($arquivo);

        $uuid = (string) Str::uuid();
        $disk = (string) config('dashboard.imports.disk', 'local');
        $directory = trim((string) config('dashboard.imports.directory', 'imports/eventos-calendario'), '/');
        $extension = strtolower($arquivo->getClientOriginalExtension());
        $safeName = $uuid.'.'.$extension;
        $path = $arquivo->storeAs($directory, $safeName, $disk);

        if (! is_string($path)) {
            throw ValidationException::withMessages(['arquivo' => 'Não foi possível armazenar a planilha.']);
        }

        try {
            $storedAbsolutePath = Storage::disk($disk)->path($path);
            $rows = $this->readRows($storedAbsolutePath, $extension);
            $normalized = $this->normalizeRows($rows, $ator);

            $importacao = DB::transaction(function () use ($arquivo, $ator, $uuid, $disk, $path, $normalized, $storedAbsolutePath): ImportacaoEventoCalendario {
                $invalidas = collect($normalized)->where('acao', ImportacaoEventoCalendarioAcao::INVALIDA)->count();
                $importacao = ImportacaoEventoCalendario::query()->create([
                    'uuid' => $uuid,
                    'usuario_id' => $ator->getKey(),
                    'nome_arquivo' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
                    'disk' => $disk,
                    'caminho_arquivo' => $path,
                    'checksum' => hash_file('sha256', $storedAbsolutePath),
                    'status' => $invalidas === 0
                        ? ImportacaoEventoCalendarioStatus::PRONTA
                        : ImportacaoEventoCalendarioStatus::EM_PRE_VISUALIZACAO,
                    'total_linhas' => count($normalized),
                    'total_validas' => count($normalized) - $invalidas,
                    'total_invalidas' => $invalidas,
                    'relatorio' => ['preview_gerado_em' => now()->toIso8601String()],
                ]);

                $now = now();
                ImportacaoEventoCalendarioLinha::query()->insert(array_map(
                    static fn (array $row): array => [
                        'importacao_id' => $importacao->getKey(),
                        'numero_linha' => $row['numero_linha'],
                        'dados_originais' => json_encode($row['originais'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'dados_normalizados' => $row['normalizados'] === null
                            ? null
                            : json_encode($row['normalizados'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'erros' => $row['erros'] === []
                            ? null
                            : json_encode($row['erros'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'acao' => $row['acao']->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $normalized,
                ));

                return $importacao->fresh('linhas');
            });

            Storage::disk($disk)->delete($path);

            return $importacao;
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function confirm(ImportacaoEventoCalendario $importacao, User $ator): ImportacaoEventoCalendario
    {
        Gate::forUser($ator)->authorize('confirm', $importacao);

        if ($importacao->status !== ImportacaoEventoCalendarioStatus::PRONTA || $importacao->total_invalidas > 0) {
            throw ValidationException::withMessages([
                'importacao' => 'Corrija todas as linhas inválidas antes de confirmar a importação.',
            ]);
        }

        try {
            return DB::transaction(function () use ($importacao, $ator): ImportacaoEventoCalendario {
                $batch = ImportacaoEventoCalendario::query()->lockForUpdate()->findOrFail($importacao->getKey());

                if ($batch->status !== ImportacaoEventoCalendarioStatus::PRONTA) {
                    throw ValidationException::withMessages(['importacao' => 'Esta importação não está mais disponível para confirmação.']);
                }

                $batch->update(['status' => ImportacaoEventoCalendarioStatus::PROCESSANDO]);
                $rawRows = $batch->linhas()->get()->map(fn ($line): array => [
                    'numero_linha' => $line->numero_linha,
                    'dados' => $line->dados_originais,
                ])->all();
                $revalidated = $this->normalizeRows($rawRows, $ator);
                $invalid = collect($revalidated)->first(fn (array $row): bool => $row['acao'] === ImportacaoEventoCalendarioAcao::INVALIDA);

                if ($invalid) {
                    throw ValidationException::withMessages([
                        'importacao' => 'A linha '.$invalid['numero_linha'].' deixou de ser válida. Gere uma nova pré-visualização.',
                    ]);
                }

                $created = 0;
                $updated = 0;
                $processados = [];

                foreach ($revalidated as $row) {
                    $payload = $row['normalizados'];
                    $eventData = $payload['evento'];
                    $publicData = [];
                    $key = $eventData['fonte_externa'].'|'.$eventData['identificador_externo'];

                    if (isset($processados[$key])) {
                        $evento = $processados[$key]['evento'];
                        $acao = $processados[$key]['acao'];
                        $batch->linhas()->where('numero_linha', $row['numero_linha'])->firstOrFail()->update([
                            'evento_calendario_id' => $evento->getKey(),
                            'dados_normalizados' => $payload,
                            'erros' => null,
                            'acao' => $acao,
                        ]);

                        continue;
                    }

                    $existing = EventoCalendario::query()
                        ->where('fonte_externa', $eventData['fonte_externa'])
                        ->where('identificador_externo', $eventData['identificador_externo'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        Gate::forUser($ator)->authorize('update', $existing);
                        $evento = $this->eventos->atualizar($existing, $eventData, $publicData, $ator, $batch);
                        $acao = ImportacaoEventoCalendarioAcao::ATUALIZAR;
                        $updated++;
                    } else {
                        Gate::forUser($ator)->authorize('create', EventoCalendario::class);
                        $evento = $this->eventos->criar(
                            $eventData,
                            $publicData,
                            $ator,
                            EventoCalendarioOrigem::PLANILHA,
                            $batch,
                            false,
                        );
                        $acao = ImportacaoEventoCalendarioAcao::CRIAR;
                        $created++;
                    }

                    $processados[$key] = ['evento' => $evento, 'acao' => $acao];

                    $batch->linhas()->where('numero_linha', $row['numero_linha'])->firstOrFail()->update([
                        'evento_calendario_id' => $evento->getKey(),
                        'dados_normalizados' => $payload,
                        'erros' => null,
                        'acao' => $acao,
                    ]);
                }

                $batch->update([
                    'status' => ImportacaoEventoCalendarioStatus::CONCLUIDA,
                    'total_criadas' => $created,
                    'total_atualizadas' => $updated,
                    'confirmada_em' => now(),
                    'relatorio' => [
                        'concluida_em' => now()->toIso8601String(),
                        'criadas' => $created,
                        'atualizadas' => $updated,
                    ],
                ]);

                Storage::disk($batch->disk)->delete($batch->caminho_arquivo);

                return $batch->fresh('linhas');
            });
        } catch (Throwable $exception) {
            $importacao->refresh();

            if (! in_array($importacao->status, [
                ImportacaoEventoCalendarioStatus::CONCLUIDA,
                ImportacaoEventoCalendarioStatus::CANCELADA,
            ], true)) {
                $importacao->update([
                    'status' => ImportacaoEventoCalendarioStatus::FALHOU,
                    'relatorio' => ['erro' => 'A confirmação foi revertida integralmente.'],
                ]);
            }

            throw $exception;
        }
    }

    public function cancel(ImportacaoEventoCalendario $importacao, User $ator): void
    {
        Gate::forUser($ator)->authorize('cancel', $importacao);

        if (! in_array($importacao->status, [
            ImportacaoEventoCalendarioStatus::EM_PRE_VISUALIZACAO,
            ImportacaoEventoCalendarioStatus::PRONTA,
        ], true)) {
            throw ValidationException::withMessages(['importacao' => 'Esta importação não pode mais ser cancelada.']);
        }

        $importacao->update([
            'status' => ImportacaoEventoCalendarioStatus::CANCELADA,
            'cancelada_em' => now(),
            'relatorio' => ['cancelada_em' => now()->toIso8601String()],
        ]);
        Storage::disk($importacao->disk)->delete($importacao->caminho_arquivo);
    }

    public function template(User $ator): BinaryFileResponse
    {
        Gate::forUser($ator)->authorize('exportTemplate', ImportacaoEventoCalendario::class);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Eventos');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray($this->exampleRow(), null, 'A2');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex(count(self::HEADERS)).'2');

        foreach (range(1, count(self::HEADERS)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instruções');
        $instructions->fromArray([
            ['Campo', 'Orientação'],
            ['fonte_externa + identificador_externo', 'Chave idempotente. Uma nova importação atualiza o mesmo evento.'],
            ['data_evento', 'Use DD/MM/AAAA. Eventos manuais sempre ocorrem em um único dia.'],
            ['período e horários', 'Período aceita manha, tarde, noite ou dia_todo. Horários informados manualmente têm precedência.'],
            ['distribuição', 'Use Sim em enviar_escolas_especificas e combine escolas, séries, turnos e turmas na mesma linha. Campos vazios significam todas as opções disponíveis.'],
            ['turnos', 'Valores aceitos: manha, tarde, noite e integral. Quando vazio, o turno é inferido pelos horários do evento.'],
            ['transporte', 'Informe Sim para estimar os estudantes das turmas filtradas. A quantidade é calculada pelo sistema.'],
            ['listas', 'Separe vários valores com |.'],
            ['segurança', 'Fórmulas, macros, referências fora do seu escopo e relacionamentos inativos são rejeitados.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(38);
        $instructions->getColumnDimension('B')->setWidth(95);

        $references = $spreadsheet->createSheet();
        $references->setTitle('Referências');
        $references->fromArray(['Tipo', 'Identificador', 'Nome'], null, 'A1');
        $context = app(DashboardUserContextFactory::class)->make($ator);
        $schools = Escola::query()->select(['codigo', 'nome'])->where('ativo', true)->orderBy('nome');

        if (! $context->escopoGlobal) {
            $schools->whereKey($context->escolaIds);
        }

        $referenceRows = $schools->get()
            ->map(fn (Escola $school): array => ['Escola (código)', (string) $school->codigo, $school->nome])
            ->merge(collect(EventoCalendarioCategoria::cases())->map(fn ($item): array => ['Categoria', $item->value, $item->label()]))
            ->merge(Serie::query()
                ->whereHas('turmas', function (Builder $query) use ($context): void {
                    if (! $context->escopoGlobal) {
                        $query->whereIn('id_escola', $context->escolaIds);
                    }
                })
                ->orderBy('nome')
                ->get(['codigo', 'nome'])
                ->map(fn (Serie $serie): array => ['Série (código)', (string) $serie->codigo, $serie->nome]))
            ->merge(Turma::query()
                ->when(! $context->escopoGlobal, fn (Builder $query): Builder => $query->whereIn('id_escola', $context->escolaIds))
                ->with(['serie:id,nome', 'escola:id,nome'])
                ->orderBy('nome')
                ->get(['id', 'codigo', 'nome', 'id_serie', 'id_escola'])
                ->map(fn (Turma $turma): array => [
                    'Turma (código)',
                    (string) $turma->codigo,
                    collect([$turma->escola?->nome, $turma->serie?->nome, $turma->nome])->filter()->join(' - '),
                ]))
            ->merge(collect([
                'manha' => 'Manhã',
                'tarde' => 'Tarde',
                'noite' => 'Noite',
                'integral' => 'Integral',
            ])->map(fn (string $nome, string $codigo): array => ['Turno', $codigo, $nome]))
            ->merge(collect(EventoCalendarioCor::cases())->map(fn ($item): array => ['Cor', $item->value, $item->label()]))
            ->values()
            ->all();
        $references->fromArray($referenceRows, null, 'A2');

        foreach ($referenceRows as $rowOffset => $referenceRow) {
            foreach (array_values($referenceRow) as $columnOffset => $value) {
                $references->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($columnOffset + 1).($rowOffset + 2),
                    (string) $value,
                    DataType::TYPE_STRING,
                );
            }
        }

        $references->getColumnDimension('A')->setWidth(24);
        $references->getColumnDimension('B')->setWidth(38);
        $references->getColumnDimension('C')->setWidth(70);

        $path = tempnam(sys_get_temp_dir(), 'modelo_eventos_');

        if ($path === false) {
            $spreadsheet->disconnectWorksheets();

            throw ValidationException::withMessages([
                'modelo' => 'Não foi possível gerar o modelo de importação.',
            ]);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'modelo-importacao-eventos.xlsx')->deleteFileAfterSend(true);
    }

    private function assertArquivo(UploadedFile $arquivo): void
    {
        $extension = strtolower($arquivo->getClientOriginalExtension());
        $maxBytes = (int) config('dashboard.imports.max_file_size_kb', 5120) * 1024;

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw ValidationException::withMessages(['arquivo' => 'Envie uma planilha XLSX ou CSV.']);
        }

        if (! $arquivo->isValid() || $arquivo->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['arquivo' => 'A planilha é inválida ou excede o limite de 5 MB.']);
        }
    }

    /** @return list<array{numero_linha:int,dados:array<string,mixed>}> */
    private function readRows(string $path, string $extension): array
    {
        $maxRows = max(1, (int) config('dashboard.imports.max_rows', 1000));
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $reader->setReadFilter(new class($maxRows + 2, count(self::HEADERS)) implements IReadFilter
        {
            public function __construct(private readonly int $maxRow, private readonly int $maxColumn) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= $this->maxRow
                    && Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumn;
            }
        });

        if ($reader instanceof Csv) {
            $contents = file_get_contents($path);

            if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
                throw ValidationException::withMessages(['arquivo' => 'O arquivo CSV deve estar codificado em UTF-8.']);
            }

            $reader->setDelimiter($this->detectDelimiter($path));
            $reader->setInputEncoding('UTF-8');
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->rangeToArray(
            'A1:'.Coordinate::stringFromColumnIndex(count(self::HEADERS)).($maxRows + 2),
            null,
            false,
            true,
            false,
        );
        $spreadsheet->disconnectWorksheets();

        $headers = array_map(fn ($value): string => $this->header((string) $value), array_shift($matrix) ?? []);

        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages([
                'arquivo' => 'As colunas da planilha não correspondem ao modelo oficial. Baixe o modelo atualizado.',
            ]);
        }

        $rows = [];

        foreach ($matrix as $offset => $values) {
            if (collect($values)->every(fn ($value): bool => blank($value))) {
                continue;
            }

            $this->assertNoFormulaValues($values, $offset + 2);

            $rows[] = [
                'numero_linha' => $offset + 2,
                'dados' => array_combine(self::HEADERS, array_slice(array_pad($values, count(self::HEADERS), null), 0, count(self::HEADERS))),
            ];
        }

        if (count($rows) > $maxRows) {
            throw ValidationException::withMessages(['arquivo' => "A planilha pode conter no máximo {$maxRows} linhas de dados."]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['arquivo' => 'A planilha não possui linhas de eventos.']);
        }

        return $rows;
    }

    /**
     * @param list<array{numero_linha:int,dados:array<string,mixed>}> $rows
     * @return list<array{numero_linha:int,originais:array<string,mixed>,normalizados:?array,erros:array,acao:ImportacaoEventoCalendarioAcao}>
     */
    private function normalizeRows(array $rows, User $ator): array
    {
        $references = $this->referenceMapsNova($rows);
        $result = [];
        $indicesPorChave = [];

        foreach ($rows as $row) {
            $raw = $row['dados'];

            try {
                $normalized = $this->normalizeRowNova(
                    $raw,
                    $references,
                    $ator,
                );
                $key = $normalized['evento']['fonte_externa'].'|'.$normalized['evento']['identificador_externo'];
                $indice = count($result);
                $indicesPorChave[$key][] = $indice;
                $result[] = [
                    'numero_linha' => $row['numero_linha'],
                    'originais' => $raw,
                    'normalizados' => $normalized,
                    'erros' => [],
                    'acao' => ImportacaoEventoCalendarioAcao::CRIAR,
                ];
            } catch (ValidationException $exception) {
                $result[] = [
                    'numero_linha' => $row['numero_linha'],
                    'originais' => $raw,
                    'normalizados' => null,
                    'erros' => $exception->errors(),
                    'acao' => ImportacaoEventoCalendarioAcao::INVALIDA,
                ];
            } catch (AuthorizationException) {
                $result[] = [
                    'numero_linha' => $row['numero_linha'],
                    'originais' => $raw,
                    'normalizados' => null,
                    'erros' => ['autorizacao' => ['Você não possui autorização para criar ou atualizar este evento.']],
                    'acao' => ImportacaoEventoCalendarioAcao::INVALIDA,
                ];
            }
        }

        foreach ($indicesPorChave as $key => $indices) {
            try {
                if (count($indices) > 1) {
                    throw ValidationException::withMessages([
                        'identificador_externo' => 'Cada chave externa deve ocupar uma única linha. Combine os filtros com | no mesmo evento.',
                    ]);
                }

                $payload = $result[$indices[0]]['normalizados'];
                $existing = $references['eventos'][$key] ?? null;

                if ($existing?->trashed()) {
                    throw ValidationException::withMessages([
                        'identificador_externo' => 'A chave externa pertence a um evento excluído. Restaure-o antes de importar.',
                    ]);
                }

                if ($existing) {
                    Gate::forUser($ator)->authorize('update', $existing);
                    $acao = ImportacaoEventoCalendarioAcao::ATUALIZAR;
                } else {
                    Gate::forUser($ator)->authorize('create', EventoCalendario::class);
                    $acao = ImportacaoEventoCalendarioAcao::CRIAR;
                }

                foreach ($indices as $indice) {
                    $result[$indice]['normalizados'] = $payload;
                    $result[$indice]['acao'] = $acao;
                }
            } catch (ValidationException $exception) {
                foreach ($indices as $indice) {
                    $result[$indice]['normalizados'] = null;
                    $result[$indice]['erros'] = $exception->errors();
                    $result[$indice]['acao'] = ImportacaoEventoCalendarioAcao::INVALIDA;
                }
            } catch (AuthorizationException) {
                foreach ($indices as $indice) {
                    $result[$indice]['normalizados'] = null;
                    $result[$indice]['erros'] = ['autorizacao' => ['Você não possui autorização para criar ou atualizar este evento.']];
                    $result[$indice]['acao'] = ImportacaoEventoCalendarioAcao::INVALIDA;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $maps
     * @return array<string,mixed>
     */
    private function normalizeRowNova(
        array $raw,
        array $maps,
        User $ator,
    ): array {
        $source = mb_strtolower(trim((string) ($raw['fonte_externa'] ?? '')));
        $externalId = trim((string) ($raw['identificador_externo'] ?? ''));
        $data = $this->dateOnly($raw['data_evento'] ?? null, 'data_evento');
        $periodo = $this->periodo($raw['periodo'] ?? null);
        $horaInicio = filled($raw['hora_inicio'] ?? null)
            ? $this->time($raw['hora_inicio'], 'hora_inicio')
            : ($periodo['inicio'] ?? null);
        $horaFim = filled($raw['hora_fim'] ?? null)
            ? $this->time($raw['hora_fim'], 'hora_fim')
            : ($periodo['fim'] ?? null);

        if (! $horaInicio || ! $horaFim || $horaFim <= $horaInicio) {
            throw ValidationException::withMessages([
                'hora_fim' => 'Informe horários válidos e mantenha o horário final posterior ao inicial.',
            ]);
        }

        $enviarEspecificas = $this->boolean(
            $raw['enviar_escolas_especificas'] ?? null,
            'enviar_escolas_especificas',
        );
        $escolaIds = $this->resolveList($raw['escolas_codigos'] ?? null, $maps['escolas'], 'escolas_codigos');
        $serieIds = $this->resolveList($raw['series_codigos'] ?? null, $maps['series'], 'series_codigos');
        $turmaIds = $this->resolveList($raw['turmas_codigos'] ?? null, $maps['turmas'], 'turmas_codigos');
        $turnos = collect($this->listValues($raw['turnos'] ?? null))
            ->map(fn (string $turno): string => mb_strtolower($turno))
            ->all();

        if ($enviarEspecificas && $escolaIds === [] && $turnos === []) {
            $turnos = $this->turnosPorHorario($horaInicio, $horaFim);
        }
        $precisaTransporte = filled($raw['precisa_transporte'] ?? null)
            ? $this->boolean($raw['precisa_transporte'], 'precisa_transporte')
            : false;
        $agendamentos = [];

        if (! $enviarEspecificas) {
            if ($escolaIds !== [] || $serieIds !== [] || $turmaIds !== [] || $turnos !== [] || $precisaTransporte) {
                throw ValidationException::withMessages([
                    'enviar_escolas_especificas' => 'Ative a distribuição específica antes de informar filtros escolares ou transporte.',
                ]);
            }

            $contexto = app(DashboardUserContextFactory::class)->make($ator);

            if (! $contexto->escopoGlobal && $contexto->escolaIds === []) {
                throw ValidationException::withMessages([
                    'enviar_escolas_especificas' => 'Seu usuário não possui escolas autorizadas para importar este evento.',
                ]);
            }
        } else {
            $agendamentos = $this->escolasEvento->gerarPorFiltros([
                'selecionar_todas_escolas' => $escolaIds === [],
                'escola_ids' => $escolaIds,
                'serie_ids' => $serieIds,
                'turnos' => $turnos,
                'turma_ids' => $turmaIds,
                'precisa_transporte' => $precisaTransporte,
            ], $ator, $horaInicio, $horaFim);
        }

        $event = [
            'fonte_externa' => $source,
            'identificador_externo' => $externalId,
            'titulo' => trim((string) ($raw['titulo'] ?? '')),
            'descricao' => $this->nullableString($raw['descricao'] ?? null),
            'categoria' => mb_strtolower(trim((string) ($raw['categoria'] ?? ''))),
            'prioridade' => DashboardPrioridade::Normal->value,
            'data_evento' => $data->toDateString(),
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
            'inserir_link' => filled($raw['link_acao'] ?? null),
            'link_acao' => $this->nullableString($raw['link_acao'] ?? null),
            'texto_botao' => $this->nullableString($raw['texto_botao'] ?? null),
            'ativo' => true,
            'cor' => mb_strtolower(trim((string) ($raw['cor'] ?? ''))),
            'enviar_todas_escolas' => ! $enviarEspecificas,
            'escolas_agendadas' => $agendamentos,
            'origem' => EventoCalendarioOrigem::PLANILHA->value,
        ];

        $validator = validator($event, [
            'fonte_externa' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'identificador_externo' => ['required', 'string', 'max:160'],
            'titulo' => ['required', 'string', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'categoria' => ['required', Rule::enum(EventoCalendarioCategoria::class)],
            'prioridade' => ['required', Rule::enum(DashboardPrioridade::class)],
            'link_acao' => ['nullable', 'string', 'max:2048'],
            'texto_botao' => ['nullable', 'string', 'max:80'],
            'ativo' => ['required', 'boolean'],
            'cor' => ['required', Rule::enum(EventoCalendarioCor::class)],
        ], [
            'required' => 'Campo obrigatório.',
            'max' => 'Valor acima do limite permitido.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if ($event['link_acao'] && ! $this->safeLink($event['link_acao'])) {
            throw ValidationException::withMessages([
                'link_acao' => 'Informe uma URL HTTP(S) ou caminho interno iniciado por /.',
            ]);
        }

        if (! Gate::forUser($ator)->allows('publish', EventoCalendario::class)) {
            throw ValidationException::withMessages([
                'ativo' => 'Você não possui permissão para importar eventos publicados.',
            ]);
        }

        return ['evento' => $event];
    }

    /** @param list<array{numero_linha:int,dados:array<string,mixed>}> $rows @return array<string,mixed> */
    private function referenceMapsNova(array $rows): array
    {
        $values = fn (string $field): array => collect($rows)
            ->flatMap(fn (array $row): array => $this->listValues($row['dados'][$field] ?? null))
            ->unique()
            ->values()
            ->all();
        $schoolCodes = $values('escolas_codigos');
        $serieCodes = $values('series_codigos');
        $turmaCodes = $values('turmas_codigos');
        $sources = collect($rows)->pluck('dados.fonte_externa')
            ->map(fn ($value): string => mb_strtolower(trim((string) $value)))
            ->filter()->unique()->all();
        $externalIds = collect($rows)->pluck('dados.identificador_externo')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()->unique()->all();

        return [
            'escolas' => Escola::query()->where('ativo', true)->whereIn('codigo', $schoolCodes)->get()
                ->groupBy('codigo')->map(fn ($items) => $items->count() === 1 ? $items->first() : null)->filter(),
            'series' => Serie::query()->whereIn('codigo', $serieCodes)->get()->keyBy('codigo'),
            'turmas' => Turma::query()->whereIn('codigo', $turmaCodes)->get()->keyBy('codigo'),
            'eventos' => EventoCalendario::query()->withTrashed()
                ->whereIn('fonte_externa', $sources)
                ->whereIn('identificador_externo', $externalIds)
                ->get()
                ->keyBy(fn (EventoCalendario $event): string => $event->fonte_externa.'|'.$event->identificador_externo),
        ];
    }

    /** @return list<int> */
    private function resolveList(mixed $raw, $map, string $field): array
    {
        $resolved = [];

        foreach ($this->listValues($raw) as $value) {
            $model = $map->get($value) ?? $map->get(mb_strtolower($value));

            if (! $model) {
                throw ValidationException::withMessages([$field => "Referência não encontrada: {$value}."]);
            }

            $resolved[] = (int) $model->getKey();
        }

        return array_values(array_unique($resolved));
    }

    /** @return list<string> */
    private function listValues(mixed $value): array
    {
        return collect(preg_split('/[|;\r\n]+/', trim((string) $value)) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function dateOnly(mixed $value, string $field): CarbonImmutable
    {
        try {
            if (is_numeric($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            $value = trim((string) $value);

            foreach (['d/m/Y', 'Y-m-d'] as $format) {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $value, config('dashboard.calendar.timezone'));
                $errors = \DateTimeImmutable::getLastErrors();

                if ($parsed && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return $parsed->startOfDay();
                }
            }
        } catch (Throwable) {
            // A mensagem padronizada é emitida abaixo.
        }

        throw ValidationException::withMessages([$field => 'Use uma data válida no formato DD/MM/AAAA.']);
    }

    private function time(mixed $value, string $field): string
    {
        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i');
            }

            $value = trim((string) $value);

            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
                return substr($value, 0, 5);
            }
        } catch (Throwable) {
            // A mensagem padronizada é emitida abaixo.
        }

        throw ValidationException::withMessages([$field => 'Use um horário válido no formato HH:MM.']);
    }

    /** @return array{inicio:string,fim:string}|null */
    private function periodo(mixed $value): ?array
    {
        $normalizado = Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->trim()
            ->toString();

        if ($normalizado === '') {
            return null;
        }

        return match ($normalizado) {
            'manha' => ['inicio' => '08:00', 'fim' => '12:00'],
            'tarde' => ['inicio' => '13:30', 'fim' => '17:30'],
            'noite' => ['inicio' => '19:00', 'fim' => '22:00'],
            'dia_todo', 'diatodo' => ['inicio' => '08:00', 'fim' => '17:30'],
            default => throw ValidationException::withMessages([
                'periodo' => 'Use manha, tarde, noite ou dia_todo.',
            ]),
        };
    }

    private function boolean(mixed $value, string $field): bool
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'sim', 's', 'yes' => true,
            '0', 'false', 'não', 'nao', 'n', 'no' => false,
            default => throw ValidationException::withMessages([$field => 'Use Sim ou Não.']),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function safeLink(string $link): bool
    {
        return (str_starts_with($link, '/') && ! str_starts_with($link, '//'))
            || in_array(strtolower((string) parse_url($link, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /** @return list<string> */
    private function turnosPorHorario(string $inicio, string $fim): array
    {
        [$horaInicio, $minutoInicio] = array_map('intval', explode(':', $inicio));
        [$horaFim, $minutoFim] = array_map('intval', explode(':', $fim));
        $inicioMinutos = ($horaInicio * 60) + $minutoInicio;
        $fimMinutos = ($horaFim * 60) + $minutoFim;
        $turnos = [];

        if ($inicioMinutos < 750 && $fimMinutos > 360) {
            $turnos[] = 'manha';
        }

        if ($inicioMinutos < 1110 && $fimMinutos > 750) {
            $turnos[] = 'tarde';
        }

        if ($fimMinutos > 1110) {
            $turnos[] = 'noite';
        }

        if ($inicioMinutos <= 480 && $fimMinutos >= 1050) {
            $turnos[] = 'integral';
        }

        return array_values(array_unique($turnos));
    }

    private function header(string $value): string
    {
        return mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $value)));
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        $line = $handle ? (string) fgets($handle) : '';

        if ($handle) {
            fclose($handle);
        }

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /** @param list<mixed> $values */
    private function assertNoFormulaValues(array $values, int $line): void
    {
        foreach ($values as $value) {
            $text = ltrim((string) $value);

            if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
                throw ValidationException::withMessages([
                    'arquivo' => "A linha {$line} contém fórmula ou conteúdo executável. Use somente valores.",
                ]);
            }
        }
    }

    /** @return list<mixed> */
    private function exampleRow(): array
    {
        return [
            'secretaria-educacao',
            'reuniao-2026-001',
            'Reunião de gestores',
            'Alinhamento mensal da equipe.',
            'administrativo',
            now()->addWeek()->format('d/m/Y'),
            'manha',
            '08:00',
            '10:30',
            '/admin',
            'Acessar',
            'azul',
            'Sim',
            null,
            null,
            'manha',
            null,
            'Não',
        ];
    }
}
