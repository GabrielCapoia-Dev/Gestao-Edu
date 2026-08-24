<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AvaliacaoDashboardReadOnlyTest extends TestCase
{
    public function test_dashboard_e_cache_de_metricas_nao_solicitam_sincronizacao(): void
    {
        $root = dirname(__DIR__, 2);
        $fontes = [
            $root.'/app/Filament/Admin/Pages/Relatorios/DashboardAvaliacoes.php',
            $root.'/app/Services/Avaliacoes/AvaliacaoDashboardMetricsService.php',
        ];
        $mutacoesProibidas = [
            'requestRebuild(',
            'requestSyncDocumento(',
            'requestSyncPauta(',
            'requestSyncTurma(',
            'requestSyncEstruturaAvaliacao(',
            'refreshIfDirty(',
            'markDirty(',
        ];

        foreach ($fontes as $fonte) {
            $conteudo = file_get_contents($fonte);

            $this->assertIsString($conteudo);

            foreach ($mutacoesProibidas as $mutacao) {
                $this->assertStringNotContainsString($mutacao, $conteudo, $fonte);
            }
        }
    }

    public function test_rebuild_completo_so_e_solicitado_pelo_comando_manual(): void
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

        sort($callers);

        $this->assertSame([
            'app/Console/Commands/RebuildAvaliacaoDashboardFactsCommand.php',
        ], $callers);
    }
}
