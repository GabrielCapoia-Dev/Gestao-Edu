<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table): void {
            if (! Schema::hasColumn('servidores', 'cpf')) {
                $table->string('cpf', 14)->nullable()->unique()->after('id');
            }
        });

        Schema::table('setor', function (Blueprint $table): void {
            if (! Schema::hasColumn('setor', 'contexto')) {
                $table->string('contexto', 20)->default('central')->after('nome');
            }

            if (! Schema::hasColumn('setor', 'exige_vinculo_escola')) {
                $table->boolean('exige_vinculo_escola')->default(false)->after('contexto');
            }
        });

        Schema::table('servidor_funcao_administrativa', function (Blueprint $table): void {
            if (! Schema::hasColumn('servidor_funcao_administrativa', 'matricula')) {
                $table->string('matricula')->nullable()->after('funcao_administrativa_id');
                $table->index('matricula', 'idx_servidor_funcao_matricula');
            }
        });

        if (! Schema::hasColumn('professores', 'servidor_funcao_administrativa_id')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->foreignId('servidor_funcao_administrativa_id')
                    ->nullable()
                    ->after('servidor_id')
                    ->constrained('servidor_funcao_administrativa')
                    ->nullOnDelete();

                $table->index('servidor_funcao_administrativa_id', 'idx_professores_servidor_funcao');
            });
        }

        $this->inferirContextoSetores();
    }

    public function down(): void
    {
        if (Schema::hasColumn('professores', 'servidor_funcao_administrativa_id')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->dropForeign(['servidor_funcao_administrativa_id']);
                $table->dropIndex('idx_professores_servidor_funcao');
                $table->dropColumn('servidor_funcao_administrativa_id');
            });
        }

        Schema::table('servidor_funcao_administrativa', function (Blueprint $table): void {
            if (Schema::hasColumn('servidor_funcao_administrativa', 'matricula')) {
                $table->dropIndex('idx_servidor_funcao_matricula');
                $table->dropColumn('matricula');
            }
        });

        Schema::table('setor', function (Blueprint $table): void {
            foreach (['exige_vinculo_escola', 'contexto'] as $column) {
                if (Schema::hasColumn('setor', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('servidores', function (Blueprint $table): void {
            if (Schema::hasColumn('servidores', 'cpf')) {
                $table->dropUnique(['cpf']);
                $table->dropColumn('cpf');
            }
        });
    }

    private function inferirContextoSetores(): void
    {
        DB::table('setor')
            ->orderBy('id')
            ->get(['id', 'nome'])
            ->each(function ($setor): void {
                $nome = mb_strtolower((string) $setor->nome);
                $contexto = 'central';
                $exigeEscola = false;

                if (str_contains($nome, 'cmei')) {
                    $contexto = 'cmei';
                    $exigeEscola = true;
                } elseif (
                    str_contains($nome, 'escola')
                    || str_contains($nome, 'emef')
                    || str_contains($nome, 'emei')
                    || str_contains($nome, 'ee ')
                    || str_contains($nome, 'unidade escolar')
                ) {
                    $contexto = 'escolar';
                    $exigeEscola = true;
                }

                DB::table('setor')
                    ->where('id', $setor->id)
                    ->update([
                        'contexto' => $contexto,
                        'exige_vinculo_escola' => $exigeEscola,
                    ]);
            });
    }
};