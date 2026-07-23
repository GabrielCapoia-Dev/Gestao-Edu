<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'evento_calendario_transporte_alocacoes';

    private const FOREIGN = 'fk_evt_transp_motorista';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'motorista_id')) {
            return;
        }

        $this->dropMotoristaForeign();

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('motorista_id')->nullable()->change();
            $table->string('motorista_nome')->nullable()->after('motorista_id');
            $table->string('motorista_cpf', 11)->nullable()->after('motorista_nome');
            $table->string('motorista_matricula')->nullable()->after('motorista_cpf');
            $table->foreign('motorista_id', self::FOREIGN)
                ->references('id')
                ->on('servidores')
                ->nullOnDelete();
        });

        DB::table(self::TABLE)
            ->whereNotNull('motorista_id')
            ->orderBy('id')
            ->chunkById(200, function ($alocacoes): void {
                $motoristas = DB::table('servidores')
                    ->whereIn('id', $alocacoes->pluck('motorista_id')->filter()->unique())
                    ->get(['id', 'nome', 'cpf', 'matricula'])
                    ->keyBy('id');

                foreach ($alocacoes as $alocacao) {
                    $motorista = $motoristas->get($alocacao->motorista_id);

                    if (! $motorista) {
                        continue;
                    }

                    DB::table(self::TABLE)
                        ->where('id', $alocacao->id)
                        ->update([
                            'motorista_nome' => $motorista->nome,
                            'motorista_cpf' => $motorista->cpf,
                            'motorista_matricula' => $motorista->matricula,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'motorista_id')) {
            return;
        }

        $this->dropMotoristaForeign();

        DB::table(self::TABLE)->whereNull('motorista_id')->delete();

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn([
                'motorista_nome',
                'motorista_cpf',
                'motorista_matricula',
            ]);
            $table->unsignedBigInteger('motorista_id')->nullable(false)->change();
            $table->foreign('motorista_id', self::FOREIGN)
                ->references('id')
                ->on('servidores')
                ->restrictOnDelete();
        });
    }

    private function dropMotoristaForeign(): void
    {
        $foreign = collect(Schema::getForeignKeys(self::TABLE))
            ->first(fn (array $foreign): bool => in_array(
                'motorista_id',
                $foreign['columns'] ?? [],
                true,
            ));

        if (! $foreign) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($foreign): void {
            $table->dropForeign(
                filled($foreign['name'] ?? null)
                    ? $foreign['name']
                    : ['motorista_id'],
            );
        });
    }
};
