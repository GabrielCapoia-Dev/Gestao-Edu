<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Admin\Pages\GerenciarEventos;
use App\Filament\Admin\Pages\Schemas\EventoCalendarioForm;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->aluno($turma, 'CGM-1', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turma, 'CGM-2', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_MATRICULADO);
        $this->aluno($turma, 'CGM-3', Aluno::TIPO_VINCULO_CONTRA_TURNO, Aluno::STATUS_MATRICULADO);
        $this->aluno($turma, 'CGM-4', Aluno::TIPO_VINCULO_PRINCIPAL, Aluno::STATUS_TRANSFERIDO);

        $evento = app(EventoCalendarioService::class)->criar([
            ...$this->dadosBase(),
            'enviar_escolas_especificas' => true,
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
        $this->assertSame(2, $agendamento->quantidade_estimada_transporte);
        $this->assertSame([$turma->id], $agendamento->turmas->modelKeys());
        $this->assertFalse($evento->publicoAlvo->todos_usuarios);
        $this->assertSame([$escola->id], $evento->publicoAlvo->escolas()->pluck('escolas.id')->all());
        $this->assertFalse($evento->ativo);
        $this->assertSame(EventoCalendarioStatus::PENDENTE_APROVACAO, $evento->status);
        $this->assertSame('normal', $evento->prioridade->value);
        $this->assertNull($evento->assunto);
        $this->assertNull($evento->progresso);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::CRIADO->value,
            'status_anterior' => null,
            'status_novo' => EventoCalendarioStatus::PENDENTE_APROVACAO->value,
        ]);
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

    public function test_formulario_oculta_link_e_nao_expoe_destinatarios(): void
    {
        [$ator] = $this->atorEscolar('FORM');

        Livewire::actingAs($ator)
            ->test(GerenciarEventos::class)
            ->mountAction('criarEvento')
            ->assertSee('Inserir link?')
            ->assertSee('Enviar para escolas específicas')
            ->assertDontSee('Link de ação')
            ->assertDontSee('Distribuição por escola')
            ->assertDontSee('Destinatários')
            ->assertDontSee('Prioridade')
            ->assertDontSee('Publicado')
            ->fillForm([
                'inserir_link' => true,
                'enviar_escolas_especificas' => true,
            ])
            ->assertSee('Link de ação')
            ->assertSee('Distribuição por escola');
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
