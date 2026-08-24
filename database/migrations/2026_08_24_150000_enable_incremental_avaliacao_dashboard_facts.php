<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('avaliacao_dashboard_escopo_pendencias')) {
            Schema::create('avaliacao_dashboard_escopo_pendencias', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
                $table->string('escopo', 16);
                $table->unsignedBigInteger('escopo_id');
                $table->unsignedBigInteger('geracao')->default(1);
                $table->unsignedSmallInteger('tentativas')->default(0);
                $table->string('motivo', 64)->nullable();
                $table->timestamps();

                $table->unique(
                    ['avaliacao_id', 'escopo', 'escopo_id'],
                    'uniq_av_dashboard_escopo_pendente',
                );
                $table->index(
                    ['avaliacao_id', 'id'],
                    'idx_av_dashboard_escopo_avaliacao',
                );
            });
        }

        Schema::table('avaliacao_dashboard_pendencias', function (Blueprint $table): void {
            if (! Schema::hasColumn('avaliacao_dashboard_pendencias', 'documento_version')) {
                $table->unsignedInteger('documento_version')->default(0)->after('aluno_id');
            }
            if (! Schema::hasColumn('avaliacao_dashboard_pendencias', 'geracao')) {
                $table->unsignedBigInteger('geracao')->default(1)->after('documento_version');
            }
            if (! Schema::hasColumn('avaliacao_dashboard_pendencias', 'tentativas')) {
                $table->unsignedSmallInteger('tentativas')->default(0)->after('geracao');
            }
            if (! Schema::hasColumn('avaliacao_dashboard_pendencias', 'motivo')) {
                $table->string('motivo', 64)->nullable()->after('tentativas');
            }
        });

        $this->deduplicatePendencias();

        if (! Schema::hasIndex('avaliacao_dashboard_pendencias', 'uniq_av_dashboard_pend_avaliacao_aluno')) {
            Schema::table('avaliacao_dashboard_pendencias', function (Blueprint $table): void {
                $table->unique(
                    ['avaliacao_id', 'aluno_id'],
                    'uniq_av_dashboard_pend_avaliacao_aluno',
                );
            });
        }

        if (Schema::hasIndex('avaliacao_dashboard_pendencias', 'idx_av_dashboard_pend_aluno')) {
            Schema::table('avaliacao_dashboard_pendencias', function (Blueprint $table): void {
                $table->dropIndex('idx_av_dashboard_pend_aluno');
            });
        }

        Schema::table('avaliacao_dashboard_consolidacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('avaliacao_dashboard_consolidacoes', 'pendencias_count')) {
                $table->unsignedInteger('pendencias_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('avaliacao_dashboard_consolidacoes', 'ultima_atualizacao_em')) {
                $table->timestamp('ultima_atualizacao_em')->nullable()->after('consolidada_em');
            }
        });

        // Usado apenas pela sincronização estrutural de uma pauta. Os demais
        // escopos já são atendidos pelos prefixos dos índices existentes.
        if (! Schema::hasIndex('avaliacao_dashboard_fatos', 'idx_av_dashboard_av_pauta')) {
            Schema::table('avaliacao_dashboard_fatos', function (Blueprint $table): void {
                $table->index(
                    ['avaliacao_id', 'pauta_id'],
                    'idx_av_dashboard_av_pauta',
                );
            });
        }

        $this->backfillPendingDocuments();
        $this->stageLegacyStructuralSyncs();
        $this->refreshConsolidationCounters();
    }

    public function down(): void
    {
        DB::table('avaliacao_dashboard_consolidacoes')
            ->whereIn('status', [
                'incremental_pendente',
                'incremental_processando',
                'rebuild_pendente',
                'rebuild_processando',
            ])
            ->update(['status' => 'pendente', 'updated_at' => now()]);

        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('status', 'rebuild_erro')
            ->update(['status' => 'erro', 'updated_at' => now()]);

        Schema::dropIfExists('avaliacao_dashboard_escopo_pendencias');

        Schema::table('avaliacao_dashboard_fatos', function (Blueprint $table): void {
            $table->dropIndex('idx_av_dashboard_av_pauta');
        });

        Schema::table('avaliacao_dashboard_consolidacoes', function (Blueprint $table): void {
            $table->dropColumn(['pendencias_count', 'ultima_atualizacao_em']);
        });

        Schema::table('avaliacao_dashboard_pendencias', function (Blueprint $table): void {
            $table->dropUnique('uniq_av_dashboard_pend_avaliacao_aluno');
            $table->index(
                ['avaliacao_id', 'aluno_id'],
                'idx_av_dashboard_pend_aluno',
            );
            $table->dropColumn(['documento_version', 'geracao', 'tentativas', 'motivo']);
        });
    }

    private function deduplicatePendencias(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
DELETE antiga
FROM avaliacao_dashboard_pendencias antiga
JOIN avaliacao_dashboard_pendencias recente
  ON recente.avaliacao_id = antiga.avaliacao_id
 AND recente.aluno_id = antiga.aluno_id
 AND recente.id > antiga.id
SQL);

            return;
        }

        $manter = DB::table('avaliacao_dashboard_pendencias')
            ->selectRaw('MAX(id) as id')
            ->groupBy('avaliacao_id', 'aluno_id');

        DB::table('avaliacao_dashboard_pendencias')
            ->whereNotIn('id', $manter)
            ->delete();
    }

    private function backfillPendingDocuments(): void
    {
        $statuses = [
            'incremental_pendente',
            'incremental_processando',
        ];

        if (DB::getDriverName() === 'mysql') {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));

            DB::statement(<<<SQL
INSERT INTO avaliacao_dashboard_pendencias
    (avaliacao_id, aluno_id, documento_version, geracao, tentativas, motivo, created_at, updated_at)
SELECT d.avaliacao_id, d.aluno_id, d.version, 1, 0, 'migracao_incremental', NOW(), NOW()
FROM avaliacao_aluno_documentos d
JOIN avaliacao_dashboard_consolidacoes c ON c.avaliacao_id = d.avaliacao_id
WHERE c.status IN ({$placeholders})
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_dashboard_escopo_pendencias ep
      WHERE ep.avaliacao_id = d.avaliacao_id
  )
