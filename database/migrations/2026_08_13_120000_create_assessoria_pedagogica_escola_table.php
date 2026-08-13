<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessoria_pedagogica_escola')) {
            Schema::create('assessoria_pedagogica_escola', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('servidor_funcao_administrativa_id');
                $table->foreignId('escola_id')
                    ->constrained('escolas')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->foreign('servidor_funcao_administrativa_id', 'fk_assessoria_escola_vinculo')
                    ->references('id')
                    ->on('servidor_funcao_administrativa')
                    ->cascadeOnDelete();

                $table->unique(
                    ['servidor_funcao_administrativa_id', 'escola_id'],
                    'assessoria_pedagogica_escola_unique',
                );
                $table->index(
                    ['escola_id', 'servidor_funcao_administrativa_id'],
                    'assessoria_pedagogica_escola_reverso',
                );
            });

            return;
        }

        $this->repairPartialTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('assessoria_pedagogica_escola');
    }

    private function repairPartialTable(): void
    {
        if (! $this->foreignKeyExists('servidor_funcao_administrativa_id')) {
            Schema::table('assessoria_pedagogica_escola', function (Blueprint $table): void {
                $table->foreign('servidor_funcao_administrativa_id', 'fk_assessoria_escola_vinculo')
                    ->references('id')
                    ->on('servidor_funcao_administrativa')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->foreignKeyExists('escola_id')) {
            Schema::table('assessoria_pedagogica_escola', function (Blueprint $table): void {
                $table->foreign('escola_id', 'fk_assessoria_escola_escola')
                    ->references('id')
                    ->on('escolas')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('assessoria_pedagogica_escola', 'assessoria_pedagogica_escola_unique')) {
            Schema::table('assessoria_pedagogica_escola', function (Blueprint $table): void {
                $table->unique(
                    ['servidor_funcao_administrativa_id', 'escola_id'],
                    'assessoria_pedagogica_escola_unique',
                );
            });
        }

        if (! Schema::hasIndex('assessoria_pedagogica_escola', 'assessoria_pedagogica_escola_reverso')) {
            Schema::table('assessoria_pedagogica_escola', function (Blueprint $table): void {
                $table->index(
                    ['escola_id', 'servidor_funcao_administrativa_id'],
                    'assessoria_pedagogica_escola_reverso',
                );
            });
        }
    }

    private function foreignKeyExists(string $column): bool
    {
        return collect(Schema::getForeignKeys('assessoria_pedagogica_escola'))
            ->contains(fn (array $foreign): bool => in_array($column, $foreign['columns'] ?? [], true));
    }
};
