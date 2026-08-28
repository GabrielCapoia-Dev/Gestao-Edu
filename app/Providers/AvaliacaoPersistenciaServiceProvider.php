<?php

namespace App\Providers;

use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryService;
use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryServiceLazy;
use App\Services\Avaliacoes\AvaliacaoDocumentoReader;
use App\Services\Avaliacoes\AvaliacaoDocumentoReaderLazy;
use App\Services\Avaliacoes\AvaliacaoMigracaoLazyService;
use App\Services\Avaliacoes\AvaliacaoMovimentacaoRelacionalService;
use App\Services\Avaliacoes\AvaliacaoMovimentacaoRelacionalServiceLazy;
use App\Services\Avaliacoes\AvaliacaoRespostaStore;
use App\Services\Avaliacoes\AvaliacaoRespostaStoreLazy;
use Illuminate\Support\ServiceProvider;

class AvaliacaoPersistenciaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AvaliacaoMigracaoLazyService::class);

        $this->app->bind(AvaliacaoRespostaStore::class, AvaliacaoRespostaStoreLazy::class);
        $this->app->bind(AvaliacaoDocumentoReader::class, AvaliacaoDocumentoReaderLazy::class);
        $this->app->bind(
            AvaliacaoMovimentacaoRelacionalService::class,
            AvaliacaoMovimentacaoRelacionalServiceLazy::class,
        );
        $this->app->scoped(
            AvaliacaoDashboardOnDemandQueryService::class,
            AvaliacaoDashboardOnDemandQueryServiceLazy::class,
        );
    }
}
