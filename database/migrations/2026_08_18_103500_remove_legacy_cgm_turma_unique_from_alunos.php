<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'alunos';

    private const LEGACY_UNIQUE = 'alunos_cgm_id_turma_unique';

    public function up(): void
    {
        /*
         * Este índice pertence ao modelo antigo, em que um CGM só podia existir
         * uma vez em uma determinada turma.
         *
         * No modelo atual, Principal e Contra Turno são vínculos independentes e
         * podem coexistir inclusive na mesma turma. Além disso, remanejamentos
         * preservam registros históricos, portanto uma unicidade por
         * CGM + turma (mesmo acrescentando tipo_vinculo) impediria um aluno de
         * retornar futuramente a uma turma já existente no seu histórico.
         *
         * A unicidade dos vínculos ATIVOS é controlada pelas chaves próprias:
         * - cgm_matricula_ativa / cgm_unidade_matricula_ativa para Principal;
         * - cgm_contra_turno_ativo para Contra Turno.
         */
        if (Schema::hasIndex(self::TABLE, self::LEGACY_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::LEGACY_UNIQUE, 'unique')) {
            return;
        }

        /*
         * Depois desta migration podem existir, legitimamente, dois ou mais
         * registros com o mesmo CGM e turma (Principal + Contra Turno ou
         * históricos de remanejamento). Nesse caso não é seguro restaurar a
         * constraint antiga durante rollback.
         */
        $possuiDuplicidadesLegitimas = DB::table(self::TABLE)
            ->select(['cgm', 'id_turma'])
            ->whereNotNull('cgm')
            ->whereNotNull('id_turma')
            ->groupBy('cgm', 'id_turma')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($possuiDuplicidadesLegitimas) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(['cgm', 'id_turma'], self::LEGACY_UNIQUE);
        });
    }
};
