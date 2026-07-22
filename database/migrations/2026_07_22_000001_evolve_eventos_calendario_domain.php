<?php

use App\Models\Enums\EventoCalendarioStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EVENTOS = 'eventos_calendario';

    private const ESCOLAS = 'evento_calendario_escolas';

    private const HISTORICOS = 'evento_calendario_historicos';

    private const INDEX_STATUS = 'idx_evento_cal_status_inicio_criado';

    private const INDEX_TRANSPORTE = 'idx_evento_cal_escola_transporte_evento';

    public function up(): void
    {
        if (Schema::hasTable(self::EVENTOS)) {
            $needsStatusIndex = ! Schema::hasIndex(self::EVENTOS, self::INDEX_STATUS);

            DB::table(self::EVENTOS)
                ->where('ativo', true)
                ->update(['status' => EventoCalendarioStatus::PUBLICADO->value]);

            DB::table(self::EVENTOS)
                ->where('ativo', false)
                ->update(['status' => EventoCalendarioStatus::INATIVO->value]);

            Schema::table(self::EVENTOS, function (Blueprint $table) use ($needsStatusIndex): void {
                $table->string('status', 24)
                    ->default(EventoCalendarioStatus::PENDENTE_APROVACAO->value)
                    ->change();

                if ($needsStatusIndex) {
                    $table->index(['status', 'data_inicio', 'created_at'], self::INDEX_STATUS);
                }
            });
        }

        if (Schema::hasTable(self::ESCOLAS) && ! Schema::hasIndex(self::ESCOLAS, self::INDEX_TRANSPORTE)) {
            Schema::table(self::ESCOLAS, function (Blueprint $table): void {
                $table->index(
                    ['precisa_transporte', 'evento_calendario_id'],
                    self::INDEX_TRANSPORTE,
                );
            });
        }

        if (! Schema::hasTable(self::HISTORICOS)) {
            Schema::create(self::HISTORICOS, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('evento_calendario_id')
                    ->constrained(self::EVENTOS)
                    ->cascadeOnDelete();
                $table->foreignId('usuario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('acao', 32);
                $table->string('status_anterior', 24)->nullable();
                $table->string('status_novo', 24)->nullable();
                $table->text('motivo')->nullable();
                $table->timestamps();

                $table->index(
                    ['evento_calendario_id', 'created_at'],
                    'idx_evento_cal_hist_evento_data',
                );
                $table->index(['acao', 'created_at'], 'idx_evento_cal_hist_acao_data');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::HISTORICOS);

        if (Schema::hasTable(self::ESCOLAS) && Schema::hasIndex(self::ESCOLAS, self::INDEX_TRANSPORTE)) {
            Schema::table(self::ESCOLAS, function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_TRANSPORTE);
            });
        }

        if (! Schema::hasTable(self::EVENTOS)) {
            return;
        }

        DB::table(self::EVENTOS)->update(['status' => 'agendado']);

        $hasStatusIndex = Schema::hasIndex(self::EVENTOS, self::INDEX_STATUS);

        Schema::table(self::EVENTOS, function (Blueprint $table) use ($hasStatusIndex): void {
            if ($hasStatusIndex) {
                $table->dropIndex(self::INDEX_STATUS);
            }

            $table->string('status', 24)->default('agendado')->change();
        });
    }
};
