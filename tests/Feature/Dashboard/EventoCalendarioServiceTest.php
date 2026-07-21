<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Admin\Resources\EventosCalendario\Pages\CreateEventoCalendario;
use App\Filament\Admin\Resources\EventosCalendario\Schemas\EventoCalendarioForm;
use App\Models\Aluno;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\Serie;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
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
            'enviar_todas_escolas' => false,
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
        $this->assertNull($evento->assunto);
        $this->assertNull($evento->progresso);
    }

    public function test_rejeita_turma_de_outra_escola_e_horario_invertido(): void
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

        try {
            app(EventoCalendarioService::class)->criar([
                ...$this->dadosBase(),
                'enviar_todas_escolas' => false,
                'escolas_agendadas' => [[
                    'escola_id' => $escolaA->id,
                    'hora_inicio' => '11:00',
                    'hora_fim' => '10:00',
                    'precisa_transporte' => true,
                    'escopo_transporte' => EventoCalendarioTransporteEscopo::TURMAS->value,
                    'turmas_ids' => [$turmaB->id],
                ]],
            ], [], $ator);
            $this->fail('Era esperada uma falha de validação.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('escolas_agendadas.0.hora_fim', $exception->errors());
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
        $this->assertTrue($evento->publicoAlvo->todos_usuarios);
        $this->assertNull($evento->link_acao);
        $this->assertNull($evento->texto_botao);
        $this->assertDatabaseCount('evento_calendario_escolas', 0);
    }

    public function test_formulario_oculta_link_e_publico_avancado_sem_as_permissoes_correspondentes(): void
    {
        [$ator] = $this->atorEscolar('FORM');

        Livewire::actingAs($ator)
            ->test(CreateEventoCalendario::class)
            ->assertSee('Inserir link?')
            ->assertDontSee('Link de ação')
            ->assertDontSee('Público-alvo')
            ->fillForm(['inserir_link' => true])
            ->assertSee('Link de ação');

        $permissao = Permission::findOrCreate(
            ListaPermissoes::GerenciarPublicoAlvoDeEventos->label(),
            'web',
        );
        $ator->givePermissionTo($permissao);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($ator)
            ->test(CreateEventoCalendario::class)
            ->assertSee('Público-alvo')
            ->assertDontSee('Setores');
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
            ListaPermissoes::ListarEventos->label(),
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
            'prioridade' => 'normal',
            'data_evento' => '2026-07-25',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'cor' => 'azul',
            'ativo' => false,
            'inserir_link' => false,
            'link_acao' => 'javascript:alert(1)',
            'texto_botao' => 'Link forjado',
        ];
    }
}
