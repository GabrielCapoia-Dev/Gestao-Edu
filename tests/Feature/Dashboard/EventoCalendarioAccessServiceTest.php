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
use App\Services\Dashboard\EventoCalendarioAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventoCalendarioAccessServiceTest extends TestCase
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
            'nome' => 'Setor dos eventos',
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);

        $this->escola = Escola::query()->create([
            'codigo' => 'ESC-ACESSO-EVENTOS',
            'nome' => 'Escola dos eventos',
            'email' => 'eventos-acesso@teste.local',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);

        $this->publicoAlvo = PublicoAlvo::query()->create([
            'modo_correspondencia' => 'qualquer',
            'todos_usuarios' => true,
            'escopo_global' => true,
        ]);
    }

    public function test_permissao_geral_lista_todos_os_eventos(): void
    {
        $usuario = User::factory()->create();
        $eventos = $this->criarCenario($usuario);
        $this->conceder($usuario, ListaPermissoes::ListarEventosGeral);

        $this->assertSame(
            $this->idsOrdenados($eventos),
            $this->idsVisiveis($usuario),
        );
    }

    public function test_permissao_de_transporte_lista_somente_eventos_com_transporte(): void
    {
        $usuario = User::factory()->create();
        $eventos = $this->criarCenario($usuario);
        $this->conceder($usuario, ListaPermissoes::ListarEventosTransporte);

        $this->assertSame(
            $this->idsOrdenados([$eventos['transporte_proprio'], $eventos['transporte_alheio']]),
            $this->idsVisiveis($usuario),
        );
    }

    public function test_permissao_de_meus_eventos_lista_somente_eventos_criados_pelo_usuario(): void
    {
        $usuario = User::factory()->create();
        $eventos = $this->criarCenario($usuario);
        $this->conceder($usuario, ListaPermissoes::ListarMeusEventos);

        $this->assertSame(
            $this->idsOrdenados([$eventos['comum_proprio'], $eventos['transporte_proprio']]),
            $this->idsVisiveis($usuario),
        );
    }

    public function test_permissoes_de_transporte_e_meus_eventos_formam_uma_uniao_sem_duplicar_registros(): void
    {
        $usuario = User::factory()->create();
        $eventos = $this->criarCenario($usuario);
        $this->conceder(
            $usuario,
            ListaPermissoes::ListarEventosTransporte,
            ListaPermissoes::ListarMeusEventos,
        );

        $this->assertSame(
            $this->idsOrdenados([
                $eventos['comum_proprio'],
                $eventos['transporte_proprio'],
                $eventos['transporte_alheio'],
            ]),
            $this->idsVisiveis($usuario),
        );
    }

    public function test_permissao_legada_nao_e_convertida_implicitamente_em_acesso_geral(): void
    {
        $usuario = User::factory()->create();
        $this->criarCenario($usuario);
        $this->conceder($usuario, ListaPermissoes::ListarEventos);

        $this->assertSame([], $this->idsVisiveis($usuario));
    }

    /**
     * @return array{
     *     comum_proprio: EventoCalendario,
     *     transporte_proprio: EventoCalendario,
     *     comum_alheio: EventoCalendario,
     *     transporte_alheio: EventoCalendario
     * }
     */
    private function criarCenario(User $usuario): array
    {
        $outroUsuario = User::factory()->create();

        return [
            'comum_proprio' => $this->criarEvento($usuario, false, 'Comum próprio'),
            'transporte_proprio' => $this->criarEvento($usuario, true, 'Transporte próprio'),
            'comum_alheio' => $this->criarEvento($outroUsuario, false, 'Comum alheio'),
            'transporte_alheio' => $this->criarEvento($outroUsuario, true, 'Transporte alheio'),
        ];
    }

    private function criarEvento(User $criador, bool $comTransporte, string $titulo): EventoCalendario
    {
        $evento = EventoCalendario::query()->create([
            'publico_alvo_id' => $this->publicoAlvo->id,
            'enviar_todas_escolas' => ! $comTransporte,
            'titulo' => $titulo,
            'descricao' => 'Evento usado para validar o escopo da listagem.',
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

    /** @return list<int> */
    private function idsVisiveis(User $usuario): array
    {
        return app(EventoCalendarioAccessService::class)
            ->aplicarEscopo($usuario, EventoCalendario::query())
            ->orderBy('eventos_calendario.id')
            ->pluck('eventos_calendario.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<array-key, EventoCalendario>  $eventos
     * @return list<int>
     */
    private function idsOrdenados(array $eventos): array
    {
        $ids = array_map(
            fn (EventoCalendario $evento): int => (int) $evento->getKey(),
            array_values($eventos),
        );
        sort($ids);

        return $ids;
    }
}
