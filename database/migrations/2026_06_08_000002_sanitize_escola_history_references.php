<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('escolas') || ! Schema::hasColumn('escolas', 'codigo')) {
            return;
        }

        $codigosComHistorico = DB::table('escolas')
            ->whereNotNull('codigo')
            ->select('codigo')
            ->groupBy('codigo')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('codigo');

        foreach ($codigosComHistorico as $codigo) {
            $principal = $this->escolaPrincipal((string) $codigo);

            if (! $principal) {
                continue;
            }

            $idsHistoricos = DB::table('escolas')
                ->where('codigo', $codigo)
                ->where('id', '!=', $principal->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($idsHistoricos as $idHistorico) {
                $this->remapearVinculos($idHistorico, (int) $principal->id, $principal->setor_id !== null ? (int) $principal->setor_id : null);
            }

            DB::table('escolas')
                ->where('codigo', $codigo)
                ->where('id', '!=', $principal->id)
                ->update($this->payloadAtualizado(['ativo' => false]));
        }
    }

    public function down(): void
    {
        //
    }

    private function escolaPrincipal(string $codigo): ?object
    {
        $query = DB::table('escolas')
            ->where('codigo', $codigo)
            ->select(['id', 'setor_id']);

        if (Schema::hasColumn('escolas', 'ativo')) {
            $principalAtiva = (clone $query)
                ->where('ativo', true)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($principalAtiva) {
                return $principalAtiva;
            }
        }

        $principal = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($principal && Schema::hasColumn('escolas', 'ativo')) {
            DB::table('escolas')
                ->where('id', $principal->id)
                ->update($this->payloadAtualizado(['ativo' => true]));
        }

        return $principal;
    }

    private function remapearVinculos(int $idHistorico, int $idPrincipal, ?int $setorPrincipalId): void
    {
        $this->atualizarReferencia('users', 'id_escola', $idHistorico, $idPrincipal);
        $this->atualizarReferencia('turmas', 'id_escola', $idHistorico, $idPrincipal);
        $this->atualizarReferencia('professores', 'id_escola', $idHistorico, $idPrincipal);
        $this->atualizarReferencia('pedidos', 'escola_id', $idHistorico, $idPrincipal);
        $this->atualizarInventarios($idHistorico, $idPrincipal, $setorPrincipalId);
        $this->atualizarReferencia('inventario_pedidos', 'escola_id', $idHistorico, $idPrincipal);
        $this->atualizarReferencia('avaliacao_exportacoes', 'escola_id', $idHistorico, $idPrincipal);

        $this->remapearPivot('escola_user', 'user_id', 'escola_id', $idHistorico, $idPrincipal);
        $this->remapearPivot('avaliacao_escola', 'avaliacao_id', 'escola_id', $idHistorico, $idPrincipal);
    }

    private function atualizarReferencia(string $tabela, string $coluna, int $idHistorico, int $idPrincipal): void
    {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
            return;
        }

        DB::table($tabela)
            ->where($coluna, $idHistorico)
            ->update($this->payloadAtualizado([$coluna => $idPrincipal], $tabela));
    }

    private function atualizarInventarios(int $idHistorico, int $idPrincipal, ?int $setorPrincipalId): void
    {
        if (! Schema::hasTable('inventarios') || ! Schema::hasColumn('inventarios', 'escola_id')) {
            return;
        }

        $payload = ['escola_id' => $idPrincipal];

        if ($setorPrincipalId !== null && Schema::hasColumn('inventarios', 'setor_id')) {
            $payload['setor_id'] = $setorPrincipalId;
        }

        if (DB::table('inventarios')->where('escola_id', $idPrincipal)->exists()) {
            return;
        }

        DB::table('inventarios')
            ->where('escola_id', $idHistorico)
            ->update($this->payloadAtualizado($payload, 'inventarios'));
    }

    private function remapearPivot(string $tabela, string $colunaDona, string $colunaEscola, int $idHistorico, int $idPrincipal): void
    {
        if (
            ! Schema::hasTable($tabela)
            || ! Schema::hasColumn($tabela, $colunaDona)
            || ! Schema::hasColumn($tabela, $colunaEscola)
        ) {
            return;
        }

        $vinculos = DB::table($tabela)
            ->where($colunaEscola, $idHistorico)
            ->pluck($colunaDona)
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($vinculos as $donoId) {
            DB::table($tabela)->updateOrInsert(
                [$colunaDona => $donoId, $colunaEscola => $idPrincipal],
                $this->payloadAtualizado([], $tabela)
            );
        }

        DB::table($tabela)
            ->where($colunaEscola, $idHistorico)
            ->delete();
    }

    private function payloadAtualizado(array $dados, ?string $tabela = 'escolas'): array
    {
        if ($tabela !== null && Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'updated_at')) {
            $dados['updated_at'] = now();
        }

        return $dados;
    }
};
