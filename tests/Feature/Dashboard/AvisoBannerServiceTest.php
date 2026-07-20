<?php

namespace Tests\Feature\Dashboard;

use App\Models\Aviso;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\Setor;
use App\Models\User;
use App\Policies\AvisoPolicy;
use App\Services\Dashboard\AvisoBannerService;
use App\Services\Dashboard\AvisoService;
use App\Services\Dashboard\PublicoAlvoService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AvisoBannerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_banner_filtra_vigencia_e_publico_no_backend_e_resolve_posicoes_sem_ocultar_avisos(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        [$usuarioA, $usuarioB] = $this->criarUsuariosDeEscolasDiferentes();
        $publico = app(PublicoAlvoService::class)->criar($usuarioA, [
            'todos_usuarios' => true,
        ]);

        $primeiro = $this->criarAviso($publico->id, 'Primeiro', 'alta', 1, $agora->subHour());
        $conflito = $this->criarAviso($publico->id, 'Conflito', 'alta', 1, $agora->subHours(2));
        $segundaPosicao = $this->criarAviso($publico->id, 'Segunda posição', 'alta', 2, $agora->subHours(3));

        $this->criarAviso($publico->id, 'Inativo', 'urgente', 1, $agora->subMinute(), false);
        $this->criarAviso($publico->id, 'Agendado', 'urgente', 1, $agora->addHour());
        $this->criarAviso($publico->id, 'Expirado', 'urgente', 1, $agora->subDays(2), true, $agora->subDay());

        $service = app(AvisoBannerService::class);

        $this->assertSame(
            [$primeiro->id, $conflito->id, $segundaPosicao->id],
            $service->avisosPara($usuarioA, $agora)->modelKeys(),
        );
        $this->assertSame(
            [$primeiro->id, $segundaPosicao->id, $conflito->id],
            $service->paginasPara($usuarioA, $agora)->first()->modelKeys(),
        );
        $this->assertTrue($service->avisosPara($usuarioB, $agora)->isEmpty());
    }

    public function test_policy_e_consulta_administrativa_exigem_permissao_e_envelope_gerenciavel(): void
    {
        [$usuarioA, $usuarioB] = $this->criarUsuariosDeEscolasDiferentes();
        $this->darPermissoesDeAviso($usuarioA);
        $this->darPermissoesDeAviso($usuarioB);
        $publico = app(PublicoAlvoService::class)->criar($usuarioA, [
            'todos_usuarios' => true,
        ]);
        $aviso = $this->criarAviso(
            $publico->id,
            'Restrito à escola A',
            'normal',
            null,
            now()->subHour(),
        );
        $policy = app(AvisoPolicy::class);

        $this->assertTrue($policy->view($usuarioA, $aviso));
        $this->assertTrue($policy->update($usuarioA, $aviso));
        $this->assertTrue($policy->publish($usuarioA, $aviso));
        $this->assertFalse($policy->view($usuarioB, $aviso));
        $this->assertFalse($policy->update($usuarioB, $aviso));
        $this->assertFalse($policy->publish($usuarioB, $aviso));
        $this->assertSame(
            [$aviso->id],
            $policy->applyViewAnyScope($usuarioA, Aviso::query())->pluck('avisos.id')->all(),
        );
        $this->assertTrue(
            $policy->applyViewAnyScope($usuarioB, Aviso::query())->doesntExist(),
        );
    }

    public function test_paginacao_preserva_layouts_de_um_dois_tres_e_mais_avisos(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        [$usuario] = $this->criarUsuariosDeEscolasDiferentes();
        $publico = app(PublicoAlvoService::class)->criar($usuario, [
            'todos_usuarios' => true,
        ]);
        $service = app(AvisoBannerService::class);

        foreach (range(1, 4) as $indice) {
            $this->criarAviso(
                $publico->id,
                'Aviso '.$indice,
                'normal',
                null,
                $agora->subMinutes($indice),
                true,
                $agora->addDay(),
            );

            $this->assertSame(
                match ($indice) {
                    1 => [1],
                    2 => [2],
                    3 => [3],
                    default => [3, 1],
                },
                $service->paginasPara($usuario, $agora)
                    ->map(fn ($pagina): int => $pagina->count())
                    ->all(),
            );
        }
    }

    public function test_status_inativo_tem_precedencia_sobre_expirado_e_link_rejeita_esquemas_inseguros(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        $aviso = Aviso::make([
            'ativo' => false,
            'inicio_exibicao' => $agora->subDays(2),
            'fim_exibicao' => $agora->subDay(),
            'link_acao' => 'javascript:alert(1)',
        ]);

        $this->assertSame('inativo', $aviso->statusExibicao($agora));
        $this->assertNull($aviso->linkAcaoSeguro());

        $aviso->link_acao = '/admin/avisos';

        $this->assertSame('/admin/avisos', $aviso->linkAcaoSeguro());
    }

    public function test_duplicacao_cria_novo_publico_e_mantem_a_copia_inativa(): void
    {
        [$usuario] = $this->criarUsuariosDeEscolasDiferentes();
        $this->darPermissoesDeAviso($usuario);
        $publico = app(PublicoAlvoService::class)->criar($usuario, [
            'todos_usuarios' => true,
        ]);
        $original = $this->criarAviso(
            $publico->id,
            'Aviso original',
            'alta',
            2,
            now()->subHour(),
        );

        $copia = app(AvisoService::class)->duplicar($original, $usuario);

        $this->assertFalse($copia->ativo);
        $this->assertSame('Cópia de Aviso original', $copia->titulo);
        $this->assertNotSame($original->publico_alvo_id, $copia->publico_alvo_id);
        $this->assertTrue($copia->publicoAlvo->todos_usuarios);
        $this->assertSame($usuario->id, $copia->criado_por_id);
        $this->assertSame($usuario->id, $copia->atualizado_por_id);
    }

    /** @return array{0: User, 1: User} */
    private function criarUsuariosDeEscolasDiferentes(): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor compartilhado dos avisos',
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
        $escolaA = Escola::query()->create([
            'codigo' => 'AVISO-A',
            'nome' => 'Escola de Avisos A',
            'setor_id' => $setor->id,
            'email' => 'avisos-a@teste.local',
            'ativo' => true,
        ]);
        $escolaB = Escola::query()->create([
            'codigo' => 'AVISO-B',
            'nome' => 'Escola de Avisos B',
            'setor_id' => $setor->id,
            'email' => 'avisos-b@teste.local',
            'ativo' => true,
        ]);

        return [
            User::factory()->create(['id_escola' => $escolaA->id]),
            User::factory()->create(['id_escola' => $escolaB->id]),
        ];
    }

    private function darPermissoesDeAviso(User $user): void
    {
        $permissoes = collect([
            ListaPermissoes::ListarAvisos,
            ListaPermissoes::CriarAvisos,
            ListaPermissoes::EditarAvisos,
            ListaPermissoes::ExcluirAvisos,
            ListaPermissoes::PublicarAvisos,
            ListaPermissoes::GerenciarPublicoAlvoDeAvisos,
        ])->map(fn (ListaPermissoes $permissao): Permission => Permission::findOrCreate(
            $permissao->label(),
            'web',
        ));

        $user->givePermissionTo($permissoes);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function criarAviso(
        int $publicoAlvoId,
        string $titulo,
        string $prioridade,
        ?int $posicao,
        CarbonInterface $inicio,
        bool $ativo = true,
        ?CarbonInterface $fim = null,
    ): Aviso {
        return Aviso::query()->create([
            'publico_alvo_id' => $publicoAlvoId,
            'titulo' => $titulo,
            'descricao' => 'Descrição de teste.',
            'prioridade' => $prioridade,
            'posicao_preferencial' => $posicao,
            'ordem_manual' => 0,
            'inicio_exibicao' => $inicio,
            'fim_exibicao' => $fim ?? now()->addDay(),
            'ativo' => $ativo,
        ]);
    }
}
