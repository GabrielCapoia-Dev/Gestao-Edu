<?php

namespace App\Livewire\Home;

use App\Filament\Admin\Resources\Avisos\AvisoResource;
use App\Models\Aviso;
use App\Models\User;
use App\Services\Dashboard\AvisoBannerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

class AvisosBanner extends Component
{
    public function marcarComoLido(int $avisoId, int $versaoEnvio): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user, 403);

        app(AvisoBannerService::class)->marcarComoLido($user, $avisoId, $versaoEnvio);
    }

    public function placeholder(): View
    {
        return view('livewire.home.avisos-banner-placeholder');
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        $erroAoCarregar = false;

        try {
            $paginas = $user
                ? app(AvisoBannerService::class)->paginasPara($user)
                : collect();
        } catch (Throwable $exception) {
            report($exception);
            $paginas = collect();
            $erroAoCarregar = true;
        }

        return view('livewire.home.avisos-banner', [
            'paginas' => $paginas,
            'quantidadeAvisos' => $paginas->sum(fn ($pagina): int => $pagina->count()),
            'erroAoCarregar' => $erroAoCarregar,
            'podeGerenciar' => $user
                ? Gate::forUser($user)->allows('viewAny', Aviso::class)
                : false,
            'urlGerenciar' => AvisoResource::getUrl(),
        ]);
    }
}
