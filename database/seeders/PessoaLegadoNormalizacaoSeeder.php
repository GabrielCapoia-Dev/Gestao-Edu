<?php

namespace Database\Seeders;

use App\Services\PessoaAcessoService;
use App\Services\PessoaLegadoNormalizacaoService;
use Illuminate\Database\Seeder;

/**
 * Roda a cada migrate --seed (boot Docker).
 * No boot só reprocessa pendências estruturais (professor sem pessoa / sem matrícula FK).
 * Conflitos de identidade são apenas auditados e nunca consolidados automaticamente.
 */
class PessoaLegadoNormalizacaoSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PessoaLegadoNormalizacaoService::class);

        // Boot: só estrutura. Evita reprocessar 5+ min por anomalias de e-mail permanentes.
        if ($service->haPendenciasEstruturais()) {
            $stats = $service->normalizarEstrutura(dryRun: false);

            if ($this->command) {
                $this->command->info('Pessoas legado normalizadas (pendências estruturais).');
                $this->command->line('  professores_linkados: '.($stats['professores_linkados'] ?? 0));
                $this->command->line('  pessoas_criadas: '.($stats['pessoas_criadas'] ?? 0));
                $this->command->line('  matriculas_criadas: '.($stats['matriculas_criadas'] ?? 0));
                $this->command->line('  sfa_sincronizados: '.($stats['sfa_sincronizados'] ?? 0));

                $anomalias = $stats['anomalias'] ?? [];
                if (is_array($anomalias) && $anomalias !== []) {
                    $this->command->warn('  anomalias: '.count($anomalias).' (ver `php artisan pessoas:normalizar-legado`)');
                }
            }
        } elseif ($this->command) {
            $this->command->info('Pessoas legado: sem pendências estruturais (boot ok).');
        }

        // Sanitização leve de roles de professor (idempotente).
        $sanit = app(PessoaAcessoService::class)->sanitizarAcessosSomenteProfessor();
        if ($this->command) {
            $this->command->info('Acessos de professor sanitizados: '.($sanit['usuarios'] ?? 0).' usuário(s).');
        }
    }
}
