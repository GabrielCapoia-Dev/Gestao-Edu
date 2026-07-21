<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\ImportacaoEventoCalendarioAcao;
use App\Models\Enums\ImportacaoEventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\FuncaoAdministrativa;
use App\Models\ImportacaoEventoCalendario;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\Imports\EventoCalendarioImportService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventoCalendarioImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        putenv('PERMISSION_CACHE_STORE=array');
        $_ENV['PERMISSION_CACHE_STORE'] = 'array';
        $_SERVER['PERMISSION_CACHE_STORE'] = 'array';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set([
            'dashboard.imports.disk' => 'local',
            'dashboard.imports.directory' => 'imports/eventos-calendario-testes',
            'dashboard.imports.max_file_size_kb' => 5120,
            'dashboard.imports.max_rows' => 1000,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_preview_rejeita_planilha_sem_eventos_sem_persistir_lote(): void
    {
        $ator = $this->criarAtorGlobal();

        $exception = $this->capturarValidacao(fn () => $this->service()->preview(
            $this->arquivoCsv([]),
            $ator,
        ));

        $this->assertStringContainsString(
            'não possui linhas de eventos',
            $exception->errors()['arquivo'][0],
        );
        $this->assertDatabaseCount('importacoes_eventos_calendario', 0);
        $this->assertDatabaseCount('eventos_calendario', 0);
    }

    public function test_preview_classifica_linhas_validas_e_invalidas_sem_criar_eventos(): void
    {
        $ator = $this->criarAtorGlobal();
        $valida = $this->linhaValida('preview-valida');
        $invalida = [
            ...$this->linhaValida('preview-invalida'),
            'categoria' => 'categoria-inexistente',
        ];

        $importacao = $this->service()->preview(
            $this->arquivoCsv([$valida, $invalida]),
            $ator,
        );

        $this->assertSame(ImportacaoEventoCalendarioStatus::EM_PRE_VISUALIZACAO, $importacao->status);
        $this->assertSame(2, $importacao->total_linhas);
        $this->assertSame(1, $importacao->total_validas);
        $this->assertSame(1, $importacao->total_invalidas);
        $this->assertSame(ImportacaoEventoCalendarioAcao::CRIAR, $importacao->linhas[0]->acao);
        $this->assertSame(ImportacaoEventoCalendarioAcao::INVALIDA, $importacao->linhas[1]->acao);
        $this->assertArrayHasKey('categoria', $importacao->linhas[1]->erros);
        $this->assertDatabaseCount('eventos_calendario', 0);
        Storage::disk($importacao->disk)->assertMissing($importacao->caminho_arquivo);
    }

    public function test_preview_rejeita_duplicidade_de_chave_dentro_da_planilha(): void
    {
        $ator = $this->criarAtorGlobal();
        $primeira = $this->linhaValida('duplicado-no-arquivo');
        $segunda = [
            ...$primeira,
            'titulo' => 'Mesmo identificador em outra linha',
        ];

        $importacao = $this->service()->preview(
            $this->arquivoCsv([$primeira, $segunda]),
            $ator,
        );

        $this->assertSame(0, $importacao->total_validas);
        $this->assertSame(2, $importacao->total_invalidas);
        $this->assertSame(ImportacaoEventoCalendarioAcao::INVALIDA, $importacao->linhas[0]->acao);
        $this->assertSame(ImportacaoEventoCalendarioAcao::INVALIDA, $importacao->linhas[1]->acao);
        $this->assertStringContainsString(
            'mesma chave externa',
            $importacao->linhas[1]->erros['evento'][0],
        );
    }

    public function test_preview_bloqueia_formulas_e_excesso_de_linhas(): void
    {
        $ator = $this->criarAtorGlobal();
        $comFormula = [
            ...$this->linhaValida('formula'),
            'titulo' => '=HYPERLINK("https://example.test")',
        ];

        $formulaException = $this->capturarValidacao(fn () => $this->service()->preview(
            $this->arquivoCsv([$comFormula]),
            $ator,
        ));

        $this->assertStringContainsString('fórmula', $formulaException->errors()['arquivo'][0]);
        $this->assertDatabaseCount('importacoes_eventos_calendario', 0);

        config()->set('dashboard.imports.max_rows', 1);
        $limitException = $this->capturarValidacao(fn () => $this->service()->preview(
            $this->arquivoCsv([
                $this->linhaValida('limite-1'),
                $this->linhaValida('limite-2'),
            ]),
            $ator,
        ));

        $this->assertStringContainsString('no máximo 1 linhas', $limitException->errors()['arquivo'][0]);
        $this->assertDatabaseCount('importacoes_eventos_calendario', 0);
    }

    public function test_preview_rejeita_horarios_e_escopo_de_transporte_invalidos(): void
    {
        $ator = $this->criarAtorGlobal();
        $horarioInvalido = [
            ...$this->linhaValida('horario-invalido'),
            'hora_inicio' => '14:00',
            'hora_fim' => '10:00',
        ];
        $transporteInvalido = [
            ...$this->linhaValida('transporte-invalido'),
            'enviar_todas_escolas' => 'Sim',
            'precisa_transporte' => 'Sim',
            'escopo_transporte' => 'toda_unidade',
        ];

        $importacao = $this->service()->preview(
            $this->arquivoCsv([$horarioInvalido, $transporteInvalido]),
            $ator,
        );

        $this->assertSame(0, $importacao->total_validas);
        $this->assertSame(2, $importacao->total_invalidas);
        $this->assertArrayHasKey('hora_fim', $importacao->linhas[0]->erros);
        $this->assertArrayHasKey('escola_codigo', $importacao->linhas[1]->erros);
        $this->assertDatabaseCount('eventos_calendario', 0);
    }

    public function test_confirmacao_reverte_integralmente_quando_uma_linha_falha_durante_persistencia(): void
    {
        $ator = $this->criarAtorGlobal();
        $importacao = $this->service()->preview(
            $this->arquivoCsv([
                $this->linhaValida('atomico-1'),
                $this->linhaValida('atomico-2'),
            ]),
            $ator,
        );

        EventoCalendario::creating(function (EventoCalendario $evento): void {
            if ($evento->identificador_externo === 'atomico-2') {
                throw new RuntimeException('Falha controlada na segunda linha.');
            }
        });

        try {
            $this->service()->confirm($importacao, $ator);
            $this->fail('A confirmação deveria propagar a falha controlada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha controlada na segunda linha.', $exception->getMessage());
        }

        $importacao->refresh()->load('linhas');
        $this->assertSame(ImportacaoEventoCalendarioStatus::FALHOU, $importacao->status);
        $this->assertSame(['erro' => 'A confirmação foi revertida integralmente.'], $importacao->relatorio);
        $this->assertDatabaseCount('eventos_calendario', 0);
        $this->assertDatabaseCount('publicos_alvo', 0);
        $this->assertTrue($importacao->linhas->every(
            fn ($linha): bool => $linha->evento_calendario_id === null,
        ));
    }

    public function test_chave_externa_cria_e_depois_atualiza_o_mesmo_evento_idempotentemente(): void
    {
        $ator = $this->criarAtorGlobal();
        $ator->revokePermissionTo(ListaPermissoes::GerenciarPublicoAlvoDeEventos->label());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $service = $this->service();
        $primeiroLote = $service->preview(
            $this->arquivoCsv([$this->linhaValida('idempotente')]),
            $ator,
        );
        $primeiroLote = $service->confirm($primeiroLote, $ator);
        $evento = EventoCalendario::query()->sole();

        $this->assertSame(ImportacaoEventoCalendarioStatus::CONCLUIDA, $primeiroLote->status);
        $this->assertSame(1, $primeiroLote->total_criadas);
        $this->assertSame(0, $primeiroLote->total_atualizadas);
        $this->assertSame(EventoCalendarioOrigem::PLANILHA, $evento->origem);

        $linhaAtualizada = [
            ...$this->linhaValida('idempotente'),
            'titulo' => 'Título atualizado pela segunda importação',
            'hora_fim' => '11:00',
        ];
        $segundoLote = $service->preview($this->arquivoCsv([$linhaAtualizada]), $ator);

        $this->assertSame(ImportacaoEventoCalendarioAcao::ATUALIZAR, $segundoLote->linhas->sole()->acao);

        $segundoLote = $service->confirm($segundoLote, $ator);
        $eventoAtualizado = EventoCalendario::query()->sole();

        $this->assertSame($evento->id, $eventoAtualizado->id);
        $this->assertSame('Título atualizado pela segunda importação', $eventoAtualizado->titulo);
        $this->assertNull($eventoAtualizado->progresso);
        $this->assertSame('11:00', $eventoAtualizado->data_fim->format('H:i'));
        $this->assertSame($segundoLote->id, $eventoAtualizado->ultima_importacao_id);
        $this->assertSame(0, $segundoLote->total_criadas);
        $this->assertSame(1, $segundoLote->total_atualizadas);
        $this->assertDatabaseCount('eventos_calendario', 1);
        $this->assertDatabaseCount('publicos_alvo', 1);
    }

    public function test_linhas_com_mesma_chave_agrupam_escolas_e_horarios_em_um_evento(): void
    {
        $ator = $this->criarAtorGlobal();
        $setor = $this->criarSetor('Setor das escolas agrupadas');
        $escolaA = $this->criarEscola('Escola agrupada A', $setor);
        $escolaB = $this->criarEscola('Escola agrupada B', $setor);
        $base = [
            ...$this->linhaValida('multiescola'),
            'enviar_todas_escolas' => 'Não',
        ];
        $importacao = $this->service()->preview($this->arquivoCsv([
            [
                ...$base,
                'escola_codigo' => $escolaA->codigo,
                'hora_inicio_escola' => '08:00',
                'hora_fim_escola' => '10:00',
            ],
            [
                ...$base,
                'escola_codigo' => $escolaB->codigo,
                'hora_inicio_escola' => '10:00',
                'hora_fim_escola' => '12:00',
            ],
        ]), $ator);

        $this->assertSame(2, $importacao->total_validas);
        $importacao = $this->service()->confirm($importacao, $ator);
        $evento = EventoCalendario::query()->with('escolasAgendadas')->sole();

        $this->assertSame(1, $importacao->total_criadas);
        $this->assertCount(2, $evento->escolasAgendadas);
        $this->assertSame(
            [$escolaA->id, $escolaB->id],
            $evento->escolasAgendadas->pluck('escola_id')->sort()->values()->all(),
        );
    }

    public function test_preview_marca_como_invalida_chave_pertencente_a_evento_excluido(): void
    {
        $ator = $this->criarAtorGlobal();
        $service = $this->service();
        $lote = $service->preview(
            $this->arquivoCsv([$this->linhaValida('evento-excluido')]),
            $ator,
        );
        $service->confirm($lote, $ator);
        EventoCalendario::query()->sole()->delete();

        $novoLote = $service->preview(
            $this->arquivoCsv([$this->linhaValida('evento-excluido')]),
            $ator,
        );
        $linha = $novoLote->linhas->sole();

        $this->assertSame(ImportacaoEventoCalendarioStatus::EM_PRE_VISUALIZACAO, $novoLote->status);
        $this->assertSame(ImportacaoEventoCalendarioAcao::INVALIDA, $linha->acao);
        $this->assertStringContainsString(
            'evento excluído',
            $linha->erros['identificador_externo'][0],
        );
        $this->assertSame(1, EventoCalendario::withTrashed()->count());
    }

    public function test_confirmacao_revalida_permissao_de_publicacao(): void
    {
        $ator = $this->criarAtorGlobal();
        $importacao = $this->service()->preview(
            $this->arquivoCsv([$this->linhaValida('reautorizar-publicacao')]),
            $ator,
        );
        $ator->revokePermissionTo(ListaPermissoes::PublicarEventos->label());
        $ator->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $exception = $this->capturarValidacao(
            fn () => $this->service()->confirm($importacao, $ator),
            'importacao',
        );

        $this->assertStringContainsString('deixou de ser válida', $exception->errors()['importacao'][0]);
        $this->assertSame(
            ImportacaoEventoCalendarioStatus::FALHOU,
            $importacao->fresh()->status,
        );
        $this->assertDatabaseCount('eventos_calendario', 0);
    }

    public function test_confirmacao_revalida_escopo_escolar_do_responsavel(): void
    {
        [$ator, $escola, , $vinculo] = $this->criarAtorEscolar('revalidacao');
        $linha = [
            ...$this->linhaValida('revalidar-escopo'),
            'enviar_todas_escolas' => 'Não',
            'escola_codigo' => $escola->codigo,
        ];
        $importacao = $this->service()->preview($this->arquivoCsv([$linha]), $ator);

        $vinculo->update(['status' => ServidorFuncaoAdministrativa::STATUS_INATIVO]);
        app(DashboardUserContextFactory::class)->forget($ator);

        $exception = $this->capturarValidacao(
            fn () => $this->service()->confirm($importacao, $ator),
            'importacao',
        );

        $this->assertStringContainsString('deixou de ser válida', $exception->errors()['importacao'][0]);
        $this->assertSame(
            ImportacaoEventoCalendarioStatus::FALHOU,
            $importacao->fresh()->status,
        );
        $this->assertDatabaseCount('eventos_calendario', 0);
    }

    public function test_cancelamento_atualiza_lote_e_remove_arquivo_privado(): void
    {
        $ator = $this->criarAtorGlobal();
        $service = $this->service();
        $importacao = $service->preview(
            $this->arquivoCsv([$this->linhaValida('cancelar')]),
            $ator,
        );
        Storage::disk($importacao->disk)->put($importacao->caminho_arquivo, 'arquivo temporário');
        Storage::disk($importacao->disk)->assertExists($importacao->caminho_arquivo);

        $service->cancel($importacao, $ator);

        $importacao->refresh();
        $this->assertSame(ImportacaoEventoCalendarioStatus::CANCELADA, $importacao->status);
        $this->assertNotNull($importacao->cancelada_em);
        Storage::disk($importacao->disk)->assertMissing($importacao->caminho_arquivo);
    }

    public function test_template_possui_tres_abas_e_referencias_limitadas_ao_escopo(): void
    {
        [$ator, $escolaPermitida, $setorPermitido] = $this->criarAtorEscolar(
            'template',
            [ListaPermissoes::ExportarModeloDeImportacaoDeEventos],
        );
        $setorBloqueado = $this->criarSetor('Setor bloqueado do template');
        $escolaBloqueada = $this->criarEscola('Escola bloqueada do template', $setorBloqueado);
        $response = $this->service()->template($ator);
        $path = $response->getFile()->getPathname();
        $spreadsheet = IOFactory::load($path);

        try {
            $this->assertSame(['Eventos', 'Instruções', 'Referências'], $spreadsheet->getSheetNames());
            $this->assertSame(
                EventoCalendarioImportService::HEADERS,
                $spreadsheet->getSheetByName('Eventos')->rangeToArray('A1:AB1')[0],
            );

            $referencias = collect($spreadsheet->getSheetByName('Referências')->toArray())
                ->skip(1)
                ->filter(fn (array $row): bool => filled($row[1] ?? null));
            $identificadores = $referencias
                ->pluck(1)
                ->map(fn ($value): string => (string) $value)
                ->all();
            $nomes = $referencias->pluck(2)->all();

            $this->assertContains($escolaPermitida->codigo, $identificadores);
            $this->assertContains($escolaPermitida->nome, $nomes);
            $this->assertNotContains($escolaBloqueada->codigo, $identificadores);
            $this->assertNotContains($escolaBloqueada->nome, $nomes);
        } finally {
            $spreadsheet->disconnectWorksheets();

            foreach ([$path, str_ends_with($path, '.xlsx') ? substr($path, 0, -5) : null] as $temporaryPath) {
                if ($temporaryPath && is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }
    }

    private function service(): EventoCalendarioImportService
    {
        return app(EventoCalendarioImportService::class);
    }

    private function criarAtorGlobal(): User
    {
        $ator = User::factory()->create();
        $ator->assignRole(Role::findOrCreate('Admin', 'web'));
        $this->concederPermissoes($ator, $this->permissoesDeImportacao());

        return $ator;
    }

    /**
     * @param list<ListaPermissoes> $permissoesExtras
     * @return array{0: User, 1: Escola, 2: Setor, 3: ServidorFuncaoAdministrativa}
     */
    private function criarAtorEscolar(string $sufixo, array $permissoesExtras = []): array
    {
        $setor = $this->criarSetor('Setor escolar '.$sufixo);
        $escola = $this->criarEscola('Escola '.$sufixo, $setor);
        $funcao = FuncaoAdministrativa::query()->create([
            'codigo' => 'funcao-'.substr(md5($sufixo), 0, 12),
            'nome' => 'Função '.$sufixo,
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ]);
        $ator = User::factory()->create(['name' => 'Responsável '.$sufixo]);
        $pessoa = Servidor::query()->create([
            'user_id' => $ator->id,
            'nome' => $ator->name,
            'email' => $ator->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'principal' => true,
            'data_inicio' => now()->toDateString(),
        ]);
        $this->concederPermissoes($ator, [
            ...$this->permissoesDeImportacao(),
            ...$permissoesExtras,
        ]);

        return [$ator, $escola, $setor, $vinculo];
    }

    /** @return list<ListaPermissoes> */
    private function permissoesDeImportacao(): array
    {
        return [
            ListaPermissoes::ImportarEventosPorPlanilha,
            ListaPermissoes::CriarEventos,
            ListaPermissoes::EditarEventos,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::GerenciarPublicoAlvoDeEventos,
        ];
    }

    /** @param list<ListaPermissoes> $permissoes */
    private function concederPermissoes(User $user, array $permissoes): void
    {
        $labels = collect($permissoes)
            ->map(fn (ListaPermissoes $permissao): string => $permissao->label())
            ->unique()
            ->values()
            ->all();

        foreach ($labels as $label) {
            Permission::findOrCreate($label, 'web');
        }

        $user->givePermissionTo($labels);
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'email' => str($nome)->slug('.').'@teste.local',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function linhaValida(string $identificador): array
    {
        return [
            'fonte_externa' => 'teste-automatizado',
            'identificador_externo' => $identificador,
            'titulo' => 'Evento '.$identificador,
            'descricao' => 'Descrição importada pelo teste.',
            'categoria' => 'administrativo',
            'data_evento' => '21/07/2026',
            'periodo' => 'manha',
            'hora_inicio' => '09:00',
            'hora_fim' => '10:00',
            'link_acao' => '/admin',
            'texto_botao' => 'Acessar',
            'ativo' => 'Sim',
            'cor' => 'azul',
            'enviar_todas_escolas' => 'Sim',
            'escola_codigo' => null,
            'hora_inicio_escola' => null,
            'hora_fim_escola' => null,
            'precisa_transporte' => null,
            'escopo_transporte' => null,
            'series_codigos' => null,
            'turmas_codigos' => null,
            'todos_usuarios' => 'Sim',
            'modo_correspondencia' => 'qualquer',
            'usuarios_emails' => null,
            'roles' => null,
            'permissoes' => null,
            'funcoes_administrativas' => null,
        ];
    }

    /** @param list<array<string, mixed>> $linhas */
    private function arquivoCsv(array $linhas): UploadedFile
    {
        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível criar o CSV temporário do teste.');
        }

        fputcsv($handle, EventoCalendarioImportService::HEADERS, ';', '"', '\\', "\n");

        foreach ($linhas as $linha) {
            fputcsv(
                $handle,
                array_map(
                    fn (string $header): mixed => $linha[$header] ?? null,
                    EventoCalendarioImportService::HEADERS,
                ),
                ';',
                '"',
                '\\',
                "\n",
            );
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler o CSV temporário do teste.');
        }

        return UploadedFile::fake()->createWithContent('eventos.csv', $contents);
    }

    private function capturarValidacao(
        callable $callback,
        string $field = 'arquivo',
    ): ValidationException {
        try {
            $callback();
            $this->fail('Era esperada uma falha de validação no campo '.$field.'.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return $exception;
        }
    }
}
