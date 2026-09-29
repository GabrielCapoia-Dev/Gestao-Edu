<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Admin\Pages\Schemas\EventoCalendarioForm;
use App\Livewire\Home\EventoCalendarioModal;
use App\Models\Aluno;
use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\Serie;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\EventoCalendarioEscolaService;
use App\Services\Dashboard\EventoCalendarioDetalhesService;
use App\Services\Dashboard\EventoCalendarioLocalizacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoCalendarioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_criador_comum_distribui_por_escola_e_persiste_estimativa_de_transporte(): void
    {
        [$ator, $escola] = $this->atorEscolar('A');
        $serie = Serie::query()->create(['codigo' => 'SER-A', 'nome' => '1º Ano']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-A',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $alunoRemovido = $this->aluno($turma, 'CGM-1', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turma, 'CGM-2', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turma, 'CGM-3', Aluno::TIPO_VINCULO_CONTRA_TURNO, Aluno::STATUS_MATRICULADO);
        $this->aluno($turma, 'CGM-4', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_TRANSFERIDO);

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'enviar_escolas_especificas' => true,
            'transporte_excecoes_aluno_ids' => [$alunoRemovido->id],
            'escolas_agendadas' => [[
                'escola_id' => $escola->id,
                'hora_inicio' => '08:00',
                'hora_fim' => '10:00',
                'precisa_transporte' => true,
                'escopo_transporte' => EventoCalendarioTransporteEscopo::TURMAS->value,
                'turmas_ids' => [$turma->id],
            ]],
        ], [], $ator);

        $agendamento = $evento->escolasAgendadas()->with('turmas')->sole();
        $this->assertFalse($evento->enviar_todas_escolas);
        $this->assertSame(1, $agendamento->quantidade_estimada_transporte);
        $this->assertSame([$turma->id], $agendamento->turmas->modelKeys());
        $this->assertFalse($evento->publicoAlvo->todos_usuarios);
        $this->assertSame([$escola->id], $evento->publicoAlvo->escolas()->pluck('escolas.id')->all());
        $this->assertFalse($evento->ativo);
        $this->assertSame(EventoCalendarioStatus::PENDENTE_APROVACAO, $evento->status);
        $this->assertSame('normal', $evento->prioridade->value);
        $this->assertNull($evento->assunto);
        $this->assertNull($evento->progresso);
        $this->assertNotNull($evento->alunos_snapshot_em);
        $this->assertDatabaseCount('evento_calendario_alunos_snapshot', 1);
        $this->assertDatabaseMissing('evento_calendario_alunos_snapshot', [
            'evento_calendario_id' => $evento->id,
            'aluno_id' => $alunoRemovido->id,
        ]);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::CRIADO->value,
            'status_anterior' => null,
            'status_novo' => EventoCalendarioStatus::PENDENTE_APROVACAO->value,
        ]);
    }

    public function test_detalhe_de_turmas_usa_quantidades_do_snapshot_historico(): void
    {
        [$ator, $escola] = $this->atorEscolar('SNAPSHOT-TURMAS');
        $serie = Serie::query()->create(['codigo' => 'SER-SNAPSHOT-TURMAS', 'nome' => '4º Ano']);
        $turmaA = Turma::query()->create([
            'codigo' => 'TUR-SNAPSHOT-A', 'nome' => 'A', 'turno' => 'manha',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        $turmaB = Turma::query()->create([
            'codigo' => 'TUR-SNAPSHOT-B', 'nome' => 'B', 'turno' => 'tarde',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        $this->aluno($turmaA, 'CGM-SNAPSHOT-1', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turmaA, 'CGM-SNAPSHOT-2', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turmaB, 'CGM-SNAPSHOT-3', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'enviar_escolas_especificas' => true,
            'escolas_agendadas' => [[
                'escola_id' => $escola->id,
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'precisa_transporte' => true,
                'escopo_transporte' => EventoCalendarioTransporteEscopo::TURMAS->value,
                'turmas_ids' => [$turmaA->id, $turmaB->id],
            ]],
        ], [], $ator);

        $linhas = app(EventoCalendarioDetalhesService::class)
            ->escolas($ator, $evento->id, 'rede', 1, 10);

        $this->assertSame(2, $linhas['total']);
        $this->assertSame([
            'A' => 2,
            'B' => 1,
        ], collect($linhas['items'])->mapWithKeys(
            fn (array $linha): array => [$linha['turma'] => $linha['quantidade_alunos']],
        )->all());
    }

    public function test_criador_restrito_rejeita_evento_comum_e_aceita_solicitacao_de_transporte(): void
    {
        [$ator, $escola] = $this->atorEscolar('TRANSPORTE-RESTRITO');
        $ator->revokePermissionTo(ListaPermissoes::CriarEventos->label());
        Permission::findOrCreate(ListaPermissoes::CriarEventosTransporte->label(), 'web');
        $ator->givePermissionTo(ListaPermissoes::CriarEventosTransporte->label());

        try {
            app(EventoCalendarioService::class)->criar([
                ...$this->dadosBase(),
                'enviar_todas_escolas' => true,
            ], [], $ator);
            $this->fail('Era esperada uma falha para evento sem transporte.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('escolas_agendadas', $exception->errors());
        }

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'enviar_escolas_especificas' => true,
            'escolas_agendadas' => [[
                'escola_id' => $escola->id,
                'precisa_transporte' => true,
                'escopo_transporte' => EventoCalendarioTransporteEscopo::TODA_UNIDADE->value,
            ]],
        ], [], $ator);

        $this->assertSame(EventoCalendarioStatus::PENDENTE_APROVACAO, $evento->status);
        $this->assertFalse($evento->ativo);
        $this->assertTrue($evento->possuiTransporte());
    }

    public function test_persiste_categoria_personalizada_e_endereco_do_mapa_separados_do_local(): void
    {
        [$ator] = $this->atorEscolar('ENDERECO');

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'categoria' => 'outro',
            'categoria_detalhe' => 'Visita técnica',
            'local' => 'Centro de Formação Municipal',
            'endereco_mapa' => 'Rua Araribá, 875, Umuarama, Paraná, Brasil',
            'latitude' => '-23.7658000',
            'longitude' => '-53.3250000',
            'enviar_todas_escolas' => true,
        ], [], $ator);

        $this->assertSame('outro', $evento->categoria->value);
        $this->assertSame('Visita técnica', $evento->categoria_detalhe);
        $this->assertSame('Centro de Formação Municipal', $evento->local);
        $this->assertSame('Rua Araribá, 875, Umuarama, Paraná, Brasil', $evento->endereco_mapa);
        $localSalvo = app(\App\Services\Dashboard\EventoCalendarioLocalizacaoService::class)
            ->buscar('Centro de Formação Municipal', true);
        $this->assertSame('Rua Araribá, 875, Umuarama, Paraná, Brasil', $localSalvo[0]['label']);
        $this->assertSame('Centro de Formação Municipal', $localSalvo[0]['referencia']);
    }

    public function test_reverte_coordenadas_em_endereco_legivel_com_a_api_do_nominatim(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Avenida Paraná, 1234, Centro, Umuarama, Paraná, Brasil',
                'address' => [
                    'road' => 'Avenida Paraná',
                    'house_number' => '1234',
                    'suburb' => 'Centro',
                    'city' => 'Umuarama',
                    'state' => 'Paraná',
                ],
            ]),
        ]);

        $endereco = app(EventoCalendarioLocalizacaoService::class)->reverter(-23.700123, -53.200456);

        $this->assertSame('Avenida Paraná, 1234 · Centro · Umuarama · Paraná', $endereco);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://nominatim.openstreetmap.org/reverse?lat=-23.700123&lon=-53.200456&format=jsonv2&addressdetails=1&zoom=18&layer=address&accept-language=pt-BR'
            && $request->hasHeader('User-Agent', 'Gestao-Edu/1.0 (+'.rtrim((string) config('app.url'), '/').')'));
    }

    public function test_exige_detalhe_quando_categoria_do_evento_for_outro(): void
    {
        [$ator] = $this->atorEscolar('CATEGORIA');

        try {
            app(EventoCalendarioService::class)->criar([
                ...$this->dadosBase(),
                'categoria' => 'outro',
                'enviar_todas_escolas' => true,
            ], [], $ator);
            $this->fail('Era esperada uma falha de validação para categoria sem detalhe.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categoria_detalhe', $exception->errors());
        }
    }

    public function test_usa_horario_geral_e_rejeita_relacoes_escolares_invalidas(): void
    {
        [$ator, $escolaA] = $this->atorEscolar('A');
        [, $escolaB] = $this->atorEscolar('B');
        $serie = Serie::query()->create(['codigo' => 'SER-B', 'nome' => '2º Ano']);
        $turmaB = Turma::query()->create([
            'codigo' => 'TUR-B',
            'nome' => 'B',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escolaB->id,
        ]);

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'enviar_todas_escolas' => false,
            'escolas_agendadas' => [[
                'escola_id' => $escolaA->id,
                'hora_inicio' => '11:00',
                'hora_fim' => '10:00',
                'precisa_transporte' => false,
            ]],
        ], [], $ator);
        $agendamento = $evento->escolasAgendadas()->sole();
        $this->assertSame($evento->data_inicio->format('H:i'), substr((string) $agendamento->hora_inicio, 0, 5));
        $this->assertSame($evento->data_fim->format('H:i'), substr((string) $agendamento->hora_fim, 0, 5));

        try {
            app(EventoCalendarioService::class)->criar([
                ...$this->dadosBase(),
                'enviar_todas_escolas' => false,
                'escolas_agendadas' => [[
                    'escola_id' => $escolaA->id,
                    'hora_inicio' => '08:00',
                    'hora_fim' => '10:00',
                    'precisa_transporte' => true,
                    'escopo_transporte' => EventoCalendarioTransporteEscopo::TURMAS->value,
                    'turmas_ids' => [$turmaB->id],
                ]],
            ], [], $ator);
            $this->fail('Era esperada uma falha para a turma de outra escola.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('escolas_agendadas.0.turmas_ids', $exception->errors());
        }

        try {
            app(EventoCalendarioService::class)->criar([
                ...$this->dadosBase(),
                'enviar_todas_escolas' => false,
                'escolas_agendadas' => [[
                    'escola_id' => $escolaA->id,
                    'hora_inicio' => '08:00',
                    'hora_fim' => '10:00',
                    'precisa_transporte' => true,
                    'escopo_transporte' => EventoCalendarioTransporteEscopo::SERIES->value,
                    'series_ids' => ['identificador-forjado'],
                ]],
            ], [], $ator);
            $this->fail('Era esperada uma falha para o identificador de série inválido.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('escolas_agendadas.0.series_ids', $exception->errors());
        }
    }

    public function test_periodo_manual_e_convertido_para_um_unico_dia(): void
    {
        $this->assertSame([
            'manha' => ['inicio' => '08:00', 'fim' => '12:00'],
            'tarde' => ['inicio' => '13:30', 'fim' => '17:30'],
            'noite' => ['inicio' => '19:00', 'fim' => '22:00'],
            'dia_todo' => ['inicio' => '08:00', 'fim' => '17:30'],
        ], EventoCalendarioForm::PERIODOS);

        [$ator] = $this->atorEscolar('C');
        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'data_evento' => '2026-07-25',
            'hora_inicio' => '08:00',
            'hora_fim' => '14:00',
            'enviar_todas_escolas' => true,
        ], [], $ator);

        $this->assertSame('2026-07-25 08:00', $evento->data_inicio->format('Y-m-d H:i'));
        $this->assertSame('2026-07-25 14:00', $evento->data_fim->format('Y-m-d H:i'));
        $this->assertFalse($evento->publicoAlvo->todos_usuarios);
        $this->assertSame([$ator->id_escola], $evento->publicoAlvo->escolas()->pluck('escolas.id')->all());
        $this->assertTrue($evento->ativo);
        $this->assertSame(EventoCalendarioStatus::PUBLICADO, $evento->status);
        $this->assertNull($evento->link_acao);
        $this->assertNull($evento->texto_botao);
        $this->assertDatabaseCount('evento_calendario_escolas', 0);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::CRIADO->value,
            'status_anterior' => null,
            'status_novo' => EventoCalendarioStatus::PUBLICADO->value,
        ]);
    }

    public function test_edicao_de_evento_publicado_com_transporte_preserva_a_distribuicao_ao_alterar_o_periodo(): void
    {
        [$ator, $escola] = $this->atorEscolar('EDICAO-DIA-TODO');
        $serie = Serie::query()->create(['codigo' => 'SER-EDICAO-DIA-TODO', 'nome' => '2º Ano']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-EDICAO-DIA-TODO',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $this->aluno($turma, 'CGM-EDICAO-DIA-TODO', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'enviar_escolas_especificas' => true,
            'escolas_agendadas' => [[
                'escola_id' => $escola->id,
                'precisa_transporte' => true,
                'escopo_transporte' => EventoCalendarioTransporteEscopo::TURMAS->value,
                'turmas_ids' => [$turma->id],
            ]],
        ], [], $ator);
        $evento->forceFill([
            'status' => EventoCalendarioStatus::PUBLICADO,
            'ativo' => true,
        ])->save();

        $atualizado = app(EventoCalendarioService::class)->atualizar($evento, [
            ...$this->dadosBase(),
            'data_evento' => '2026-07-25',
            'periodo' => 'dia_todo',
            'hora_inicio' => '08:00',
            'hora_fim' => '17:30',
            'enviar_escolas_especificas' => true,
            'escolas_agendadas' => app(EventoCalendarioEscolaService::class)->paraFormulario($evento),
        ], [], $ator);

        $this->assertSame('08:00', $atualizado->data_inicio->format('H:i'));
        $this->assertSame('17:30', $atualizado->data_fim->format('H:i'));
        $this->assertSame(EventoCalendarioStatus::PENDENTE_APROVACAO, $atualizado->status);
        $this->assertFalse($atualizado->ativo);
        $this->assertSame([$turma->id], $atualizado->escolasAgendadas()->with('turmas')->sole()->turmas->modelKeys());
    }

    public function test_filtros_de_serie_e_turno_geram_escolas_e_transporte_em_lote(): void
    {
        [$ator, $escola] = $this->atorEscolar('GRUPO');
        $serie = Serie::query()->create(['codigo' => 'SER-GRUPO', 'nome' => '1º Ano']);
        $manha = Turma::query()->create([
            'codigo' => 'TUR-GRUPO-M',
            'nome' => '1º Ano A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $tarde = Turma::query()->create([
            'codigo' => 'TUR-GRUPO-T',
            'nome' => '1º Ano B',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $this->aluno($manha, 'CGM-GRUPO-M', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($tarde, 'CGM-GRUPO-T', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);

        $linhas = app(EventoCalendarioEscolaService::class)->gerarPorFiltros([
            'selecionar_todas_escolas' => true,
            'serie_ids' => [$serie->id],
            'turnos' => ['manha'],
            'precisa_transporte' => true,
        ], $ator, '08:00', '12:00');

        $this->assertCount(1, $linhas);
        $this->assertSame($escola->id, $linhas[0]['escola_id']);
        $this->assertSame(EventoCalendarioTransporteEscopo::TURMAS->value, $linhas[0]['escopo_transporte']);
        $this->assertSame([$manha->id], $linhas[0]['turmas_ids']);
        $this->assertSame(1, $linhas[0]['quantidade_estimada_transporte']);
    }

    public function test_prefixo_limita_escolas_agendadas_e_respeita_serie_e_turno(): void
    {
        [$ator, $escolaCmei] = $this->atorEscolar('PREFIXO-CMEI');
        [, $escolaEscola] = $this->atorEscolar('PREFIXO-ESCOLA');
        $escolaCmei->update(['nome' => 'CMEI - Unidade Central']);
        $escolaEscola->update(['nome' => 'ESCOLA - Unidade Central']);

        Role::findOrCreate('Admin', 'web');
        $ator->assignRole('Admin');

        $serie = Serie::query()->create(['codigo' => 'SER-PREFIXO', 'nome' => '1º Ano']);
        $turmaCmei = Turma::query()->create([
            'codigo' => 'TUR-PREFIXO-CMEI',
            'nome' => '1º Ano Tarde CMEI',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escolaCmei->id,
        ]);
        $turmaEscola = Turma::query()->create([
            'codigo' => 'TUR-PREFIXO-ESCOLA',
            'nome' => '1º Ano Tarde Escola',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escolaEscola->id,
        ]);
        $this->aluno($turmaCmei, 'CGM-PREFIXO-CMEI', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turmaEscola, 'CGM-PREFIXO-ESCOLA', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);

        $linhas = app(EventoCalendarioEscolaService::class)->gerarPorFiltros([
            'selecionar_todas_escolas' => true,
            'prefixos' => ['CMEI'],
            'serie_ids' => [$serie->id],
            'turnos' => ['tarde'],
            'precisa_transporte' => true,
        ], $ator, '13:30', '17:30');

        $this->assertCount(1, $linhas);
        $this->assertSame($escolaCmei->id, $linhas[0]['escola_id']);
        $this->assertSame([$turmaCmei->id], $linhas[0]['turmas_ids']);
    }

    public function test_modal_personalizado_abre_com_os_campos_do_evento(): void
    {
        [$ator] = $this->atorEscolar('FORM');
        $formularioEdicao = file_get_contents(app_path('Filament/Admin/Pages/Schemas/EventoCalendarioForm.php'));
        $modalBlade = file_get_contents(resource_path('views/livewire/home/evento-calendario-modal.blade.php'));
        $multiSelectBlade = file_get_contents(resource_path('views/components/evento-multi-select.blade.php'));
        $modalCss = file_get_contents(public_path('css/evento-calendario-modal.css'));
        $mapScript = file_get_contents(public_path('js/evento-local-map.js'));

        $this->assertIsString($modalBlade);
        $this->assertIsString($formularioEdicao);
        $this->assertIsString($multiSelectBlade);
        $this->assertIsString($modalCss);
        $this->assertIsString($mapScript);
        $this->assertStringContainsString('class="evento-custom-modal"', $modalBlade);
        $this->assertStringNotContainsString('<x-filament-actions::modals', $modalBlade);
        $this->assertStringNotContainsString('@class(', $modalBlade);
        $this->assertStringNotContainsString('@disabled(', $modalBlade);
        $this->assertStringNotContainsString('MutationObserver', $modalBlade);
        $this->assertStringNotContainsString('setInterval', $modalBlade);
        $this->assertStringContainsString('x-data="{ aberto: false, selecionados:', $multiSelectBlade);
        $this->assertStringContainsString('id="publico-tipo-escola"', $modalBlade);
        $this->assertStringContainsString('model="data.publico_prefixos"', $modalBlade);
        $this->assertStringContainsString('x-for="valor in selecionados"', $multiSelectBlade);
        $this->assertStringNotContainsString('selecionados.slice(0, 2)', $multiSelectBlade);
        $this->assertStringNotContainsString('__multi-select-count', $multiSelectBlade);
        $this->assertStringContainsString('x-model="selecionados"', $multiSelectBlade);
        $this->assertStringContainsString('x-bind:open="aberto"', $multiSelectBlade);
        $this->assertStringContainsString('$wire.set(@js($model), selecionados, false)', $multiSelectBlade);
        $this->assertStringNotContainsString('wire:model.live=', $multiSelectBlade);
        $this->assertStringContainsString("'is-selected'", $multiSelectBlade);
        $this->assertStringContainsString("this.$root.closest('[wire\\\\:id]')", $mapScript);
        $this->assertStringContainsString('this.selectPoint(lat, lng, ! endereco, endereco || null);', $mapScript);
        $this->assertStringContainsString('searchTypedAddress()', $mapScript);
        $this->assertStringContainsString("this.componentRoot()?.addEventListener('input'", $mapScript);
        $this->assertStringContainsString("this.message = label ? '' : 'Localizando o endereço do ponto...';", $mapScript);
        $this->assertStringContainsString('searchSavedReference()', $mapScript);
        $this->assertStringContainsString('addressDisplayInput()', $mapScript);
        $this->assertStringContainsString('return this.addressDisplayInput() || this.fieldInput(\'endereco_mapa\');', $mapScript);
        $this->assertStringContainsString("attribute.name.startsWith('wire:model')", $mapScript);
        $this->assertStringContainsString('wire.set(model, value, false)', $mapScript);
        $this->assertStringContainsString('if (! response.ok) throw new Error(`HTTP ${response.status}`);', $mapScript);
        $this->assertStringContainsString("console.log('[evento-local-map] Endereço retornado para o ponto selecionado:'", $mapScript);
        $this->assertStringContainsString("console.log('[evento-local-map] Coordenadas clicadas no mapa:'", $mapScript);
        $this->assertStringContainsString("console.log('[evento-local-map] Resposta do Nominatim:'", $mapScript);
        $this->assertStringContainsString('bubblingMouseEvents: true', $mapScript);
        $this->assertStringNotContainsString('https://nominatim.openstreetmap.org/reverse', $mapScript);
        $this->assertStringContainsString('data-reverse-geocode-url="{{ route(\'eventos-calendario.localizacoes.reverter\') }}"', file_get_contents(resource_path('views/filament/admin/pages/fields/evento-local-map.blade.php')));
        $this->assertStringContainsString('data-evento-map-address', $modalBlade);
        $this->assertStringContainsString("new CustomEvent('evento-mapa-endereco'", $mapScript);
        $this->assertStringContainsString('Nome ou referência do local', $modalBlade);
        $this->assertStringContainsString('Endereço do mapa', $modalBlade);
        $this->assertStringContainsString('x-on:evento-mapa-endereco.window', $modalBlade);
        $this->assertStringContainsString('TextInput::make(\'endereco_mapa\')', $formularioEdicao);
        $this->assertStringContainsString('Endereço do mapa', $formularioEdicao);
        $this->assertStringNotContainsString('->readOnly()', $formularioEdicao);
        $this->assertStringContainsString('.evento-custom-modal__backdrop', $modalCss);
        $this->assertStringContainsString('.evento-custom-modal__footer', $modalCss);
        $this->assertStringNotContainsString(
            "\$set('transporte_turnos', self::turnosParaPeriodo",
            $formularioEdicao,
        );

        $modal = Livewire::actingAs($ator)
            ->test(EventoCalendarioModal::class)
            ->call('abrir')
            ->assertSee('Planeje um novo evento')
            ->assertSee('Título')
            ->assertSee('Próximo')
            ->assertDontSee('Criar evento')
            ->assertDontSee('x-filament-actions');

        $modal
            ->set('data.titulo', 'Evento de teste')
            ->set('data.data_evento', '2026-09-25')
            ->set('data.hora_inicio', '08:00')
            ->set('data.hora_fim', '10:00')
            ->call('avancar')
            ->assertSet('etapa', 2)
            ->assertSee('Próximo')
            ->assertDontSee('Criar evento')
            ->call('avancar')
            ->assertSet('etapa', 3)
            ->assertSee('Criar evento')
            ->assertDontSee('Próximo');

        $this->assertDatabaseCount('eventos_calendario', 0);
    }

    public function test_filtro_de_tipo_de_escola_limita_participantes_a_prefixos_disponiveis(): void
    {
        [$ator, $escola] = $this->atorEscolar('PREFIXO-CONVITE');
        $escola->update(['nome' => 'CMEI - Unidade de teste']);

        Livewire::actingAs($ator)
            ->test(EventoCalendarioModal::class)
            ->call('abrir')
            ->set('data.publico_prefixos', ['CMEI'])
            ->call('aplicarFiltrosParticipantes')
            ->assertHasNoErrors()
            ->assertSet('data.publico_regras.0.escola_ids', [$escola->id])
            ->set('data.publico_prefixos', ['ESCOLA'])
            ->call('aplicarFiltrosParticipantes')
            ->assertHasErrors(['data.publico_prefixos']);
    }

    public function test_rejeita_envio_paralelo_para_todos_os_usuarios(): void
    {
        [$ator] = $this->atorEscolar('PUBLICO');

        try {
            app(EventoCalendarioService::class)->criar([
                ...$this->dadosBase(),
                'enviar_todos_usuarios' => true,
            ], [], $ator);
            $this->fail('Era esperada uma falha de autorização do público.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('enviar_todos_usuarios', $exception->errors());
        }

    }

    /** @return array{0: User, 1: Escola} */
    private function atorEscolar(string $sufixo): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$sufixo,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-'.$sufixo,
            'nome' => 'Escola '.$sufixo,
            'email' => strtolower($sufixo).'@escola.test',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
        $ator = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
        ]);
        $permissoes = [
            ListaPermissoes::ListarEventosGeral->label(),
            ListaPermissoes::CriarEventos->label(),
            ListaPermissoes::EditarEventos->label(),
        ];

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }

        $ator->givePermissionTo($permissoes);

        return [$ator, $escola];
    }

    private function aluno(Turma $turma, string $cgm, string $tipo, string $status): Aluno
    {
        return Aluno::query()->create([
            'nome' => 'Aluno '.$cgm,
            'cgm' => $cgm,
            'data_nascimento' => '2018-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => $tipo,
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    private function dadosBase(): array
    {
        return [
            'titulo' => 'Evento escolar',
            'descricao' => 'Descrição do evento.',
            'categoria' => 'administrativo',
            'data_evento' => '2026-07-25',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'cor' => 'azul',
            'inserir_link' => false,
            'link_acao' => 'javascript:alert(1)',
            'texto_botao' => 'Link forjado',
        ];
    }
}
