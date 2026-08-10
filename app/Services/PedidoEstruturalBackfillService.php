<?php

namespace App\Services;

use App\Support\UserActorSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PedidoEstruturalBackfillService
{
    /** @var array<int, object|null> */
    private array $users = [];

    /** @var array<int, object|null> */
    private array $schools = [];

    /** @var array<int, array<int, int>> */
    private array $userSchoolIds = [];

    /** @var array<int, int|null> */
    private array $schoolByOriginSector = [];

    /** @var array<int, int|null> */
    private array $pedidoSchoolIds = [];

    private bool $apply = false;

    private int $batchSize = 500;

    /** @var array<string, mixed> */
    private array $report = [];

    /** @return array<string, mixed> */
    public function run(bool $apply = false, int $batchSize = 500): array
    {
        $this->assertSchemaReady();

        $this->users = [];
        $this->schools = [];
        $this->userSchoolIds = [];
        $this->schoolByOriginSector = [];
        $this->pedidoSchoolIds = [];
        $this->apply = $apply;
        $this->batchSize = max(1, min($batchSize, 5000));
        $this->report = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'started_at' => now()->toIso8601String(),
            'batch_size' => $this->batchSize,
            'pedidos' => $this->emptyPedidoStats(),
            'pedido_historicos' => $this->emptyActorStats(),
            'pedido_arquivos' => $this->emptyActorStats(),
            'export_requests' => $this->emptyActorStats(),
        ];

        $this->processPedidos();
        $this->processActorTable('pedido_historicos');
        $this->processActorTable('pedido_arquivos');
        $this->processExportRequests();

        $unresolvedSchools = (int) $this->report['pedidos']['schools_unresolved'];
        $ordersWithoutSchool = DB::table('pedidos')->whereNull('escola_id')->count();
        $additionalSchoolMismatches = DB::table('pedidos as adicional')
            ->join('pedidos as principal', 'principal.id', '=', 'adicional.pedido_principal_id')
            ->whereColumn('adicional.escola_id', '<>', 'principal.escola_id')
            ->count();
        $actorOrphans = $this->countCurrentActorOrphans();
        $actorLegacyGaps = $this->countCurrentActorLegacyGaps();
        $hardConstraintsReady = $ordersWithoutSchool === 0
            && $additionalSchoolMismatches === 0
            && $actorOrphans === 0
            && $actorLegacyGaps === 0;

        $this->report['readiness'] = [
            'school_candidates_resolvable' => $unresolvedSchools === 0,
            'pedidos_escola_id_not_null' => $ordersWithoutSchool === 0,
            'pedidos_escola_id_unresolved' => $unresolvedSchools,
            'additional_school_mismatches' => $additionalSchoolMismatches,
            'actor_orphans_remaining' => $actorOrphans,
            'actor_legacy_gaps' => $actorLegacyGaps,
            'hard_constraints_ready' => $hardConstraintsReady,
            'hard_constraints_applied' => false,
        ];
        $this->report['finished_at'] = now()->toIso8601String();

        return $this->report;
    }

    private function processPedidos(): void
    {
        $this->processPedidoQuery(DB::table('pedidos')->whereNull('pedido_principal_id'));
        $this->processPedidoQuery(DB::table('pedidos')->whereNotNull('pedido_principal_id'));
    }

    private function processPedidoQuery($query): void
    {
        $query
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows): void {
                $process = function () use ($rows): void {
                    foreach ($rows as $row) {
                        $this->report['pedidos']['scanned']++;
                        $updates = [];

                        $this->reconcileActor(
                            $row,
                            $updates,
                            $this->report['pedidos'],
                            'solicitante_id',
                            'solicitante_id_legado',
                            'solicitante_nome_snapshot',
                            'solicitante_email_snapshot',
                        );
                        $this->reconcileActor(
                            $row,
                            $updates,
                            $this->report['pedidos'],
                            'responsavel_id',
                            'responsavel_id_legado',
                            'responsavel_nome_snapshot',
                            'responsavel_email_snapshot',
                        );
                        $this->reconcileActor(
                            $row,
                            $updates,
                            $this->report['pedidos'],
                            'comentario_gestor_user_id',
                            'comentario_gestor_user_id_legado',
                            'comentario_gestor_user_nome_snapshot',
                            'comentario_gestor_user_email_snapshot',
                        );
                        $this->reconcileSchool($row, $updates);

                        $this->persistUpdates('pedidos', (int) $row->id, $updates, 'pedidos');
                    }
                };

                $this->apply ? DB::transaction($process) : $process();
            });
    }

    private function countCurrentActorOrphans(): int
    {
        return collect([
            ['pedidos', 'solicitante_id'],
            ['pedidos', 'responsavel_id'],
            ['pedidos', 'comentario_gestor_user_id'],
            ['pedido_historicos', 'usuario_id'],
            ['pedido_arquivos', 'usuario_id'],
            ['export_requests', 'user_id'],
        ])->sum(function (array $actor): int {
            [$table, $column] = $actor;

            return DB::table("{$table} as origem")
                ->leftJoin('users as ator_atual', 'ator_atual.id', '=', "origem.{$column}")
                ->whereNotNull("origem.{$column}")
                ->whereNull('ator_atual.id')
                ->count();
        });
    }

    private function countCurrentActorLegacyGaps(): int
    {
        return collect([
            ['pedidos', 'solicitante_id', 'solicitante_id_legado'],
            ['pedidos', 'responsavel_id', 'responsavel_id_legado'],
            ['pedidos', 'comentario_gestor_user_id', 'comentario_gestor_user_id_legado'],
            ['pedido_historicos', 'usuario_id', 'usuario_id_legado'],
            ['pedido_arquivos', 'usuario_id', 'usuario_id_legado'],
            ['export_requests', 'user_id', 'user_id_legado'],
        ])->sum(function (array $actor): int {
            [$table, $current, $legacy] = $actor;

            return DB::table($table)
                ->whereNotNull($current)
                ->whereNull($legacy)
                ->count();
        });
    }

    private function processActorTable(string $table): void
    {
        DB::table($table)
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows) use ($table): void {
                $process = function () use ($rows, $table): void {
                    foreach ($rows as $row) {
                        $this->report[$table]['scanned']++;
                        $updates = [];

                        $this->reconcileActor(
                            $row,
                            $updates,
                            $this->report[$table],
                            'usuario_id',
                            'usuario_id_legado',
                            'usuario_nome_snapshot',
                            'usuario_email_snapshot',
                        );

                        $this->persistUpdates($table, (int) $row->id, $updates, $table);
                    }
                };

                $this->apply ? DB::transaction($process) : $process();
            });
    }

    private function processExportRequests(): void
    {
        DB::table('export_requests')
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows): void {
                $process = function () use ($rows): void {
                    foreach ($rows as $row) {
                        $this->report['export_requests']['scanned']++;
                        $updates = [];

                        $this->reconcileActor(
                            $row,
                            $updates,
                            $this->report['export_requests'],
                            'user_id',
                            'user_id_legado',
                            'user_nome_snapshot',
                            'user_email_snapshot',
                        );

                        $this->persistUpdates(
                            'export_requests',
                            (string) $row->id,
                            $updates,
                            'export_requests',
                        );
                    }
                };

                $this->apply ? DB::transaction($process) : $process();
            }, 'id');
    }

    /**
     * @param array<string, mixed> $updates
     * @param array<string, int> $stats
     */
    private function reconcileActor(
        object $row,
        array &$updates,
        array &$stats,
        string $idColumn,
        string $legacyColumn,
        string $nameColumn,
        string $emailColumn,
    ): void {
        $currentId = filled($row->{$idColumn} ?? null) ? (int) $row->{$idColumn} : null;
        $legacyId = filled($row->{$legacyColumn} ?? null) ? (int) $row->{$legacyColumn} : null;

        if ($currentId && ! $legacyId) {
            $updates[$legacyColumn] = $currentId;
            $legacyId = $currentId;
            $stats['legacy_ids_preserved']++;
        }

        $user = $this->user($currentId ?: $legacyId);

        if ($currentId && ! $this->user($currentId)) {
            $updates[$idColumn] = null;
            $stats['orphan_actor_ids_found']++;
            $stats['actor_ids_to_null']++;
        }

        if (! $user) {
            return;
        }

        if (blank($row->{$nameColumn} ?? null)) {
            $updates[$nameColumn] = $user->name;
            $stats['snapshots_filled']++;
        }

        if (blank($row->{$emailColumn} ?? null)) {
            $updates[$emailColumn] = $user->email;
            $stats['snapshots_filled']++;
        }
    }

    /** @param array<string, mixed> $updates */
    private function reconcileSchool(object $row, array &$updates): void
    {
        $stats =& $this->report['pedidos'];
        $currentId = filled($row->escola_id ?? null) ? (int) $row->escola_id : null;
        $legacyId = filled($row->escola_id_legado ?? null) ? (int) $row->escola_id_legado : null;

        if ($currentId && ! $legacyId) {
            $updates['escola_id_legado'] = $currentId;
            $legacyId = $currentId;
            $stats['school_ids_preserved']++;
        }

        $currentSchool = $this->school($currentId);

        if ($currentId && ! $currentSchool) {
            $stats['orphan_school_ids_found']++;
        }

        $desiredId = null;
        $principalSchoolId = null;

        if ((bool) ($row->is_pedido_adicional ?? false) && filled($row->pedido_principal_id ?? null)) {
            $principalSchoolId = $this->pedidoSchoolId((int) $row->pedido_principal_id);

            if ($this->school($principalSchoolId)) {
                $desiredId = $principalSchoolId;
            }
        }

        if (! $desiredId && $currentSchool) {
            $desiredId = $currentId;
        }

        if (! $desiredId && $this->school($legacyId)) {
            $desiredId = $legacyId;
        }

        if (! $desiredId) {
            $actorId = filled($row->solicitante_id ?? null)
                ? (int) $row->solicitante_id
                : (filled($row->solicitante_id_legado ?? null) ? (int) $row->solicitante_id_legado : null);
            $schoolIds = $this->schoolIdsForUser($actorId);

            if (count($schoolIds) === 1) {
                $desiredId = $schoolIds[0];
            }
        }

        if (! $desiredId && filled($row->setor_origem_id ?? null)) {
            $desiredId = $this->uniqueSchoolForOriginSector((int) $row->setor_origem_id);
        }

        if (! $desiredId) {
            if ($currentId) {
                $updates['escola_id'] = null;
            }

            $stats['schools_unresolved']++;
            $this->pedidoSchoolIds[(int) $row->id] = null;

            return;
        }

        $this->pedidoSchoolIds[(int) $row->id] = $desiredId;

        if ($desiredId !== $currentId) {
            $updates['escola_id'] = $desiredId;
            $stats['schools_resolved']++;

            if ($principalSchoolId && $desiredId === $principalSchoolId) {
                $stats['additional_school_inherited']++;
            }
        }

        if (! $legacyId) {
            $updates['escola_id_legado'] = $desiredId;
            $stats['school_ids_preserved']++;
        }

        $school = $this->school($desiredId);
        $schoolChanged = $desiredId !== $currentId;

        if ($school && ($schoolChanged || blank($row->escola_nome_snapshot ?? null))) {
            $updates['escola_nome_snapshot'] = $school->nome;
            $stats['school_snapshots_filled']++;
        }

        if ($school && ($schoolChanged || blank($row->escola_codigo_snapshot ?? null))) {
            $updates['escola_codigo_snapshot'] = $school->codigo;
            $stats['school_snapshots_filled']++;
        }
    }

    /** @param array<string, mixed> $updates */
    private function persistUpdates(string $table, int|string $id, array $updates, string $statsKey): void
    {
        if ($updates === []) {
            return;
        }

        $this->report[$statsKey]['rows_with_changes']++;

        if ($this->apply) {
            DB::table($table)->where('id', $id)->update($updates);
            $this->report[$statsKey]['rows_updated']++;
        }
    }

    private function user(?int $id): ?object
    {
        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $this->users)) {
            $this->users[$id] = DB::table('users')
                ->where('id', $id)
                ->first(['id', 'name', 'email']);
        }

        return $this->users[$id];
    }

    private function school(?int $id): ?object
    {
        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $this->schools)) {
            $this->schools[$id] = DB::table('escolas')
                ->where('id', $id)
                ->first(['id', 'nome', 'codigo', 'setor_id', 'ativo']);
        }

        return $this->schools[$id];
    }

    private function pedidoSchoolId(int $pedidoId): ?int
    {
        if (! array_key_exists($pedidoId, $this->pedidoSchoolIds)) {
            $value = DB::table('pedidos')->where('id', $pedidoId)->value('escola_id');
            $this->pedidoSchoolIds[$pedidoId] = filled($value) ? (int) $value : null;
        }

        return $this->pedidoSchoolIds[$pedidoId];
    }

    private function uniqueSchoolForOriginSector(int $setorId): ?int
    {
        if (! array_key_exists($setorId, $this->schoolByOriginSector)) {
            $ids = DB::table('escolas')
                ->where('setor_id', $setorId)
                ->where('ativo', true)
                ->limit(2)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $this->schoolByOriginSector[$setorId] = count($ids) === 1 ? $ids[0] : null;
        }

        return $this->schoolByOriginSector[$setorId];
    }

    /** @return array<int, int> */
    private function schoolIdsForUser(?int $userId): array
    {
        if (! $userId || ! $this->user($userId)) {
            return [];
        }

        if (! array_key_exists($userId, $this->userSchoolIds)) {
            $user = UserActorSnapshot::find($userId);
            $ids = $user?->idsEscolasVinculadas() ?? [];

            $this->userSchoolIds[$userId] = collect($ids)
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => (bool) $this->school($id)?->ativo)
                ->unique()
                ->values()
                ->all();
        }

        return $this->userSchoolIds[$userId];
    }

    private function assertSchemaReady(): void
    {
        $required = [
            'pedidos' => [
                'escola_id_legado',
                'escola_nome_snapshot',
                'escola_codigo_snapshot',
                'solicitante_id_legado',
                'solicitante_nome_snapshot',
                'solicitante_email_snapshot',
                'responsavel_id_legado',
                'responsavel_nome_snapshot',
                'responsavel_email_snapshot',
                'comentario_gestor_user_id_legado',
                'comentario_gestor_user_nome_snapshot',
                'comentario_gestor_user_email_snapshot',
            ],
            'pedido_historicos' => [
                'usuario_id_legado',
                'usuario_nome_snapshot',
                'usuario_email_snapshot',
            ],
            'pedido_arquivos' => [
                'usuario_id_legado',
                'usuario_nome_snapshot',
                'usuario_email_snapshot',
            ],
            'export_requests' => [
                'user_id_legado',
                'user_nome_snapshot',
                'user_email_snapshot',
            ],
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabela necessária ausente: {$table}.");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Execute a migration estrutural antes do backfill: {$table}.{$column} ausente.");
                }
            }
        }
    }

    /** @return array<string, int> */
    private function emptyActorStats(): array
    {
        return [
            'scanned' => 0,
            'rows_with_changes' => 0,
            'rows_updated' => 0,
            'legacy_ids_preserved' => 0,
            'snapshots_filled' => 0,
            'orphan_actor_ids_found' => 0,
            'actor_ids_to_null' => 0,
        ];
    }

    /** @return array<string, int> */
    private function emptyPedidoStats(): array
    {
        return $this->emptyActorStats() + [
            'school_ids_preserved' => 0,
            'school_snapshots_filled' => 0,
            'orphan_school_ids_found' => 0,
            'schools_resolved' => 0,
            'schools_unresolved' => 0,
            'additional_school_inherited' => 0,
        ];
    }
}
