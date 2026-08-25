<?php

namespace App\Providers;

use App\Http\Responses\PasswordChangeLoginResponse;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\Aviso;
use App\Models\BaixasEstoques;
use App\Models\BalancoEstoque;
use App\Models\BalancoInventario;
use App\Models\ComponenteCurricular;
use App\Models\Contrato;
use App\Models\DominioEmail;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\Estoque;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\ExportRequest;
use App\Models\FeedbackPedido;
use App\Models\FuncaoAdministrativa;
use App\Models\ImportacaoEventoCalendario;
use App\Models\Inventario;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Models\LocalTrabalho;
use App\Models\Lotacao;
use App\Models\NotificacaoEnvio;
use App\Models\Pauta;
use App\Models\Pedido;
use App\Models\PedidoArquivo;
use App\Models\PedidoMerenda;
use App\Models\Permission;
use App\Models\Professor;
use App\Models\ReservaVeiculo;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Observers\PedidoObserver;
use App\Observers\ProfessorObserver;
use App\Observers\TurmaComponenteProfessorObserver;
use App\Policies\AlternativaPolicy;
use App\Policies\AlunoPolicy;
use App\Policies\AvaliacaoPolicy;
use App\Policies\AvisoPolicy;
use App\Policies\BaixasEstoquesPolicy;
use App\Policies\BalancoEstoquePolicy;
use App\Policies\BalancoInventarioPolicy;
use App\Policies\ComponenteCurricularPolicy;
use App\Policies\ContratoPolicy;
use App\Policies\DominioEmailPolicy;
use App\Policies\EmpresaContratadaPolicy;
use App\Policies\EscolaPolicy;
use App\Policies\EstoquePolicy;
use App\Policies\EventoCalendarioPolicy;
use App\Policies\EventoCalendarioTransporteAlocacaoPolicy;
use App\Policies\ExportRequestPolicy;
use App\Policies\FeedbackPedidoPolicy;
use App\Policies\FuncaoAdministrativaPolicy;
use App\Policies\ImportacaoEventoCalendarioPolicy;
use App\Policies\InventarioPedidoPolicy;
use App\Policies\InventarioPolicy;
use App\Policies\ItemPolicy;
use App\Policies\LocalTrabalhoPolicy;
use App\Policies\LotacaoPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PautaPolicy;
use App\Policies\PedidoArquivoPolicy;
use App\Policies\PedidoMerendaPolicy;
use App\Policies\PedidoPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProfessorPolicy;
use App\Policies\ReservaVeiculoPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeriePolicy;
use App\Policies\ServidorFuncaoAdministrativaPolicy;
use App\Policies\ServidorPolicy;
use App\Policies\SetorPolicy;
use App\Policies\TipoManutencaoPolicy;
use App\Policies\TurmaPolicy;
use App\Policies\UserPolicy;
use App\Policies\VeiculoTransportePolicy;
use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryService;
use App\Services\Exports\ExportSessionService;
use App\Services\NotificationCenterService;
use App\Services\UserPresenceService;
use Filament\Actions\ViewAction;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, PasswordChangeLoginResponse::class);
        $this->app->scoped(AvaliacaoDashboardOnDemandQueryService::class);
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::before(
            fn (User $user): ?bool => $user->isOperationallyActive() ? null : false,
        );

        // ── Policies ───────────────────────────────────────────────────────────
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(DominioEmail::class, DominioEmailPolicy::class);
        Gate::policy(Escola::class, EscolaPolicy::class);
        Gate::policy(LocalTrabalho::class, LocalTrabalhoPolicy::class);
        Gate::policy(Lotacao::class, LotacaoPolicy::class);
        Gate::policy(Serie::class, SeriePolicy::class);
        Gate::policy(Turma::class, TurmaPolicy::class);
        Gate::policy(Aluno::class, AlunoPolicy::class);
        Gate::policy(Professor::class, ProfessorPolicy::class);
        Gate::policy(ReservaVeiculo::class, ReservaVeiculoPolicy::class);
        Gate::policy(TipoManutencao::class, TipoManutencaoPolicy::class);
        Gate::policy(EmpresaContratada::class, EmpresaContratadaPolicy::class);
        Gate::policy(Contrato::class, ContratoPolicy::class);
        Gate::policy(Pedido::class, PedidoPolicy::class);
        Gate::policy(PedidoMerenda::class, PedidoMerendaPolicy::class);
        Gate::policy(PedidoArquivo::class, PedidoArquivoPolicy::class);
        Gate::policy(ExportRequest::class, ExportRequestPolicy::class);
        Gate::policy(ComponenteCurricular::class, ComponenteCurricularPolicy::class);
        Gate::policy(FuncaoAdministrativa::class, FuncaoAdministrativaPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Setor::class, SetorPolicy::class);
        Gate::policy(Servidor::class, ServidorPolicy::class);
        Gate::policy(ServidorFuncaoAdministrativa::class, ServidorFuncaoAdministrativaPolicy::class);
        Gate::policy(Alternativa::class, AlternativaPolicy::class);
        Gate::policy(Pauta::class, PautaPolicy::class);
        Gate::policy(Avaliacao::class, AvaliacaoPolicy::class);
        Gate::policy(Aviso::class, AvisoPolicy::class);
        Gate::policy(Estoque::class, EstoquePolicy::class);
        Gate::policy(EventoCalendario::class, EventoCalendarioPolicy::class);
        Gate::policy(EventoCalendarioTransporteAlocacao::class, EventoCalendarioTransporteAlocacaoPolicy::class);
        Gate::policy(BaixasEstoques::class, BaixasEstoquesPolicy::class);
        Gate::policy(BalancoEstoque::class, BalancoEstoquePolicy::class);
        Gate::policy(Inventario::class, InventarioPolicy::class);
        Gate::policy(BalancoInventario::class, BalancoInventarioPolicy::class);
        Gate::policy(InventarioPedido::class, InventarioPedidoPolicy::class);
        Gate::policy(ImportacaoEventoCalendario::class, ImportacaoEventoCalendarioPolicy::class);
        Gate::policy(FeedbackPedido::class, FeedbackPedidoPolicy::class);
        Gate::policy(NotificacaoEnvio::class, NotificationPolicy::class);
        Gate::policy(VeiculoTransporte::class, VeiculoTransportePolicy::class);

        // ── Ficha completa do aluno no ViewAction ─────────────────────────────
        ViewAction::configureUsing(function (ViewAction $action): void {
            if ($action->getName() !== 'visualizar') {
                return;
            }

            $action->modalContent(function ($record) {
                if (! $record instanceof Aluno) {
                    return null;
                }

                $record->loadMissing([
                    'turma.escola',
                    'turma.serie',
                    'statusAlteradoPor',
                    'alunoOrigem.turma.escola',
                    'alunoOrigem.turma.serie',
                    'pendenciaOrigem.turma.escola',
                    'pendenciaOrigem.turma.serie',
                    'turmaOrigem.escola',
                    'turmaOrigem.serie',
                ]);

                $vinculos = Aluno::query()
                    ->with(['turma.escola', 'turma.serie'])
                    ->where('cgm', Aluno::normalizarCgm($record->cgm))
                    ->orderByRaw(
                        'case status when ? then 0 when ? then 1 when ? then 2 when ? then 3 else 4 end',
                        [
                            Aluno::STATUS_MATRICULADO,
                            Aluno::STATUS_PENDENTE,
                            Aluno::STATUS_REMANEJADO,
                            Aluno::STATUS_TRANSFERIDO,
                        ]
                    )
                    ->orderByRaw(
                        'case when tipo_vinculo = ? then 0 else 1 end',
                        [Aluno::TIPO_VINCULO_PRINCIPAL]
                    )
                    ->latest('status_alterado_em')
                    ->latest('id')
                    ->get();

                $user = auth()->user();
                $podeListarEscolas = $user instanceof User
                    && Gate::forUser($user)->allows('viewAny', Escola::class);

                return view('components.alunos.ficha-aluno-modal', [
                    'aluno' => $record,
                    'vinculos' => $vinculos,
                    'podeListarEscolas' => $podeListarEscolas,
                ]);
            });
        });

        // ── Observers ──────────────────────────────────────────────────────────
        Pedido::observe(PedidoObserver::class);
        Professor::observe(ProfessorObserver::class);
        TurmaComponenteProfessor::observe(TurmaComponenteProfessorObserver::class);

        // ── Gates ──────────────────────────────────────────────────────────────
        Gate::define('admin-only', fn ($user) => $user->hasRole('Admin'));
        Gate::define('exportReports', fn (User $user): bool => app(UserPolicy::class)->exportReports($user));

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && $event->user->isOperationallyActive()) {
                if (request()->hasSession()) {
                    request()->session()->put('auth_version', (int) $event->user->auth_version);
                }

                app(UserPresenceService::class)->touch($event->user, markLogin: true);
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! app()->bound('request')) {
                return;
            }

            app(ExportSessionService::class)->endCurrentSession(
                request(),
                $event->user instanceof User ? $event->user : null,
            );
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->channel === 'database' && $event->notifiable instanceof User) {
                app(NotificationCenterService::class)->forgetUnreadCountCache($event->notifiable);
            }
        });

        // ── Assets ─────────────────────────────────────────────────────────────
        $assetVersion = function (string $path): int {
            $modifiedAt = file_exists(public_path($path))
                ? filemtime(public_path($path))
                : false;

            return $modifiedAt ?: time();
        };

        $selectFixJs = 'js/filament-modal-select-fix.js';
        $datePasteJs = 'js/filament-date-paste.js';
        $actionLoadingJs = 'js/app/action-loading.js';
        $actionLoadingCss = 'css/action-loading.css';
        $geralCss = 'css/geral.css';

        FilamentAsset::register([
            Js::make('filament-modal-select-fix', asset($selectFixJs).'?v='.$assetVersion($selectFixJs)),
            Js::make('filament-date-paste', asset($datePasteJs).'?v='.$assetVersion($datePasteJs)),
            Js::make('action-loading', asset($actionLoadingJs).'?v='.$assetVersion($actionLoadingJs)),
            Css::make('geral', asset($geralCss).'?v='.$assetVersion($geralCss)),
            Css::make('action-loading', asset($actionLoadingCss).'?v='.$assetVersion($actionLoadingCss)),
        ]);

        // ── Render Hooks ───────────────────────────────────────────────────────
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('components.open-url-listener'),
        );

    }
}
