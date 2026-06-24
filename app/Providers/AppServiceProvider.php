<?php

namespace App\Providers;

use App\Http\Responses\PasswordChangeLoginResponse;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Contrato;
use App\Models\DominioEmail;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\FuncaoAdministrativa;
use App\Models\Item;
use App\Models\Pauta;
use App\Models\Pedido;
use App\Models\PedidoArquivo;
use App\Models\PedidoMerenda;
use App\Models\Permission;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Setor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\TipoManutencao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Observers\PedidoObserver;
use App\Observers\ProfessorObserver;
use App\Observers\TurmaComponenteProfessorObserver;
use App\Policies\AlternativaPolicy;
use App\Policies\AlunoPolicy;
use App\Policies\AvaliacaoPolicy;
use App\Policies\ComponenteCurricularPolicy;
use App\Policies\ContratoPolicy;
use App\Policies\DominioEmailPolicy;
use App\Policies\EmpresaContratadaPolicy;
use App\Policies\EscolaPolicy;
use App\Policies\ExportRequestPolicy;
use App\Policies\FuncaoAdministrativaPolicy;
use App\Policies\ItemPolicy;
use App\Policies\PautaPolicy;
use App\Policies\PedidoArquivoPolicy;
use App\Policies\PedidoMerendaPolicy;
use App\Policies\PedidoPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProfessorPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeriePolicy;
use App\Policies\SetorPolicy;
use App\Policies\ServidorPolicy;
use App\Policies\ServidorFuncaoAdministrativaPolicy;
use App\Policies\TipoManutencaoPolicy;
use App\Policies\TurmaPolicy;
use App\Policies\UserPolicy;
use App\Services\NotificationCenterService;
use App\Services\UserPresenceService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
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
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // ── Policies ───────────────────────────────────────────────────────────
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(DominioEmail::class, DominioEmailPolicy::class);
        Gate::policy(Escola::class, EscolaPolicy::class);
        Gate::policy(Serie::class, SeriePolicy::class);
        Gate::policy(Turma::class, TurmaPolicy::class);
        Gate::policy(Aluno::class, AlunoPolicy::class);
        Gate::policy(Professor::class, ProfessorPolicy::class);
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

        // ── Observers ──────────────────────────────────────────────────────────
        Pedido::observe(PedidoObserver::class);
        Professor::observe(ProfessorObserver::class);
        TurmaComponenteProfessor::observe(TurmaComponenteProfessorObserver::class);

        // ── Gates ──────────────────────────────────────────────────────────────
        Gate::define('admin-only', fn ($user) => $user->hasRole('Admin'));

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(UserPresenceService::class)->touch($event->user, markLogin: true);
            }
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
