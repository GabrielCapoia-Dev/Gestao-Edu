<?php

namespace App\Services\Dashboard\Imports;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Enums\ImportacaoEventoCalendarioAcao;
use App\Models\Enums\ImportacaoEventoCalendarioStatus;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\FuncaoAdministrativa;
use App\Models\ImportacaoEventoCalendario;
use App\Models\ImportacaoEventoCalendarioLinha;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\PublicoAlvoService;
use App\Services\Dashboard\PublicoAlvoOptionsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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
        'prioridade',
        'data_evento',
        'periodo',
        'hora_inicio',
        'hora_fim',
        'link_acao',
        'texto_botao',
        'ativo',
        'cor',
        'enviar_todas_escolas',
        'escola_codigo',
        'hora_inicio_escola',
        'hora_fim_escola',
        'precisa_transporte',
        'escopo_transporte',
        'series_codigos',
        'turmas_codigos',
        'todos_usuarios',
        'modo_correspondencia',
        'usuarios_emails',
        'roles',
        'permissoes',
        'funcoes_administrativas',
    ];

    public function __construct(
        private readonly EventoCalendarioService $eventos,
        private readonly EventoCalendarioEscolaService $escolasEvento,
        private readonly PublicoAlvoService $publicos,
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
                    $publicData = $payload['publico_alvo'];
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
            ['escolas', 'Para escolas específicas, repita a chave externa em uma linha por escola.'],
            ['transporte', 'Use toda_unidade, series ou turmas. A estimativa é calculada pelo sistema.'],
            ['listas', 'Separe vários valores com |.'],
            ['todos_usuarios', 'Use Sim ou Não. Se Sim, deixe os demais critérios de público vazios.'],
            ['público-alvo avançado', 'Usuários, níveis, permissões e cargos só são aplicados quando o responsável possui a permissão específica.'],
            ['segurança', 'Fórmulas, macros, referências fora do seu escopo e relacionamentos inativos são rejeitados.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(38);
        $instructions->getColumnDimension('B')->setWidth(95);

        $references = $spreadsheet->createSheet();
        $references->setTitle('Referências');
        $references->fromArray(['Tipo', 'Identificador', 'Nome'], null, 'A1');
        $options = app(PublicoAlvoOptionsService::class);
        $context = app(DashboardUserContextFactory::class)->make($ator);
        $schools = Escola::query()->select(['codigo', 'nome'])->where('ativo', true)->orderBy('nome');

        if (! $context->escopoGlobal) {
            $schools->whereKey($context->escolaIds);
        }

        $audienceReferences = collect();

        if (Gate::forUser($ator)->allows('manageAudience', EventoCalendario::class)) {
            $audienceReferences = collect($options->roles())
                ->map(fn (string $name): array => ['Nível de acesso', $name, $name])
                ->merge(collect($options->permissoes())->map(
                    fn (string $name): array => ['Permissão', $name, $name],
                ))
                ->merge(FuncaoAdministrativa::query()->where('ativo', true)->orderBy('nome')->get(['codigo', 'nome'])->map(
                    fn (FuncaoAdministrativa $funcao): array => ['Função administrativa (código)', (string) $funcao->codigo, $funcao->nome],
                ));
        }

        $referenceRows = $schools->get()
            ->map(fn (Escola $school): array => ['Escola (código)', (string) $school->codigo, $school->nome])
            ->merge($audienceReferences)
            ->merge(collect(EventoCalendarioCategoria::cases())->map(fn ($item): array => ['Categoria', $item->value, $item->label()]))
            ->merge(collect(DashboardPrioridade::cases())->map(fn ($item): array => ['Prioridade', $item->value, $item->label()]))
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
            ->merge(collect(EventoCalendarioTransporteEscopo::cases())->map(fn ($item): array => ['Escopo de transporte', $item->value, $item->label()]))
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
        $audienceValidationCache = [];
        $indicesPorChave = [];

        foreach ($rows as $row) {
            $raw = $row['dados'];

            try {
                $normalized = $this->normalizeRowNova(
                    $raw,
                    $references,
                    $ator,
                    $audienceValidationCache,
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
                $primeiro = $result[$indices[0]]['normalizados'];
                $eventoBase = Arr::except($primeiro['evento'], 'escolas_agendadas');
                $publicoBase = $primeiro['publico_alvo'];
                $todas = (bool) $eventoBase['enviar_todas_escolas'];
                $agendamentos = [];
                $escolasVistas = [];

                foreach ($indices as $indice) {
                    $payload = $result[$indice]['normalizados'];

                    if (
                        Arr::except($payload['evento'], 'escolas_agendadas') !== $eventoBase
                        || $payload['publico_alvo'] !== $publicoBase
                    ) {
                        throw ValidationException::withMessages([
                            'evento' => 'Linhas com a mesma chave externa devem repetir exatamente os dados gerais e o público-alvo.',
                        ]);
                    }

                    $linhasEscola = $payload['evento']['escolas_agendadas'];

                    if ($todas && (count($indices) > 1 || $linhasEscola !== [])) {
                        throw ValidationException::withMessages([
                            'enviar_todas_escolas' => 'Eventos enviados para todas as escolas devem ocupar uma única linha.',
                        ]);
                    }

                    foreach ($linhasEscola as $linhaEscola) {
                        $escolaId = (int) $linhaEscola['escola_id'];

                        if (isset($escolasVistas[$escolaId])) {
                            throw ValidationException::withMessages([
                                'escola_codigo' => 'A mesma escola não pode aparecer duas vezes para a mesma chave externa.',
                            ]);
                        }

                        $escolasVistas[$escolaId] = true;
                        $agendamentos[] = $linhaEscola;
                    }
                }

                $eventoBase['escolas_agendadas'] = $agendamentos;
                $payloadAgrupado = ['evento' => $eventoBase, 'publico_alvo' => $publicoBase];
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
                    $result[$indice]['normalizados'] = $payloadAgrupado;
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
     * @param array<string,array{value:mixed,errors:?array}> $audienceValidationCache
     * @return array<string,mixed>
     */
    private function normalizeRowNova(
        array $raw,
        array $maps,
        User $ator,
        array &$audienceValidationCache,
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

        $enviarTodas = $this->boolean($raw['enviar_todas_escolas'] ?? null, 'enviar_todas_escolas');
        $schoolCode = trim((string) ($raw['escola_codigo'] ?? ''));
        $school = $schoolCode !== '' ? ($maps['escolas'][$schoolCode] ?? null) : null;
        $agendamentos = [];

        if ($enviarTodas) {
            if ($schoolCode !== '' || filled($raw['precisa_transporte'] ?? null)) {
                throw ValidationException::withMessages([
                    'escola_codigo' => 'Deixe escola e transporte vazios quando o evento for enviado para todas as escolas.',
                ]);
            }

            $contexto = app(DashboardUserContextFactory::class)->make($ator);

            if (! $contexto->escopoGlobal && $contexto->escolaIds === []) {
                throw ValidationException::withMessages([
                    'enviar_todas_escolas' => 'Seu usuário não possui escolas autorizadas para importar este evento.',
                ]);
            }
        } else {
            if (! $school) {
                throw ValidationException::withMessages([
                    'escola_codigo' => 'Informe o código de uma escola ativa do seu contexto.',
                ]);
            }

            $precisaTransporte = filled($raw['precisa_transporte'] ?? null)
                ? $this->boolean($raw['precisa_transporte'], 'precisa_transporte')
                : false;
            $escopoTransporte = $precisaTransporte
                ? mb_strtolower(trim((string) ($raw['escopo_transporte'] ?? '')))
                : null;
            $seriesIds = $precisaTransporte && $escopoTransporte === EventoCalendarioTransporteEscopo::SERIES->value
                ? $this->resolveList($raw['series_codigos'] ?? null, $maps['series'], 'series_codigos')
                : [];
            $turmasIds = $precisaTransporte && $escopoTransporte === EventoCalendarioTransporteEscopo::TURMAS->value
                ? $this->resolveList($raw['turmas_codigos'] ?? null, $maps['turmas'], 'turmas_codigos')
                : [];
            $horaInicioEscola = filled($raw['hora_inicio_escola'] ?? null)
                ? $this->time($raw['hora_inicio_escola'], 'hora_inicio_escola')
                : $horaInicio;
            $horaFimEscola = filled($raw['hora_fim_escola'] ?? null)
                ? $this->time($raw['hora_fim_escola'], 'hora_fim_escola')
                : $horaFim;

            $agendamentos = $this->escolasEvento->normalizar([[
                'escola_id' => $school->getKey(),
                'hora_inicio' => $horaInicioEscola,
                'hora_fim' => $horaFimEscola,
                'precisa_transporte' => $precisaTransporte,
                'escopo_transporte' => $escopoTransporte,
                'series_ids' => $seriesIds,
                'turmas_ids' => $turmasIds,
            ]], $ator, $horaInicio, $horaFim);
        }

        $event = [
            'fonte_externa' => $source,
            'identificador_externo' => $externalId,
            'titulo' => trim((string) ($raw['titulo'] ?? '')),
            'descricao' => $this->nullableString($raw['descricao'] ?? null),
            'categoria' => mb_strtolower(trim((string) ($raw['categoria'] ?? ''))),
            'prioridade' => mb_strtolower(trim((string) ($raw['prioridade'] ?? ''))),
            'data_evento' => $data->toDateString(),
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
            'inserir_link' => filled($raw['link_acao'] ?? null),
            'link_acao' => $this->nullableString($raw['link_acao'] ?? null),
            'texto_botao' => $this->nullableString($raw['texto_botao'] ?? null),
            'ativo' => $this->boolean($raw['ativo'] ?? null, 'ativo'),
            'cor' => mb_strtolower(trim((string) ($raw['cor'] ?? ''))),
            'enviar_todas_escolas' => $enviarTodas,
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

        if ($event['ativo'] && ! Gate::forUser($ator)->allows('publish', EventoCalendario::class)) {
            throw ValidationException::withMessages([
                'ativo' => 'Você não possui permissão para importar eventos publicados.',
            ]);
        }

        $podeGerenciarPublico = Gate::forUser($ator)->allows('manageAudience', EventoCalendario::class);
        $public = $podeGerenciarPublico
            ? [
                'todos_usuarios' => $this->boolean($raw['todos_usuarios'] ?? null, 'todos_usuarios'),
                'modo_correspondencia' => mb_strtolower(trim((string) ($raw['modo_correspondencia'] ?: 'qualquer'))),
                'usuarios_ids' => $this->resolveList($raw['usuarios_emails'] ?? null, $maps['usuarios'], 'usuarios_emails'),
                'roles_ids' => $this->resolveList($raw['roles'] ?? null, $maps['roles'], 'roles'),
                'permissoes_ids' => $this->resolveList($raw['permissoes'] ?? null, $maps['permissoes'], 'permissoes'),
                'funcoes_administrativas_ids' => $this->resolveList($raw['funcoes_administrativas'] ?? null, $maps['funcoes'], 'funcoes_administrativas'),
                'escolas_ids' => [],
                'setores_ids' => [],
            ]
            : [
                'todos_usuarios' => $enviarTodas,
                'modo_correspondencia' => 'qualquer',
                'usuarios_ids' => [],
                'roles_ids' => [],
                'permissoes_ids' => [],
                'funcoes_administrativas_ids' => [],
                'escolas_ids' => $enviarTodas ? [] : collect($agendamentos)->pluck('escola_id')->all(),
                'setores_ids' => [],
            ];
        $public = $this->rememberValidation(
            $audienceValidationCache,
            $this->audienceCacheKey($public),
            fn (): array => $this->publicos->validar($ator, $public),
        );

        return ['evento' => $event, 'publico_alvo' => $public];
    }

    /**
     * @param array<string,array{value:mixed,errors:?array}> $cache
     */
    private function rememberValidation(array &$cache, string $key, callable $callback): mixed
    {
        if (array_key_exists($key, $cache)) {
            if ($cache[$key]['errors'] !== null) {
                throw ValidationException::withMessages($cache[$key]['errors']);
            }

            return $cache[$key]['value'];
        }

        try {
            $value = $callback();
            $cache[$key] = ['value' => $value, 'errors' => null];

            return $value;
        } catch (ValidationException $exception) {
            $cache[$key] = ['value' => null, 'errors' => $exception->errors()];

            throw $exception;
        }
    }

    /** @param array<string,mixed> $public */
    private function audienceCacheKey(array $public): string
    {
        foreach ([
            'usuarios_ids',
            'roles_ids',
            'permissoes_ids',
            'funcoes_administrativas_ids',
            'escolas_ids',
            'setores_ids',
        ] as $field) {
            sort($public[$field], SORT_NUMERIC);
        }

        return hash('sha256', json_encode($public, JSON_THROW_ON_ERROR));
    }

    /** @param list<array{numero_linha:int,dados:array<string,mixed>}> $rows @return array<string,mixed> */
    private function referenceMapsNova(array $rows): array
    {
        $values = fn (string $field): array => collect($rows)
            ->flatMap(fn (array $row): array => $this->listValues($row['dados'][$field] ?? null))
            ->unique()
            ->values()
            ->all();
        $schoolCodes = $values('escola_codigo');
        $serieCodes = $values('series_codigos');
        $turmaCodes = $values('turmas_codigos');
        $sources = collect($rows)->pluck('dados.fonte_externa')
            ->map(fn ($value): string => mb_strtolower(trim((string) $value)))
            ->filter()->unique()->all();
        $externalIds = collect($rows)->pluck('dados.identificador_externo')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()->unique()->all();

        return [
            'usuarios' => User::query()->whereIn('email', $values('usuarios_emails'))->get()
                ->keyBy(fn (User $item): string => mb_strtolower($item->email)),
            'roles' => Role::query()->where('guard_name', 'web')->whereIn('name', $values('roles'))->get()->keyBy('name'),
            'permissoes' => Permission::query()->where('guard_name', 'web')->whereIn('name', $values('permissoes'))->get()->keyBy('name'),
            'funcoes' => FuncaoAdministrativa::query()->where('ativo', true)
                ->whereIn('codigo', $values('funcoes_administrativas'))->get()->keyBy('codigo'),
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
            'normal',
            now()->addWeek()->format('d/m/Y'),
            'manha',
            '08:00',
            '10:30',
            '/admin',
            'Acessar',
            'Sim',
            'azul',
            'Sim',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'Sim',
            'qualquer',
            null,
            null,
            null,
            null,
        ];
    }
}
