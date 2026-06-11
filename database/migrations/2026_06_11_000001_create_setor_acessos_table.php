<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setor_acessos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('setor_origem_id')->constrained('setor')->cascadeOnDelete();
            $table->foreignId('setor_alvo_id')->constrained('setor')->cascadeOnDelete();
            $table->boolean('pode_listar')->default(false);
            $table->boolean('pode_editar')->default(false);
            $table->boolean('pode_cancelar')->default(false);
            $table->boolean('pode_encaminhar')->default(false);
            $table->timestamps();

            $table->unique(['setor_origem_id', 'setor_alvo_id'], 'setor_acessos_origem_alvo_unique');
            $table->index(['setor_origem_id', 'pode_listar']);
            $table->index(['setor_origem_id', 'pode_editar']);
            $table->index(['setor_origem_id', 'pode_cancelar']);
            $table->index(['setor_origem_id', 'pode_encaminhar']);
        });

        $setores = DB::table('setor')
            ->where('ativo', true)
            ->get(['id', 'path', 'encaminha_pedido_para_setor_ids']);

        foreach ($setores as $origem) {
            if (filled($origem->path)) {
                foreach ($setores as $alvo) {
                    if (
                        (int) $origem->id !== (int) $alvo->id
                        && filled($alvo->path)
                        && str_starts_with((string) $alvo->path, (string) $origem->path)
                    ) {
                        $this->upsertAccess((int) $origem->id, (int) $alvo->id, [
                            'pode_listar' => true,
                            'pode_editar' => true,
                            'pode_cancelar' => true,
                        ]);
                    }
                }
            }

            foreach ($this->legacyForwardIds($origem->encaminha_pedido_para_setor_ids) as $setorAlvoId) {
                if ((int) $origem->id !== $setorAlvoId) {
                    $this->upsertAccess((int) $origem->id, $setorAlvoId, [
                        'pode_encaminhar' => true,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setor_acessos');
    }

    private function upsertAccess(int $setorOrigemId, int $setorAlvoId, array $capabilities): void
    {
        $existing = DB::table('setor_acessos')
            ->where('setor_origem_id', $setorOrigemId)
            ->where('setor_alvo_id', $setorAlvoId)
            ->first();

        $values = array_merge([
            'pode_listar' => (bool) ($existing->pode_listar ?? false),
            'pode_editar' => (bool) ($existing->pode_editar ?? false),
            'pode_cancelar' => (bool) ($existing->pode_cancelar ?? false),
            'pode_encaminhar' => (bool) ($existing->pode_encaminhar ?? false),
        ], $capabilities, [
            'updated_at' => now(),
        ]);

        if ($existing) {
            DB::table('setor_acessos')->where('id', $existing->id)->update($values);

            return;
        }

        DB::table('setor_acessos')->insert(array_merge($values, [
            'setor_origem_id' => $setorOrigemId,
            'setor_alvo_id' => $setorAlvoId,
            'created_at' => now(),
        ]));
    }

    private function legacyForwardIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
