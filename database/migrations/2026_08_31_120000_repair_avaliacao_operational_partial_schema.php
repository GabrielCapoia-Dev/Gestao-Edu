<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairRespostas();
        $this->repairInformacoes();
        $this->repairResumos();
    }

    public function down(): void
    {
        // Reparo permanente de integridade. O rollback não recria schema parcial.
    }

    private function repairRespostas(): void
    {
        $table = 'avaliacao_respostas_operacionais';
        if (! Schema::hasTable($table)) {
            return;
        }

        $this->addForeignIfMissing($table, 'fk_av_resp_comp', 'componente_curricular_id', 'componentes_curriculares');
        $this->addForeignIfMissing($table, 'fk_av_resp_prof', 'professor_id', 'professores');
        $this->addForeignIfMissing($table, 'fk_av_resp_alt', 'alternativa_id', 'alternativas');
        $this->addIndexIfMissing($table, 'idx_av_resp_ciclo_pauta_alt', ['ciclo_id', 'pauta_id', 'alternativa_id']);
        $this->addIndexIfMissing($table, 'idx_av_resp_ciclo_comp_prof', ['ciclo_id', 'componente_curricular_id', 'professor_id']);
        $this->addIndexIfMissing($table, 'idx_av_resp_av_aluno', ['avaliacao_id', 'aluno_id']);
    }

    private function repairInformacoes(): void
    {
        $table = 'avaliacao_informacoes_operacionais';
        if (! Schema::hasTable($table)) {
            return;
        }

        $this->addForeignIfMissing($table, 'fk_av_info_comp', 'componente_curricular_id', 'componentes_curriculares');
        $this->addForeignIfMissing($table, 'fk_av_info_prof', 'professor_id', 'professores');
        $this->addUniqueIfMissing($table, 'uniq_av_info_ciclo_aluno_comp', ['ciclo_id', 'aluno_id', 'componente_chave']);
        $this->addIndexIfMissing($table, 'idx_av_info_av_aluno', ['avaliacao_id', 'aluno_id']);
    }

    private function repairResumos(): void
    {
        $table = 'avaliacao_snapshot_resumos_componentes';
        if (! Schema::hasTable($table)) {
            return;
        }

        $this->addForeignIfMissing($table, 'fk_av_resumo_comp', 'componente_curricular_id', 'componentes_curriculares');
        $this->addUniqueIfMissing($table, 'uniq_av_snapshot_resumo_evento_comp', ['evento_id', 'componente_curricular_id']);
        $this->addIndexIfMissing($table, 'idx_av_snapshot_resumo_ciclo_comp', ['ciclo_id', 'componente_curricular_id']);
    }

    private function addForeignIfMissing(
        string $table,
        string $name,
        string $column,
        string $references,
    ): void {
        if ($this->hasForeign($table, $name, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $column, $references): void {
            $blueprint->foreign($column, $name)->references('id')->on($references)->nullOnDelete();
        });
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    /** @param list<string> $columns */
    private function addUniqueIfMissing(string $table, string $name, array $columns): void
    {
        if (Schema::hasIndex($table, $name, 'unique')) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }

    private function hasForeign(string $table, string $name, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(function (array $foreign) use ($name, $column): bool {
                return strcasecmp((string) ($foreign['name'] ?? ''), $name) === 0
                    || in_array($column, (array) ($foreign['columns'] ?? []), true);
            });
    }
};
