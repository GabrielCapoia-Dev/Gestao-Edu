<?php

namespace App\Providers;

use App\Models\DominioEmail;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Policies\AlternativaPolicy;
use App\Policies\AvaliacaoPolicy;
use App\Policies\DominioEmailPolicy;
use App\Policies\EscolaPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PautaPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeriePolicy;
use App\Policies\TurmaPolicy;
use App\Policies\UserPolicy;
use App\Policies\AlunoPolicy;
use App\Policies\ProfessorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use App\Models\ComponenteCurricular;
use App\Models\TipoManutencao;
use App\Policies\TipoManutencaoPolicy;
use App\Models\Pedido;
use App\Policies\PedidoPolicy;
use App\Models\PedidoArquivo;
use App\Policies\PedidoArquivoPolicy;
use App\Observers\PedidoObserver;
use App\Observers\ProfessorObserver;
use App\Observers\TurmaComponenteProfessorObserver;
use Filament\View\PanelsRenderHook;
use Filament\Support\Facades\FilamentView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\EquipeGestora;
use App\Policies\EquipeGestoraPolicy;
use App\Policies\ComponenteCurricularPolicy;
use App\Models\FuncaoAdministrativa;
use App\Models\Item;
use App\Models\Setor;
use App\Models\TurmaComponenteProfessor;
use App\Policies\FuncaoAdministrativaPolicy;
use App\Policies\ItemPolicy;
use App\Policies\SetorPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // ── Policies ───────────────────────────────────────────────────────────
        Gate::policy(User::class,           UserPolicy::class);
        Gate::policy(Role::class,           RolePolicy::class);
        Gate::policy(Permission::class,     PermissionPolicy::class);
        Gate::policy(DominioEmail::class,   DominioEmailPolicy::class);
        Gate::policy(Escola::class,         EscolaPolicy::class);
        Gate::policy(Serie::class,          SeriePolicy::class);
        Gate::policy(Turma::class,          TurmaPolicy::class);
        Gate::policy(Aluno::class,          AlunoPolicy::class);
        Gate::policy(Professor::class,      ProfessorPolicy::class);
        Gate::policy(TipoManutencao::class, TipoManutencaoPolicy::class);
        Gate::policy(Pedido::class,         PedidoPolicy::class);
        Gate::policy(PedidoArquivo::class,  PedidoArquivoPolicy::class);
        Gate::policy(EquipeGestora::class,  EquipeGestoraPolicy::class);
        Gate::policy(ComponenteCurricular::class,  ComponenteCurricularPolicy::class);
        Gate::policy(FuncaoAdministrativa::class, FuncaoAdministrativaPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Setor::class, SetorPolicy::class);
        Gate::policy(Alternativa::class, AlternativaPolicy::class);
        Gate::policy(Pauta::class, PautaPolicy::class);
        Gate::policy(Avaliacao::class, AvaliacaoPolicy::class);


        // ── Observers ──────────────────────────────────────────────────────────
        Pedido::observe(PedidoObserver::class);
        Professor::observe(ProfessorObserver::class);
        TurmaComponenteProfessor::observe(TurmaComponenteProfessorObserver::class);

        // ── Gates ──────────────────────────────────────────────────────────────
        Gate::define('admin-only', fn($user) => $user->hasRole('Admin'));

        // ── Assets ─────────────────────────────────────────────────────────────
        FilamentAsset::register([
            Js::make('filament-modal-select-fix', secure_asset('js/filament-modal-select-fix.js')),
            Css::make('geral', secure_asset('css/geral.css')),
        ]);

        // ── Render Hooks ───────────────────────────────────────────────────────
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn() => view('components.open-url-listener'),
        );
    }
}
