<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendManualNotificationBatchJob;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\NotificacaoEnvio;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Policies\SetorPolicy;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationCenterSchoolScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_opcoes_e_envio_da_equipe_gestora_ficam_restritos_a_escola_do_vinculo(): void
    {
        Queue::fake();

        [$escolaA, $setorA] = $this->criarEscola('Escola Notificação A');
        [$escolaB] = $this->criarEscola('Escola Notificação B');
        $turmaA = $this->criarTurma($escolaA, 'Turma A');
        $turmaB = $this->criarTurma($escolaB, 'Turma B');
        $gestor = $this->criarGestor($escolaA, $setorA, 'Gestor A');
        $usuarioA = User::factory()->create(['name' => 'Usuário A', 'id_escola' => $escolaA->id]);
        $usuarioB = User::factory()->create(['name' => 'Usuário B', 'id_escola' => $escolaB->id]);

        $this->darPermissoes($gestor, [
            NotificationCenterService::DESTINATION_PERMISSIONS['usuarios'],
            NotificationCenterService::DESTINATION_PERMISSIONS['escolas'],
            NotificationCenterService::DESTINATION_PERMISSIONS['professores_turmas'],
        ]);

        $service = app(NotificationCenterService::class);
        $options = $service->formOptions($gestor);

        $this->assertContains((string) $usuarioA->id, array_column($options['usuarios'], 'id'));
        $this->assertNotContains((string) $usuarioB->id, array_column($options['usuarios'], 'id'));
        $this->assertSame([(string) $escolaA->id], array_column($options['escolas'], 'id'));
        $this->assertSame([(string) $turmaA->id], array_column($options['turmas'], 'id'));

        $resultado = $service->send($gestor, [
            'titulo' => 'Aviso da escola',
            'mensagem' => 'Mensagem restrita',
            'prioridade' => 'normal',
            'destino_tipo' => 'usuarios',
            'usuarios_ids' => [$usuarioA->id],
        ]);

        $this->assertSame(1, $resultado['count']);
        $envio = NotificacaoEnvio::query()->findOrFail($resultado['envio_id']);
        $this->assertSame([(int) $usuarioA->id], array_map('intval', $envio->destinatarios_ids));
        Queue::assertPushed(SendManualNotificationBatchJob::class, 1);

        $this->assertIdsForaDoEscopoSaoRecusados($service, $gestor, [
            'destino_tipo' => 'usuarios',
            'usuarios_ids' => [$usuarioB->id],
        ], 'usuarios_ids');
        $this->assertIdsForaDoEscopoSaoRecusados($service, $gestor, [
            'destino_tipo' => 'escolas',
            'escolas_ids' => [$escolaB->id],
        ], 'escolas_ids');
        $this->assertIdsForaDoEscopoSaoRecusados($service, $gestor, [
            'destino_tipo' => 'professores_turmas',
            'turmas_ids' => [$turmaB->id],
        ], 'turmas_ids');

        $this->assertDatabaseCount('notificacao_envios', 1);
    }

    public function test_listagem_de_envios_respeita_a_escola_e_gestor_ambiguo_falha_fechado(): void
    {
        [$escolaA, $setorA] = $this->criarEscola('Escola Listagem A');
        [$escolaB, $setorB] = $this->criarEscola('Escola Listagem B');
        $gestorA = $this->criarGestor($escolaA, $setorA, 'Gestor Listagem A');
        $gestorB = $this->criarGestor($escolaB, $setorB, 'Gestor Listagem B');
        $ambiguo = $this->criarGestor($escolaA, $setorA, 'Gestor Ambíguo');
        $servidorAmbiguo = Servidor::query()->where('user_id', $ambiguo->id)->firstOrFail();
        $this->criarVinculoGestor($servidorAmbiguo, $escolaB, $setorB);

        foreach ([$gestorA, $gestorB, $ambiguo] as $user) {
            $this->darPermissoes($user, [
                'Visualizar Notificações',
                'Criar Notificações',
                NotificationCenterService::DESTINATION_PERMISSIONS['usuarios'],
            ]);
        }

        $this->criarEnvio($gestorA, 'Envio Escola A');
        $this->criarEnvio($gestorB, 'Envio Escola B');
        $service = app(NotificationCenterService::class);

        $payloadA = $service->payload($gestorA, ['modo' => 'enviadas', 'periodo' => 'todos']);
        $this->assertSame(['Envio Escola A'], $payloadA['items']->pluck('titulo')->all());
        $this->assertSame(1, $payloadA['stats']['enviadas']);

        $optionsAmbiguo = $service->formOptions($ambiguo);
        $this->assertSame([], $optionsAmbiguo['usuarios']);
        $this->assertSame([], $optionsAmbiguo['escolas']);
        $this->assertSame([], $optionsAmbiguo['turmas']);

        $payloadAmbiguo = $service->payload($ambiguo, ['modo' => 'enviadas', 'periodo' => 'todos']);
        $this->assertSame([], $payloadAmbiguo['items']->all());
        $this->assertSame(0, $payloadAmbiguo['stats']['enviadas']);

        try {
            $service->send($ambiguo, [
                'titulo' => 'Não deve enviar',
                'mensagem' => 'Sem escopo inequívoco',
                'prioridade' => 'normal',
                'destino_tipo' => 'usuarios',
                'usuarios_ids' => [$gestorA->id],
            ]);
            $this->fail('O gestor com vínculos escolares ambíguos deveria falhar fechado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destino_tipo', $exception->errors());
        }
    }

    public function test_admin_e_permissao_global_preservam_acesso_as_duas_escolas(): void
    {
        Queue::fake();

        [$escolaA] = $this->criarEscola('Escola Global A');
        [$escolaB] = $this->criarEscola('Escola Global B');
        $turmaA = $this->criarTurma($escolaA, 'Turma Global A');
        $turmaB = $this->criarTurma($escolaB, 'Turma Global B');
        $usuarioA = User::factory()->create(['name' => 'Destino Global A', 'id_escola' => $escolaA->id]);
        $usuarioB = User::factory()->create(['name' => 'Destino Global B', 'id_escola' => $escolaB->id]);
        $admin = User::factory()->create(['name' => 'Administrador']);
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $global = User::factory()->create(['name' => 'Usuário Global']);

        foreach ([$admin, $global] as $user) {
            $this->darPermissoes($user, [
                'Visualizar Notificações',
                'Criar Notificações',
                NotificationCenterService::DESTINATION_PERMISSIONS['usuarios'],
            ]);
        }
        $this->darPermissoes($global, [SetorPolicy::GLOBAL_SCOPE_PERMISSION]);

        $this->criarEnvio($usuarioA, 'Envio Global A');
        $this->criarEnvio($usuarioB, 'Envio Global B');
        $service = app(NotificationCenterService::class);

        foreach ([$admin, $global] as $user) {
            $options = $service->formOptions($user);
            $this->assertContains((string) $usuarioA->id, array_column($options['usuarios'], 'id'));
            $this->assertContains((string) $usuarioB->id, array_column($options['usuarios'], 'id'));
            $this->assertContains((string) $escolaA->id, array_column($options['escolas'], 'id'));
            $this->assertContains((string) $escolaB->id, array_column($options['escolas'], 'id'));
            $this->assertContains((string) $turmaA->id, array_column($options['turmas'], 'id'));
            $this->assertContains((string) $turmaB->id, array_column($options['turmas'], 'id'));

            $payload = $service->payload($user, ['modo' => 'enviadas', 'periodo' => 'todos']);
            $this->assertSame(2, $payload['stats']['enviadas']);
            $this->assertCount(2, $payload['items']);
        }

        $resultado = $service->send($global, [
            'titulo' => 'Aviso global',
            'mensagem' => 'Mensagem para outra escola',
            'prioridade' => 'normal',
            'destino_tipo' => 'usuarios',
            'usuarios_ids' => [$usuarioB->id],
        ]);

        $this->assertSame(1, $resultado['count']);
    }

    private function assertIdsForaDoEscopoSaoRecusados(
        NotificationCenterService $service,
        User $autor,
        array $destino,
        string $campo,
    ): void {
        try {
            $service->send($autor, [
                'titulo' => 'Tentativa fora do escopo',
                'mensagem' => 'Não deve ser enfileirada',
                'prioridade' => 'normal',
                ...$destino,
            ]);
            $this->fail('O ID fora do escopo deveria ser recusado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    /** @return array{0: Escola, 1: Setor} */
    private function criarEscola(string $nome): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
        $escola = Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'ativo' => true,
        ]);

        return [$escola, $setor];
    }

    private function criarTurma(Escola $escola, string $nome): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => strtoupper(substr(md5('serie-'.$nome), 0, 8)),
            'nome' => 'Série '.$nome,
        ]);

        return Turma::query()->create([
            'codigo' => strtoupper(substr(md5('turma-'.$nome), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarGestor(Escola $escola, Setor $setor, string $nome): User
    {
        $user = User::factory()->create(['name' => $nome]);
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $nome,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $this->criarVinculoGestor($servidor, $escola, $setor);

        return $user;
    }

    private function criarVinculoGestor(Servidor $servidor, Escola $escola, Setor $setor): void
    {
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::direcaoPadrao()->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'portaria' => 'PORT-'.strtoupper(substr(md5($servidor->id.'-'.$escola->id), 0, 6)),
            'principal' => true,
            'data_inicio' => now()->toDateString(),
        ]);
    }

    private function criarEnvio(User $autor, string $titulo): NotificacaoEnvio
    {
        return NotificacaoEnvio::query()->create([
            'user_id' => $autor->id,
            'titulo' => $titulo,
            'mensagem' => 'Mensagem do envio',
            'prioridade' => 'normal',
            'destino_tipo' => 'usuarios',
            'destino_label' => $autor->name,
            'destinatarios_count' => 1,
            'destinatarios_ids' => [$autor->id],
            'filtros' => ['destino_tipo' => 'usuarios'],
            'status' => NotificacaoEnvio::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /** @param array<int, string> $permissions */
    private function darPermissoes(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }
}
