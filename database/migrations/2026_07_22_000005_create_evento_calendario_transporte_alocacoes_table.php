<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'evento_calendario_transporte_alocacoes';

    private const INDEX_EVENTO = 'idx_evt_transp_aloc_evento_ativo';

    private const INDEX_VEICULO = 'idx_evt_transp_aloc_veiculo_ativo';

    private const INDEX_MOTORISTA = 'idx_evt_transp_aloc_motorista_ativo';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('evento_calendario_id');
                $table->foreignId('veiculo_transporte_id');
                $table->foreignId('motorista_id');
                $table->foreignId('criado_por_id')->nullable();
                $table->foreignId('removido_por_id')->nullable();
                $table->timestamp('removido_em')->nullable();
                $table->timestamps();
            });
        }

        $this->ensureIndex(
            self::INDEX_EVENTO,
            ['evento_calendario_id', 'removido_em'],
        );
        $this->ensureIndex(
            self::INDEX_VEICULO,
            ['veiculo_transporte_id', 'removido_em', 'evento_calendario_id'],
        );
        $this->ensureIndex(
            self::INDEX_MOTORISTA,
            ['motorista_id', 'removido_em', 'evento_calendario_id'],
        );

        $this->ensureForeign('evento_calendario_id', 'eventos_calendario', 'fk_evt_transp_evento', false);
        $this->ensureForeign('veiculo_transporte_id', 'veiculos_transporte', 'fk_evt_transp_veiculo', false);
        $this->ensureForeign('motorista_id', 'servidores', 'fk_evt_transp_motorista', false);
        $this->ensureForeign('criado_por_id', 'users', 'fk_evt_transp_criador', true);
        $this->ensureForeign('removido_por_id', 'users', 'fk_evt_transp_removedor', true);
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureIndex(string $name, array $columns): void
    {
        if (Schema::hasIndex(self::TABLE, $name)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    private function ensureForeign(
        string $column,
        string $referencedTable,
        string $name,
        bool $nullOnDelete,
    ): void {
        if ($this->foreignKeyExists($column)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use (
            $column,
            $referencedTable,
            $name,
            $nullOnDelete,
        ): void {
            $foreign = $table
                ->foreign($column, $name)
                ->references('id')
                ->on($referencedTable);

            if ($nullOnDelete) {
                $foreign->nullOnDelete();

                return;
            }

            $foreign->restrictOnDelete();
        });
    }

    private function foreignKeyExists(string $column): bool
    {
        return collect(Schema::getForeignKeys(self::TABLE))
            ->contains(fn (array $foreign): bool => in_array($column, $foreign['columns'] ?? [], true));
    }
};
