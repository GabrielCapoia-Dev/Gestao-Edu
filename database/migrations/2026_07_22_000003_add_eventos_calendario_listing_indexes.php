<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'eventos_calendario';

    private const INDEX_EVENT_DATE = 'idx_evt_cal_inicio_id';

    private const INDEX_CREATED_DATE = 'idx_evt_cal_criado_id';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $needsEventDateIndex = ! Schema::hasIndex(self::TABLE, self::INDEX_EVENT_DATE);
        $needsCreatedDateIndex = ! Schema::hasIndex(self::TABLE, self::INDEX_CREATED_DATE);

        if (! $needsEventDateIndex && ! $needsCreatedDateIndex) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($needsEventDateIndex, $needsCreatedDateIndex): void {
            if ($needsEventDateIndex) {
                $table->index(['data_inicio', 'id'], self::INDEX_EVENT_DATE);
            }

            if ($needsCreatedDateIndex) {
                $table->index(['created_at', 'id'], self::INDEX_CREATED_DATE);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $hasEventDateIndex = Schema::hasIndex(self::TABLE, self::INDEX_EVENT_DATE);
        $hasCreatedDateIndex = Schema::hasIndex(self::TABLE, self::INDEX_CREATED_DATE);

        if (! $hasEventDateIndex && ! $hasCreatedDateIndex) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($hasEventDateIndex, $hasCreatedDateIndex): void {
            if ($hasEventDateIndex) {
                $table->dropIndex(self::INDEX_EVENT_DATE);
            }

            if ($hasCreatedDateIndex) {
                $table->dropIndex(self::INDEX_CREATED_DATE);
            }
        });
    }
};
