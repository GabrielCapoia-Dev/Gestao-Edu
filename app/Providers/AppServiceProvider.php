<?php

namespace App\Providers;

use App\Models\DominioEmail;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\Aluno;
use App\Models\Laudo;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Policies\DominioEmailPolicy;
use App\Policies\EscolaPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeriePolicy;
use App\Policies\TurmaPolicy;
use App\Policies\UserPolicy;
use App\Policies\AlunoPolicy;
use App\Policies\LaudoPolicy;
use App\Policies\ProfessorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use App\Services\UserService;
use Hasnayeen\Themes\Themes;
use Illuminate\Support\Facades\Auth;
use App\Models\AlunoLaudo;
use App\Policies\AlunoLaudoPolicy;
use App\Models\TipoManutencao;
use App\Policies\TipoManutencaoPolicy;
use App\Models\Pedido;
use App\Policies\PedidoPolicy;
use App\Models\PedidoArquivo;
use App\Policies\PedidoArquivoPolicy;
use Illuminate\Support\Facades\Blade;
use App\Observers\PedidoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(DominioEmail::class, DominioEmailPolicy::class);
        Gate::policy(Escola::class, EscolaPolicy::class);
        Gate::policy(Serie::class, SeriePolicy::class);
        Gate::policy(Turma::class, TurmaPolicy::class);
        Gate::policy(Aluno::class, AlunoPolicy::class);
        Gate::policy(Professor::class, ProfessorPolicy::class);
        Gate::policy(Laudo::class, LaudoPolicy::class);
        Gate::policy(AlunoLaudo::class, AlunoLaudoPolicy::class);
        Gate::policy(TipoManutencao::class, TipoManutencaoPolicy::class);
        Gate::policy(Pedido::class, PedidoPolicy::class);
        Gate::policy(PedidoArquivo::class, PedidoArquivoPolicy::class);
        
        Pedido::observe(PedidoObserver::class);
        
        Gate::define('admin-only', function ($user) {
            return $user->hasRole('Admin');
        });

        FilamentAsset::register([
            Css::make('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'),
            Js::make('leaflet-js',  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'),

            Js::make(
                'chartjs-datalabels',
                'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2'
            ),

            Css::make('professores-modal', asset('css/professores-modal.css')),
        ]);

        Event::listen(ServingFilament::class, function () {

            /** @var \App\Models\User|null $user */
            $user = Filament::getCurrentPanel()?->auth()?->user()
                ?? Auth::user();

            if (! $user) {
                return;
            }

            $canViewPanel = $user->hasPermissionTo('Visualizar Painel Personalizado');

            app(Themes::class)->register(
                $canViewPanel
                    ? [\Hasnayeen\Themes\Themes\Sunset::class]
                    : [\App\Filament\Themes\TemaSME::class],
                true
            );
        });
    }
}
