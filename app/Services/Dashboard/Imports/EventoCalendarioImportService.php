<?php

namespace App\Services\Dashboard\Imports;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ImportacaoEventoCalendarioAcao;
use App\Models\Enums\ImportacaoEventoCalendarioStatus;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\FuncaoAdministrativa;
use App\Models\ImportacaoEventoCalendario;
use App\Models\ImportacaoEventoCalendarioLinha;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setor;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\PublicoAlvoService;
use App\Services\Dashboard\PublicoAlvoOptionsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
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
        'assunto',
        'prioridade',
        'data_inicio',
        'data_fim',
        'link_acao',
        'texto_botao',
        'status',
        'ativo',
        'progresso',
        'cor',
        'escola_codigo',
        'setor_id',
        'todos_usuarios',
        'modo_correspondencia',
        'usuarios_emails',
        'roles',
        'permissoes',
        'funcoes_administrativas',
        'escolas_publico_codigos',
        'setores_publico_ids',
    ];

    public function __construct(
        private readonly EventoCalendarioService $eventos,
        private readonly PublicoAlvoService $publicos,
    ) {}

    public function preview(UploadedFile $arquivo, User $ator): ImportacaoEventoCalendario
    {
        Gate::forUser($ator)->authorize('create', ImportacaoEventoCalendario::class);
        Gate::forUser($ator)->authorize('manageAudience', EventoCalendario::class);
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
        Gate::forUser($ator)->authorize('manageAudience', EventoCalendario::class);

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

                foreach ($revalidated as $row) {
                    $payload = $row['normalizados'];
                    $eventData = $payload['evento'];
                    $publicData = $payload['publico_alvo'];
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
            ['data_inicio / data_fim', 'Use DD/MM/AAAA HH:MM. A data final é obrigatória.'],
            ['listas', 'Separe vários valores com |.'],
            ['todos_usuarios', 'Use Sim ou Não. Se Sim, deixe os demais critérios de público vazios.'],
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

        $referenceRows = $schools->get()
            ->map(fn (Escola $school): array => ['Escola (código)', (string) $school->codigo, $school->nome])
            ->merge(collect($options->setores($ator))->map(fn (string $name, int|string $id): array => ['Setor (ID)', (string) $id, $name]))
            ->merge(collect($options->roles())->map(fn (string $name): array => ['Nível de acesso', $name, $name]))
            ->merge(collect($options->permissoes())->map(fn (string $name): array => ['Permissão', $name, $name]))
            ->merge(FuncaoAdministrativa::query()->where('ativo', true)->orderBy('nome')->get(['codigo', 'nome'])->map(
                fn (FuncaoAdministrativa $funcao): array => ['Função administrativa (código)', (string) $funcao->codigo, $funcao->nome],
            ))
            ->merge(collect(EventoCalendarioCategoria::cases())->map(fn ($item): array => ['Categoria', $item->value, $item->label()]))
            ->merge(collect(DashboardPrioridade::cases())->map(fn ($item): array => ['Prioridade', $item->value, $item->label()]))
            ->merge(collect(EventoCalendarioStatus::cases())->map(fn ($item): array => ['Status', $item->value, $item->label()]))
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
        $references = $this->referenceMaps($rows);
        $seen = [];
        $result = [];
        $contextValidationCache = [];
        $audienceValidationCache = [];

        foreach ($rows as $row) {
            $raw = $row['dados'];

            try {
                $normalized = $this->normalizeRow(
                    $raw,
                    $references,
                    $ator,
                    $contextValidationCache,
                    $audienceValidationCache,
                );
                $key = $normalized['evento']['fonte_externa'].'|'.$normalized['evento']['identificador_externo'];

                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        'identificador_externo' => 'Identificador duplicado na planilha (também usado na linha '.$seen[$key].').',
                    ]);
                }

                $seen[$key] = $row['numero_linha'];
                $existing = $references['eventos'][$key] ?? null;

                if ($existing?->trashed()) {
                    throw ValidationException::withMessages([
                        'identificador_externo' => 'A chave externa pertence a um evento excluído. Restaure-o antes de importar.',
                    ]);
                }

                if ($existing) {
                    Gate::forUser($ator)->authorize('update', $existing);
                    $action = ImportacaoEventoCalendarioAcao::ATUALIZAR;
                } else {
                    Gate::forUser($ator)->authorize('create', EventoCalendario::class);
                    $action = ImportacaoEventoCalendarioAcao::CRIAR;
                }

                $result[] = [
                    'numero_linha' => $row['numero_linha'],
                    'originais' => $raw,
                    'normalizados' => $normalized,
                    'erros' => [],
                    'acao' => $action,
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

        return $result;
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $maps
     * @param array<string,array{value:mixed,errors:?array}> $contextValidationCache
     * @param array<string,array{value:mixed,errors:?array}> $audienceValidationCache
     * @return array<string,mixed>
     */
    private function normalizeRow(
        array $raw,
        array $maps,
        User $ator,
        array &$contextValidationCache,
        array &$audienceValidationCache,
    ): array
    {
        $source = mb_strtolower(trim((string) ($raw['fonte_externa'] ?? '')));
        $externalId = trim((string) ($raw['identificador_externo'] ?? ''));
        $start = $this->date($raw['data_inicio'] ?? null, 'data_inicio');
        $end = $this->date($raw['data_fim'] ?? null, 'data_fim');
        $schoolCode = trim((string) ($raw['escola_codigo'] ?? ''));
        $school = $schoolCode !== '' ? ($maps['escolas'][$schoolCode] ?? null) : null;
        $sectorId = $this->nullablePositiveInt($raw['setor_id'] ?? null, 'setor_id');
        $sector = $sectorId ? ($maps['setores'][$sectorId] ?? null) : null;
        $progress = $raw['progresso'] ?? null;

        if (filled($progress) && ! is_numeric($progress)) {
            throw ValidationException::withMessages([
                'progresso' => 'Informe um número entre 0 e 100.',
            ]);
        }

        $event = [
            'fonte_externa' => $source,
            'identificador_externo' => $externalId,
            'titulo' => trim((string) ($raw['titulo'] ?? '')),
            'descricao' => $this->nullableString($raw['descricao'] ?? null),
            'categoria' => mb_strtolower(trim((string) ($raw['categoria'] ?? ''))),
            'assunto' => $this->nullableString($raw['assunto'] ?? null),
            'prioridade' => mb_strtolower(trim((string) ($raw['prioridade'] ?? ''))),
            'data_inicio' => $start,
            'data_fim' => $end,
            'link_acao' => $this->nullableString($raw['link_acao'] ?? null),
            'texto_botao' => $this->nullableString($raw['texto_botao'] ?? null),
            'status' => mb_strtolower(trim((string) ($raw['status'] ?? ''))),
            'ativo' => $this->boolean($raw['ativo'] ?? null, 'ativo'),
            'progresso' => filled($progress) ? (float) $progress : null,
            'cor' => mb_strtolower(trim((string) ($raw['cor'] ?? ''))),
            'escola_id' => $school?->getKey(),
            'setor_id' => $sector?->getKey(),
            'origem' => EventoCalendarioOrigem::PLANILHA->value,
        ];

        $validator = validator($event, [
            'fonte_externa' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'identificador_externo' => ['required', 'string', 'max:160'],
            'titulo' => ['required', 'string', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'categoria' => ['required', Rule::enum(EventoCalendarioCategoria::class)],
            'assunto' => ['nullable', 'string', 'max:100'],
            'prioridade' => ['required', Rule::enum(DashboardPrioridade::class)],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'link_acao' => ['nullable', 'string', 'max:2048'],
            'texto_botao' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::enum(EventoCalendarioStatus::class)],
            'ativo' => ['required', 'boolean'],
            'progresso' => ['nullable', 'numeric', 'between:0,100'],
            'cor' => ['required', Rule::enum(EventoCalendarioCor::class)],
        ], [
            'required' => 'Campo obrigatório.',
            'max' => 'Valor acima do limite permitido.',
            'data_fim.after_or_equal' => 'A data final deve ser posterior ou igual à inicial.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if ($schoolCode !== '' && ! $school) {
            throw ValidationException::withMessages(['escola_codigo' => 'Código de escola inexistente, inativo ou ambíguo.']);
        }

        if ($sectorId && ! $sector) {
            throw ValidationException::withMessages(['setor_id' => 'Setor inexistente ou inativo.']);
        }

        if ($event['link_acao'] && ! $this->safeLink($event['link_acao'])) {
            throw ValidationException::withMessages(['link_acao' => 'Informe uma URL HTTP(S) ou caminho interno iniciado por /.']);
        }

        if ($event['ativo'] && ! Gate::forUser($ator)->allows('publish', EventoCalendario::class)) {
            throw ValidationException::withMessages([
                'ativo' => 'Você não possui permissão para importar eventos publicados.',
            ]);
        }

        $contextKey = ($event['escola_id'] ?? 0).'|'.($event['setor_id'] ?? 0);
        $this->rememberValidation(
            $contextValidationCache,
            $contextKey,
            function () use ($event, $ator): bool {
                $this->eventos->validarContextoRelacionado($event, $ator);

                return true;
            },
        );

        $public = [
            'todos_usuarios' => $this->boolean($raw['todos_usuarios'] ?? null, 'todos_usuarios'),
            'modo_correspondencia' => mb_strtolower(trim((string) ($raw['modo_correspondencia'] ?: 'qualquer'))),
            'usuarios_ids' => $this->resolveList($raw['usuarios_emails'] ?? null, $maps['usuarios'], 'usuarios_emails'),
            'roles_ids' => $this->resolveList($raw['roles'] ?? null, $maps['roles'], 'roles'),
            'permissoes_ids' => $this->resolveList($raw['permissoes'] ?? null, $maps['permissoes'], 'permissoes'),
            'funcoes_administrativas_ids' => $this->resolveList($raw['funcoes_administrativas'] ?? null, $maps['funcoes'], 'funcoes_administrativas'),
            'escolas_ids' => $this->resolveList($raw['escolas_publico_codigos'] ?? null, $maps['escolas'], 'escolas_publico_codigos'),
            'setores_ids' => $this->resolveList($raw['setores_publico_ids'] ?? null, $maps['setores'], 'setores_publico_ids', numeric: true),
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
    private function referenceMaps(array $rows): array
    {
        $values = fn (string $field): array => collect($rows)
            ->flatMap(fn (array $row): array => $this->listValues($row['dados'][$field] ?? null))
            ->unique()->values()->all();
        $schoolCodes = collect([...$values('escola_codigo'), ...$values('escolas_publico_codigos')])->filter()->unique()->all();
        $sectorIds = collect([...$values('setor_id'), ...$values('setores_publico_ids')])->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->all();
        $sources = collect($rows)->pluck('dados.fonte_externa')->map(fn ($value): string => mb_strtolower(trim((string) $value)))->filter()->unique()->all();
        $externalIds = collect($rows)->pluck('dados.identificador_externo')->map(fn ($value): string => trim((string) $value))->filter()->unique()->all();

        return [
            'usuarios' => User::query()->whereIn('email', $values('usuarios_emails'))->get()->keyBy(fn (User $item): string => mb_strtolower($item->email)),
            'roles' => Role::query()->where('guard_name', 'web')->whereIn('name', $values('roles'))->get()->keyBy('name'),
            'permissoes' => Permission::query()->where('guard_name', 'web')->whereIn('name', $values('permissoes'))->get()->keyBy('name'),
            'funcoes' => FuncaoAdministrativa::query()->where('ativo', true)->whereIn('codigo', $values('funcoes_administrativas'))->get()->keyBy('codigo'),
            'escolas' => Escola::query()->where('ativo', true)->whereIn('codigo', $schoolCodes)->get()->groupBy('codigo')->map(fn ($items) => $items->count() === 1 ? $items->first() : null)->filter(),
            'setores' => Setor::query()->where('ativo', true)->whereKey($sectorIds)->get()->keyBy('id'),
            'eventos' => EventoCalendario::query()->withTrashed()->whereIn('fonte_externa', $sources)->whereIn('identificador_externo', $externalIds)->get()->keyBy(fn (EventoCalendario $event): string => $event->fonte_externa.'|'.$event->identificador_externo),
        ];
    }

    /** @return list<int> */
    private function resolveList(mixed $raw, $map, string $field, bool $numeric = false): array
    {
        $resolved = [];

        foreach ($this->listValues($raw) as $value) {
            $key = $numeric
                ? $this->nullablePositiveInt($value, $field)
                : $value;
            $model = $map->get($key) ?? $map->get(mb_strtolower((string) $key));

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

    private function date(mixed $value, string $field): CarbonImmutable
    {
        try {
            if (is_numeric($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }

            $value = trim((string) $value);

            foreach (['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
                try {
                    $parsed = CarbonImmutable::createFromFormat('!'.$format, $value, config('dashboard.calendar.timezone'));
                    $errors = \DateTimeImmutable::getLastErrors();

                    if ($parsed && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                        return $parsed;
                    }
                } catch (Throwable) {
                    // Tenta o próximo formato permitido.
                }
            }
        } catch (Throwable) {
            // Converte para uma mensagem de validação por linha.
        }

        throw ValidationException::withMessages([$field => 'Use uma data válida no formato DD/MM/AAAA HH:MM.']);
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

    private function nullablePositiveInt(mixed $value, string $field): ?int
    {
        if (blank($value)) {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0 || (float) $value !== (float) (int) $value) {
            throw ValidationException::withMessages([
                $field => 'Informe um identificador numérico positivo.',
            ]);
        }

        return (int) $value;
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
            'secretaria-educacao', 'reuniao-2026-001', 'Reunião de gestores',
            'Alinhamento mensal da equipe.', 'administrativo', 'Gestão escolar', 'normal',
            now()->addWeek()->format('d/m/Y 09:00'), now()->addWeek()->format('d/m/Y 10:30'),
            '/admin', 'Acessar', 'agendado', 'Sim', null, 'azul', null, null,
            'Sim', 'qualquer', null, null, null, null, null, null,
        ];
    }
}
