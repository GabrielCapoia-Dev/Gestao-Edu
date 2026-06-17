<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcao_administrativa') || ! Schema::hasColumn('funcao_administrativa', 'codigo')) {
            return;
        }

        DB::table('funcao_administrativa')
            ->select(['id', 'codigo'])
            ->orderBy('id')
            ->chunkById(100, function ($funcoes): void {
                foreach ($funcoes as $funcao) {
                    $codigo = trim((string) ($funcao->codigo ?? ''));

                    if ($this->codigoEhUuid($codigo)) {
                        continue;
                    }

                    DB::table('funcao_administrativa')
                        ->where('id', $funcao->id)
                        ->update([
                            'codigo' => $this->gerarCodigoUuid(),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Data-only migration: UUID generation is intentionally not reversible.
    }

    private function codigoEhUuid(string $codigo): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $codigo,
        );
    }

    private function gerarCodigoUuid(): string
    {
        do {
            $codigo = (string) Str::uuid();
        } while (DB::table('funcao_administrativa')->where('codigo', $codigo)->exists());

        return $codigo;
    }
};
