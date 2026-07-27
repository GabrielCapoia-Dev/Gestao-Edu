<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Admin\Pages\GerenciarEventos;
use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioListQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GerenciarEventosPageTest extends TestCase
{
    use RefreshDatabase;

    private Escola $escola;

    private PublicoAlvo $publico;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->escola = Escola::query()->create([
            'codigo' => 'ESC-PAGE-EVENTOS',
            'nome' => 'Escola da Page de Eventos',
            'email' => 'eventos-page@teste.local',
            'ativo' => true,
        ]);

        $this->publico = PublicoAlvo::query()->create([
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::Qualquer,
            'todos_usuarios' => true,
            'escopo_global' => true,
        ]);
        $this->publico->escolas()->attach($this->escola);
    }

    public function test_page_exige_uma_das_permissoes_de_listagem(): void
    {
        $semAcesso = User::factory()->create(['email_approved' => true]);
        $this->actingAs($semAcesso);
        $this->assertFalse(GerenciarEventos::canAccess());

        $comAcesso = $this->usuarioComPermissoes(ListaPermissoes::ListarMeusEventos);
        $this->actingAs($comAcesso);
        $this->assertTrue(GerenciarEventos::canAccess());
    }

    public function test_visao_de_transporte_renderiza_indicadores_e_somente_eventos_do_escopo(): void
    {
        $usuario = $this->usuarioComPermissoes(ListaPermissoes::ListarEventosTransporte);
        $outro = User::factory()->create();
        $transporte = $this->evento($outro, 'Solicitação de transporte', EventoCalendarioStatus::PENDENTE_APROVACAO, true);
        $comum = $this->evento($outro, 'Evento geral sem transporte', EventoCalendarioStatus::PUBLICADO, false);

        Livewire::actingAs($usuario)
            ->test(GerenciarEventos::class)
            ->assertCanSeeTableRecords([$transporte])
            ->assertCanNotSeeTableRecords([$comum])
            ->assertSee('Solicitações em aberto')
            ->assertSee('Total com transporte');
    }

    public function test_visao_de_meus_eventos_nao_exibe_indicadores_exclusivos_do_transporte(): void
    {
        $usuario = $this->usuarioComPermissoes(ListaPermissoes::ListarMeusEventos);
        $proprio = $this->evento($usuario, 'Meu evento', EventoCalendarioStatus::PUBLICADO, false);
        $alheio = $this->evento(User::factory()->create(), 'Evento de outro usuário', EventoCalendarioStatus::PUBLICADO, false);

        Livewire::actingAs($usuario)
            ->test(GerenciarEventos::class)
            ->assertCanSeeTableRecords([$proprio])
            ->assertCanNotSeeTableRecords([$alheio])
            ->assertDontSee('Solicitações em aberto');
    }

    public function test_visao_sem_permissao_de_transporte_ordena_pela_data_do_evento(): void
    {
        $usuario = $this->usuarioComPermissoes(ListaPermissoes::ListarMeusEventos);
        $proximo = $this->evento($usuario, 'Evento mais próximo', EventoCalendarioStatus::PUBLICADO, false);
        $proximo->forceFill([
            'data_inicio' => '2026-07-25 08:00:00',
            'data_fim' => '2026-07-25 12:00:00',
        ])->save();
        $pendenteDistante = $this->evento(
            $usuario,
            'Transporte pendente distante',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            true,
        );
        $pendenteDistante->forceFill([
            'data_inicio' => '2026-08-10 08:00:00',
            'data_fim' => '2026-08-10 12:00:00',
        ])->save();

        Livewire::actingAs($usuario)
            ->test(GerenciarEventos::class)
            ->assertCanSeeTableRecords([$proximo, $pendenteDistante], inOrder: true);
    }

    public function test_publicacao_de_transporte_e_reautorizada_pela_action_da_page(): void
    {
        $usuario = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosTransporte,
            ListaPermissoes::PublicarEventosTransporte,
        );
        $evento = $this->evento(
            User::factory()->create(),
            'Evento pendente para aprovação',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            true,
        );

        Livewire::actingAs($usuario)
            ->test(GerenciarEventos::class)
            ->assertTableActionVisible('publicar', $evento)
            ->callTableAction('publicar', $evento)
            ->assertHasNoTableActionErrors();

        $evento->refresh();
        $this->assertSame(EventoCalendarioStatus::PUBLICADO, $evento->status);
        $this->assertTrue($evento->ativo);
    }

    public function test_evento_de_transporte_rejeitado_nao_expoe_acao_de_publicacao(): void
    {
        $usuario = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosTransporte,
            ListaPermissoes::PublicarEventosTransporte,
        );
        $evento = $this->evento(
            User::factory()->create(),
            'Evento rejeitado',
            EventoCalendarioStatus::REJEITADO,
            true,
        );

        Livewire::actingAs($usuario)
            ->test(GerenciarEventos::class)
            ->assertTableActionHidden('publicar', $evento);
    }

    public function test_detalhes_abrem_em_slideover_e_acoes_de_exclusao_e_duplicacao_nao_existem(): void
    {
        $usuario = $this->usuarioComPermissoes(ListaPermissoes::ListarEventosGeral);
        $evento = $this->evento(
            $usuario,
            'Evento detalhado',
            EventoCalendarioStatus::PUBLICADO,
            false,
            'Descrição completa e exclusiva do evento detalhado.',
        );

        Livewire::actingAs($usuario)
            ->test(GerenciarEventos::class)
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('duplicate')
            ->assertTableActionVisible('detalhes', $evento)
            ->callTableAction('detalhes', $evento)
            ->assertSee('Descrição completa e exclusiva do evento detalhado.');
    }

    public function test_detalhes_de_transporte_exibem_escola_e_turmas_sem_alocacao_de_veiculo(): void
    {
        $usuario = $this->usuarioComPermissoes(ListaPermissoes::ListarEventosTransporte);
        $evento = $this->evento(
            User::factory()->create(),
            'Evento para conferência das turmas',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            true,
        );
        $serie = Serie::query()->create([
            'codigo' => 'SER-PAGE-EVENTOS',
            'nome' => '5º Ano',
        ]);
        Turma::query()->create([
            'codigo' => 'TUR-PAGE-EVENTOS',
            'nome' => 'Turma Azul',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $this->escola->id,
        ]);

        $detalhes = app(EventoCalendarioListQueryService::class)
            ->detalhes($usuario, $evento->id);
        $html = view(
            'filament.admin.pages.partials.evento-calendario-detalhes',
            ['evento' => $detalhes],
        )->render();

        $this->assertStringContainsString('Escola da Page de Eventos', $html);
        $this->assertStringContainsString('5º Ano Turma Azul', $html);
        $this->assertStringNotContainsString('Confirmar atribuição', $html);
        $this->assertStringNotContainsString('Motorista:', $html);
        $this->assertStringNotContainsString('Veículo atribuído', $html);
    }

    private function usuarioComPermissoes(ListaPermissoes ...$permissoes): User
    {
        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $nomes = [];

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->label(), 'web');
            $nomes[] = $permissao->label();
        }

        $usuario->givePermissionTo($nomes);

        return $usuario;
    }

    private function evento(
        User $criador,
        string $titulo,
        EventoCalendarioStatus $status,
        bool $transporte,
        string $descricao = 'Descrição resumida do evento.',
    ): EventoCalendario {
        $evento = EventoCalendario::query()->create([
            'publico_alvo_id' => $this->publico->id,
            'enviar_todas_escolas' => ! $transporte,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => '2026-07-30 08:00:00',
            'data_fim' => '2026-07-30 12:00:00',
            'status' => $status,
            'ativo' => $status === EventoCalendarioStatus::PUBLICADO,
            'cor' => EventoCalendarioCor::AZUL,
            'origem' => EventoCalendarioOrigem::MANUAL,
            'criado_por_id' => $criador->id,
            'atualizado_por_id' => $criador->id,
        ]);

        if ($transporte) {
            $evento->escolasAgendadas()->create([
                'escola_id' => $this->escola->id,
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'precisa_transporte' => true,
                'quantidade_estimada_transporte' => 42,
            ]);
        }

        return $evento;
    }
}
