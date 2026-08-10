<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            if (! Schema::hasColumn('funcao_administrativa', 'secretaria_escolar')) {
                $table->boolean('secretaria_escolar')
                    ->default(false)
                    ->after('coordenacao_pedagogica');
            }
        });

        Schema::table('servidor_funcao_administrativa', function (Blueprint $table): void {
            if (! Schema::hasColumn('servidor_funcao_administrativa', 'principal')) {
                $table->boolean('principal')
                    ->default(false)
                    ->after('portaria');
                $table->index(
                    ['id_escola', 'principal', 'status'],
                    'idx_sfa_escola_principal_status',
                );
            }
        });

        if (! Schema::hasTable('servidor_funcao_turma')) {
            return;
        }

        Schema::table('servidor_funcao_turma', function (Blueprint $table): void {
            if (! Schema::hasColumn('servidor_funcao_turma', 'principal')) {
                $table->boolean('principal')->default(false)->after('turma_id');
            }

            if (! Schema::hasColumn('servidor_funcao_turma', 'status')) {
                $table->string('status')->default('ativo')->after('principal');
            }

            if (! Schema::hasColumn('servidor_funcao_turma', 'data_inicio')) {
                $table->date('data_inicio')->nullable()->after('status');
            }

            if (! Schema::hasColumn('servidor_funcao_turma', 'data_fim')) {
                $table->date('data_fim')->nullable()->after('data_inicio');
            }
        });

        // Este índice também sustenta a FK e deve existir antes da remoção do UNIQUE.
        if (! $this->indexExists('servidor_funcao_turma', 'idx_sft_vinculo_turma_status')) {
            Schema::table('servidor_funcao_turma', function (Blueprint $table): void {
                $table->index(
                    ['servidor_funcao_administrativa_id', 'turma_id', 'status'],
                    'idx_sft_vinculo_turma_status',
                );
            });
        }

        // A restrição antiga impedia manter mais de um período histórico para
        // a mesma coordenação/turma. A unicidade de vínculos ativos é garantida
        // transacionalmente pelo serviço de domínio.
        if ($this->indexExists('servidor_funcao_turma', 'servidor_funcao_turma_unique')) {
            Schema::table('servidor_funcao_turma', function (Blueprint $table): void {
                $table->dropUnique('servidor_funcao_turma_unique');
            });
        }

        if (! $this->indexExists('servidor_funcao_turma', 'idx_sft_turma_principal_status')) {
            Schema::table('servidor_funcao_turma', function (Blueprint $table): void {
                $table->index(
                    ['turma_id', 'principal', 'status'],
                    'idx_sft_turma_principal_status',
                );
            });
        }

        DB::table('servidor_funcao_turma')
            ->whereNull('status')
            ->update(['status' => 'ativo']);
    }

    public function down(): void
    {
        if (Schema::hasTable('servidor_funcao_turma')) {
            Schema::table('servidor_funcao_turma', function (Blueprint $table): void {
                if ($this->indexExists('servidor_funcao_turma', 'idx_sft_vinculo_turma_status')) {
                    $table->dropIndex('idx_sft_vinculo_turma_status');
                }

                if ($this->indexExists('servidor_funcao_turma', 'idx_sft_turma_principal_status')) {
                    $table->dropIndex('idx_sft_turma_principal_status');
                }

                foreach (['data_fim', 'data_inicio', 'status', 'principal'] as $column) {
                    if (Schema::hasColumn('servidor_funcao_turma', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            $temDuplicados = DB::table('servidor_funcao_turma')
                ->select('servidor_funcao_administrativa_id', 'turma_id')
                ->groupBy('servidor_funcao_administrativa_id', 'turma_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if (! $temDuplicados && ! $this->indexExists('servidor_funcao_turma', 'servidor_funcao_turma_unique')) {
                Schema::table('servidor_funcao_turma', function (Blueprint $table): void {
                    $table->unique(
                        ['servidor_funcao_administrativa_id', 'turma_id'],
                        'servidor_funcao_turma_unique',
                    );
                });
            }
        }

        Schema::table('servidor_funcao_administrativa', function (Blueprint $table): void {
            if ($this->indexExists('servidor_funcao_administrativa', 'idx_sfa_escola_principal_status')) {
                $table->dropIndex('idx_sfa_escola_principal_status');
            }

            if (Schema::hasColumn('servidor_funcao_administrativa', 'principal')) {
                $table->dropColumn('principal');
            }
        });

        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            if (Schema::hasColumn('funcao_administrativa', 'secretaria_escolar')) {
                $table->dropColumn('secretaria_escolar');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $item): bool => ($item['name'] ?? null) === $index);
    }
};
