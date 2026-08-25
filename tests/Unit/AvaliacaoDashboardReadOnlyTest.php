<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AvaliacaoDashboardReadOnlyTest extends TestCase
{
    public function test_leituras_do_dashboard_nao_dependem_de_fatos_ou_consolidacoes(): void
    {
        $root = dirname(__DIR__, 2);
        $fontes = [
            $root.'/app/Filament/Admin/Pages/Relatorios/DashboardAvaliacoes.php',
            $root.'/app/Services/Avaliacoes/AvaliacaoDashboardOnDemandQueryService.php',
            $root.'/app/Services/Avaliacoes/AvaliacaoDashboardProgressService.php',
        ];

        foreach ($fontes as $fonte) {
            $conteudo = file_get_contents($fonte);

            $this->assertIsString($conteudo);
            $this->assertStringNotContainsString('avaliacao_dashboard_fatos', $conteudo, $fonte);
            $this->assertStringNotContainsString('avaliacao_dashboard_consolidacoes', $conteudo, $fonte);
            $this->assertStringNotContainsString('AvaliacaoDashboardFactsService', $conteudo, $fonte);
            $this->assertStringNotContainsString('AvaliacaoDashboardMetricsService', $conteudo, $fonte);
        }
    }

    public function test_runtime_nao_agenda_sincronizacao_rebuild_ou_worker_de_fatos(): void
    {
        $root = dirname(__DIR__, 2);
        $fontes = [
            $root.'/app/Services/Avaliacoes/AvaliacaoAlunoDocumentoService.php',
            $root.'/app/Filament/Admin/Pages/GestaoAvaliacoes.php',
            $root.'/app/Providers/AppServiceProvider.php',
            $root.'/bootstrap/app.php',
            $root.'/composer.json',
            $root.'/docker-compose.yml',
            $root.'/entrypoint.sh',
        ];
        $trechosProibidos = [
            'requestDashboardFactsSync',
            'requestSyncEstruturaAvaliacao(',
            'AvaliacaoDashboardAlunoObserver',
            'AvaliacaoDashboardSourceObserver',
            'avaliacoes:dispatch-dashboard-pendencias --limit=',
            'queue-dashboard:',
            'queue-worker", "dashboard',
            'queue:listen dashboard_redis',
        ];

        foreach ($fontes as $fonte) {
            $conteudo = file_get_contents($fonte);

            $this->assertIsString($conteudo);

            foreach ($trechosProibidos as $trecho) {
                $this->assertStringNotContainsString($trecho, $conteudo, $fonte);
            }
        }
    }

    public function test_nenhum_codigo_de_aplicacao_solicita_rebuild(): void
    {
        $root = dirname(__DIR__, 2);
        $callers = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.'/app', RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $conteudo = file_get_contents($file->getPathname());

            if (is_string($conteudo) && str_contains($conteudo, '->requestRebuild(')) {
                $callers[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        $this->assertSame([], $callers);
    }
}
