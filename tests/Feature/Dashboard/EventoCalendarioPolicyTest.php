<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventoCalendarioPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PublicoAlvo $publicoAlvo;

    private Escola $escola;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $setor = Setor::query()->create([
            'nome' => 'Setor da policy de eventos',
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);

        $this->escola = Escola::query()->create([
            'codigo' => 'ESC-POLICY-EVENTOS',
            'nome' => 'Escola da policy de eventos',
            'email' => 'eventos-policy@teste.local',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);

        $this->publicoAlvo = PublicoAlvo::query()->create([
            'modo_correspondencia' => 'qualquer',
            'todos_usuarios' => true,
            'escopo_global' => true,
        ]);
    }

    public function test_permissao_de_editar_nao_amplia_o_escopo_de_listagem(): void
    {
        $editor = User::factory()->create();
        $outroUsuario = User::factory()->create();
        $eventoProprio = $this->criarEvento($editor, false, 'Evento próprio');
        $eventoAlheio = $this->criarEvento($outroUsuario, false, 'Evento alheio');

        $this->conceder($editor, ListaPermissoes::EditarEventos);

        $this->assertFalse(Gate::forUser($editor)->allows('viewAny', EventoCalendario::class));
        $this->assertFalse(Gate::forUser($editor)->allows('update', $eventoProprio));

        $this->conceder($editor, ListaPermissoes::ListarMeusEventos);

        $this->assertTrue(Gate::forUser($editor)->allows('viewAny', EventoCalendario::class));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $eventoProprio));
        $this->assertFalse(Gate::forUser($editor)->allows('update', $eventoAlheio));
    }

    public function test_criador_restrito_pode_criar_somente_solicitacoes_de_transporte(): void
    {
        $assessoria = User::factory()->create();

        $this->conceder(
            $assessoria,
            ListaPermissoes::ListarMeusEventos,
            ListaPermissoes::CriarEventosTransporte,
        );

        $this->assertTrue(Gate::forUser($assessoria)->allows('create', EventoCalendario::class));
        $this->assertTrue(Gate::forUser($assessoria)->allows('createTransport', EventoCalendario::class));
        $this->assertTrue(Gate::forUser($assessoria)->allows('requiresTransport', EventoCalendario::class));
        $this->assertFalse(Gate::forUser($assessoria)->allows('createCommon', EventoCalendario::class));
        $this->assertFalse(Gate::forUser($assessoria)->allows('publish', EventoCalendario::class));
        $this->assertFalse(Gate::forUser($assessoria)->allows('manageTransport', EventoCalendario::class));
    }

    public function test_publicacao_e_desativacao_comuns_nao_autorizam_eventos_com_transporte(): void
    {
        $gestor = User::factory()->create();
        $criador = User::factory()->create();
        $eventoComum = $this->criarEvento($criador, false, 'Evento comum');
        $eventoTransporte = $this->criarEvento($criador, true, 'Evento com transporte');

        $this->conceder(
            $gestor,
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::DesativarEventos,
        );

        $this->assertTrue(Gate::forUser($gestor)->allows('publish', $eventoComum));
        $this->assertTrue(Gate::forUser($gestor)->allows('deactivate', $eventoComum));
        $this->assertFalse(Gate::forUser($gestor)->allows('publish', $eventoTransporte));
        $this->assertFalse(Gate::forUser($gestor)->allows('deactivate', $eventoTransporte));
    }

    public function test_publicacao_e_desativacao_de_transporte_nao_autorizam_eventos_comuns(): void
    {
        $gestor = User::factory()->create();
        $criador = User::factory()->create();
        $eventoComum = $this->criarEvento($criador, false, 'Evento comum');
        $eventoTransporte = $this->criarEvento($criador, true, 'Evento com transporte');

        $this->conceder(
            $gestor,
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::PublicarEventosTransporte,
            ListaPermissoes::DesativarEventosTransporte,
        );

        $this->assertTrue(Gate::forUser($gestor)->allows('publish', $eventoTransporte));
        $this->assertTrue(Gate::forUser($gestor)->allows('deactivate', $eventoTransporte));
        $this->assertFalse(Gate::forUser($gestor)->allows('publish', $eventoComum));
        $this->assertFalse(Gate::forUser($gestor)->allows('deactivate', $eventoComum));
    }

    private function criarEvento(User $criador, bool $comTransporte, string $titulo): EventoCalendario
    {
        $evento = EventoCalendario::query()->create([
            'publico_alvo_id' => $this->publicoAlvo->id,
            'enviar_todas_escolas' => ! $comTransporte,
            'titulo' => $titulo,
            'descricao' => 'Evento usado para validar a policy.',
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => '2026-07-23 08:00:00',
            'data_fim' => '2026-07-23 12:00:00',
            'status' => EventoCalendarioStatus::PUBLICADO,
            'ativo' => true,
            'cor' => EventoCalendarioCor::AZUL,
            'origem' => EventoCalendarioOrigem::MANUAL,
            'criado_por_id' => $criador->id,
            'atualizado_por_id' => $criador->id,
        ]);

        if ($comTransporte) {
            $evento->escolasAgendadas()->create([
                'escola_id' => $this->escola->id,
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'precisa_transporte' => true,
                'quantidade_estimada_transporte' => 30,
            ]);
        }

        return $evento;
    }

    private function conceder(User $usuario, ListaPermissoes ...$permissoes): void
    {
        $nomes = [];

        foreach ($permissoes as $permissao) {
            $nomes[] = $permissao->label();
            Permission::findOrCreate($permissao->label(), 'web');
        }

        $usuario->givePermissionTo($nomes);
    }
}
