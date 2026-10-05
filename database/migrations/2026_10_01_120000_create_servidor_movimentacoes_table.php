<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->garantirChavePrimariaDoServidor();
        $this->removerTabelaParcialVazia();

        Schema::create('servidor_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 80);
            $table->json('alteracoes');
            $table->timestamp('ocorrido_em')->useCurrent();
            $table->index(['servidor_id', 'ocorrido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servidor_movimentacoes');
    }

    private function garantirChavePrimariaDoServidor(): void
    {
        if (! Schema::hasTable('servidores')) {
            return;
        }

        $possuiChavePrimaria = collect(Schema::getIndexes('servidores'))
            ->contains(fn (array $index): bool => (bool) ($index['primary'] ?? false));

        if ($possuiChavePrimaria) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('A tabela servidores precisa ter uma chave primária em id antes de registrar movimentações.');
        }

        $idsDuplicados = DB::table('servidores')
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($idsDuplicados || DB::table('servidores')->whereNull('id')->exists()) {
            throw new RuntimeException('Não foi possível restaurar a chave primária de servidores: existem IDs ausentes ou duplicados.');
        }

        DB::statement('ALTER TABLE `servidores` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)');
    }

    private function removerTabelaParcialVazia(): void
    {
        if (! Schema::hasTable('servidor_movimentacoes')) {
            return;
        }

        if (DB::table('servidor_movimentacoes')->exists()) {
            throw new RuntimeException('A tabela servidor_movimentacoes já contém dados e não pode ser recriada automaticamente.');
        }

        Schema::drop('servidor_movimentacoes');
    }
};