ON DUPLICATE KEY UPDATE
    documento_version = GREATEST(documento_version, VALUES(documento_version)),
    geracao = geracao + 1,
    motivo = VALUES(motivo),
    updated_at = VALUES(updated_at)
SQL, $statuses);

            return;
        }

        DB::table('avaliacao_aluno_documentos as d')
            ->join('avaliacao_dashboard_consolidacoes as c', 'c.avaliacao_id', '=', 'd.avaliacao_id')
            ->whereIn('c.status', $statuses)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('avaliacao_dashboard_escopo_pendencias as ep')
                    ->whereColumn('ep.avaliacao_id', 'd.avaliacao_id');
            })
            ->orderBy('d.id')
            ->select(['d.avaliacao_id', 'd.aluno_id', 'd.version'])
            ->each(function (object $documento): void {
                $agora = now();
                $version = max(0, (int) $documento->version);
                $inserida = DB::table('avaliacao_dashboard_pendencias')->insertOrIgnore([
                    'avaliacao_id' => (int) $documento->avaliacao_id,
                    'aluno_id' => (int) $documento->aluno_id,
                    'documento_version' => $version,
                    'geracao' => 1,
                    'tentativas' => 0,
                    'motivo' => 'migracao_incremental',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

                if ($inserida === 0) {
                    DB::table('avaliacao_dashboard_pendencias')
                        ->where('avaliacao_id', (int) $documento->avaliacao_id)
                        ->where('aluno_id', (int) $documento->aluno_id)
                        ->update([
                            'documento_version' => DB::raw(
                                "CASE WHEN documento_version < {$version} THEN {$version} ELSE documento_version END"
                            ),
                            'geracao' => DB::raw('geracao + 1'),
                            'motivo' => 'migracao_incremental',
                            'updated_at' => $agora,
                        ]);
                }
            });
    }

    private function stageLegacyStructuralSyncs(): void
    {
        $consolidacoes = DB::table('avaliacao_dashboard_consolidacoes')
            ->whereIn('status', ['pendente', 'processando', 'erro'])
            ->get(['id', 'avaliacao_id']);

        foreach ($consolidacoes as $consolidacao) {
            $avaliacaoId = (int) $consolidacao->avaliacao_id;
            $turmaIds = DB::table('avaliacao_turma')
                ->where('avaliacao_id', $avaliacaoId)
                ->pluck('turma_id')
                ->merge(DB::table('avaliacao_dashboard_fatos')
                    ->where('avaliacao_id', $avaliacaoId)
                    ->distinct()
                    ->pluck('turma_id'))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();
            $agora = now();

            foreach ($turmaIds as $turmaId) {
                DB::table('avaliacao_dashboard_escopo_pendencias')->insertOrIgnore([
                    'avaliacao_id' => $avaliacaoId,
                    'escopo' => 'turma',
                    'escopo_id' => $turmaId,
                    'geracao' => 1,
                    'tentativas' => 0,
                    'motivo' => 'migracao_estrutura_legada',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            DB::table('avaliacao_dashboard_consolidacoes')
                ->where('id', (int) $consolidacao->id)
                ->update([
                    'status' => $turmaIds->isEmpty() ? 'consolidado' : 'incremental_pendente',
                    'solicitada_em' => $agora,
                    'erro' => null,
                    'updated_at' => $agora,
                ]);
        }
    }

    private function refreshConsolidationCounters(): void
    {
        DB::table('avaliacao_dashboard_consolidacoes')
            ->orderBy('id')
            ->get(['id', 'avaliacao_id', 'status'])
            ->each(function (object $consolidacao): void {
                $avaliacaoId = (int) $consolidacao->avaliacao_id;
                $pendencias = DB::table('avaliacao_dashboard_pendencias')
                    ->where('avaliacao_id', $avaliacaoId)
                    ->count()
                    + DB::table('avaliacao_dashboard_escopo_pendencias')
                        ->where('avaliacao_id', $avaliacaoId)
                        ->count();
                $status = (string) $consolidacao->status;

                if (in_array($status, ['pendente', 'processando', 'erro'], true)) {
                    $status = $pendencias > 0 ? 'incremental_pendente' : 'consolidado';
                } elseif ($pendencias > 0 && ! in_array($status, ['rebuild_pendente', 'rebuild_processando', 'rebuild_erro'], true)) {
                    $status = 'incremental_pendente';
                }

                DB::table('avaliacao_dashboard_consolidacoes')
                    ->where('id', (int) $consolidacao->id)
                    ->update([
                        'status' => $status,
                        'pendencias_count' => $pendencias,
                        'updated_at' => now(),
                    ]);
            });
    }
};
