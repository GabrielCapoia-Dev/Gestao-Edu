<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tabelasComCodigo() as $tabela) {
            $this->preencherCodigosVazios($tabela);
        }

        $this->normalizarCodigosDuplicados('componentes_curriculares');

        Schema::table('componentes_curriculares', function (Blueprint $table) {
            $table->unique('codigo', 'componentes_curriculares_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('componentes_curriculares', function (Blueprint $table) {
            $table->dropUnique('componentes_curriculares_codigo_unique');
        });
    }

    private function tabelasComCodigo(): array
    {
        return [
            'escolas',
            'users',
            'series',
            'turmas',
            'componentes_curriculares',
            'itens',
            'inventario_romaneios',
            'balancos_estoque',
            'balancos_inventario',
        ];
    }

    private function preencherCodigosVazios(string $tabela): void
    {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'codigo')) {
            return;
        }

        DB::table($tabela)
            ->where(function ($query) {
                $query->whereNull('codigo')
                    ->orWhere('codigo', '');
            })
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $registro) use ($tabela): void {
                DB::table($tabela)
                    ->where('id', $registro->id)
                    ->update(['codigo' => $this->novoUuidUnico($tabela)]);
            });
    }

    private function normalizarCodigosDuplicados(string $tabela): void
    {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'codigo')) {
            return;
        }

        $codigosUsados = [];

        DB::table($tabela)
            ->orderBy('id')
            ->get(['id', 'codigo'])
            ->each(function (object $registro) use ($tabela, &$codigosUsados): void {
                $codigo = trim((string) $registro->codigo);
                $chave = mb_strtolower($codigo);

                if ($codigo === '' || isset($codigosUsados[$chave])) {
                    $novoCodigo = $this->novoUuidUnico($tabela);

                    DB::table($tabela)
                        ->where('id', $registro->id)
                        ->update(['codigo' => $novoCodigo]);

                    $codigosUsados[mb_strtolower($novoCodigo)] = true;

                    return;
                }

                $codigosUsados[$chave] = true;
            });
    }

    private function novoUuidUnico(string $tabela): string
    {
        do {
            $codigo = (string) Str::uuid();
        } while (DB::table($tabela)->where('codigo', $codigo)->exists());

        return $codigo;
    }
};
