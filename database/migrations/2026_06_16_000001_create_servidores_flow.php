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
        if (! Schema::hasTable('servidores')) {
            Schema::create('servidores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('id_escola')->nullable()->constrained('escolas')->nullOnDelete();
                $table->foreignId('setor_id')->nullable()->constrained('setor')->nullOnDelete();
                $table->string('matricula')->nullable();
                $table->string('nome');
                $table->string('email')->nullable();
                $table->string('telefone')->nullable();
                $table->string('status')->default('ativo');
                $table->text('observacoes')->nullable();
                $table->timestamps();

                $table->index(['id_escola', 'status']);
                $table->index(['setor_id', 'status']);
                $table->index(['user_id', 'status']);
                $table->index('matricula');
            });
        }

        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            if (! Schema::hasColumn('funcao_administrativa', 'codigo')) {
                $table->string('codigo')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('funcao_administrativa', 'categoria')) {
                $table->string('categoria')->default('geral')->after('nome');
            }

            if (! Schema::hasColumn('funcao_administrativa', 'ativo')) {
                $table->boolean('ativo')->default(true)->after('categoria');
            }

            if (! Schema::hasColumn('funcao_administrativa', 'exige_professor')) {
                $table->boolean('exige_professor')->default(false)->after('ativo');
            }
        });

        if (! Schema::hasColumn('professores', 'servidor_id')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->foreignId('servidor_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('servidores')
                    ->nullOnDelete();

                $table->index('servidor_id');
            });
        }

        if (! Schema::hasTable('servidor_funcao_administrativa')) {
            Schema::create('servidor_funcao_administrativa', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
                $table->foreignId('funcao_administrativa_id')->constrained('funcao_administrativa')->cascadeOnDelete();
                $table->foreignId('id_escola')->nullable()->constrained('escolas')->nullOnDelete();
                $table->foreignId('setor_id')->nullable()->constrained('setor')->nullOnDelete();
                $table->string('status')->default('ativo');
                $table->string('origem')->default('manual');
                $table->string('portaria')->nullable();
                $table->date('data_inicio')->nullable();
                $table->date('data_fim')->nullable();
                $table->timestamps();

                $table->index(['servidor_id', 'status'], 'idx_servidor_funcao_servidor_status');
                $table->index(['funcao_administrativa_id', 'status'], 'idx_servidor_funcao_funcao_status');
                $table->index(['id_escola', 'status'], 'idx_servidor_funcao_escola_status');
                $table->index(['setor_id', 'status'], 'idx_servidor_funcao_setor_status');
            });
        }

        $this->normalizarCodigosDasFuncoes();
        $professorFuncaoId = $this->garantirFuncaoProfessor();
        $this->backfillProfessores($professorFuncaoId);
    }

    public function down(): void
    {
        Schema::dropIfExists('servidor_funcao_administrativa');

        if (Schema::hasColumn('professores', 'servidor_id')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->dropForeign(['servidor_id']);
                $table->dropIndex(['servidor_id']);
                $table->dropColumn('servidor_id');
            });
        }

        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            foreach (['exige_professor', 'ativo', 'categoria', 'codigo'] as $column) {
                if (Schema::hasColumn('funcao_administrativa', $column)) {
                    $column === 'codigo'
                        ? $table->dropUnique(['codigo'])
                        : null;
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('servidores');
    }

    private function normalizarCodigosDasFuncoes(): void
    {
        $funcoes = DB::table('funcao_administrativa')
            ->orderBy('id')
            ->get(['id', 'nome', 'codigo']);

        $usados = $funcoes
            ->pluck('codigo')
            ->filter()
            ->map(fn (string $codigo): string => Str::lower($codigo))
            ->values()
            ->all();

        foreach ($funcoes as $funcao) {
            DB::table('funcao_administrativa')
                ->where('id', $funcao->id)
                ->whereNull('categoria')
                ->update(['categoria' => 'equipe_gestora']);

            if (filled($funcao->codigo)) {
                continue;
            }

            $base = Str::slug((string) $funcao->nome) ?: 'funcao';
            $codigo = $base;

            if (in_array(Str::lower($codigo), $usados, true)) {
                $codigo = "{$base}-{$funcao->id}";
            }

            $usados[] = Str::lower($codigo);

            DB::table('funcao_administrativa')
                ->where('id', $funcao->id)
                ->update([
                    'codigo' => $codigo,
                    'categoria' => 'equipe_gestora',
                    'ativo' => true,
                    'exige_professor' => false,
                ]);
        }
    }

    private function garantirFuncaoProfessor(): int
    {
        $agora = now();

        $existente = DB::table('funcao_administrativa')
            ->where('codigo', 'professor')
            ->orWhere('nome', 'Professor')
            ->first();

        if ($existente) {
            DB::table('funcao_administrativa')
                ->where('id', $existente->id)
                ->update([
                    'codigo' => 'professor',
                    'nome' => 'Professor',
                    'categoria' => 'pedagogico',
                    'ativo' => true,
                    'exige_professor' => true,
                    'updated_at' => $agora,
                ]);

            return (int) $existente->id;
        }

        return (int) DB::table('funcao_administrativa')->insertGetId([
            'codigo' => 'professor',
            'nome' => 'Professor',
            'categoria' => 'pedagogico',
            'ativo' => true,
            'exige_professor' => true,
            'tem_relacao_turma' => false,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }

    private function backfillProfessores(int $professorFuncaoId): void
    {
        $agora = now();

        DB::table('professores as p')
            ->leftJoin('escolas as e', 'e.id', '=', 'p.id_escola')
            ->select([
                'p.id',
                'p.user_id',
                'p.servidor_id',
                'p.id_escola',
                'p.matricula',
                'p.nome',
                'p.email',
                'p.telefone',
                'p.funcao_administrativa_id',
                'p.portaria',
                'e.setor_id as escola_setor_id',
            ])
            ->orderBy('p.id')
            ->chunk(200, function ($professores) use ($agora, $professorFuncaoId): void {
                foreach ($professores as $professor) {
                    $servidorId = $this->servidorIdParaProfessor($professor, $agora);

                    DB::table('professores')
                        ->where('id', $professor->id)
                        ->update([
                            'servidor_id' => $servidorId,
                            'updated_at' => $agora,
                        ]);

                    $this->garantirVinculoFuncao(
                        servidorId: $servidorId,
                        funcaoId: $professorFuncaoId,
                        escolaId: $professor->id_escola ? (int) $professor->id_escola : null,
                        setorId: $professor->escola_setor_id ? (int) $professor->escola_setor_id : null,
                        origem: 'professor',
                        portaria: null,
                        agora: $agora,
                    );

                    if ($professor->funcao_administrativa_id) {
                        $this->garantirVinculoFuncao(
                            servidorId: $servidorId,
                            funcaoId: (int) $professor->funcao_administrativa_id,
                            escolaId: $professor->id_escola ? (int) $professor->id_escola : null,
                            setorId: $professor->escola_setor_id ? (int) $professor->escola_setor_id : null,
                            origem: 'professor_legacy',
                            portaria: $professor->portaria,
                            agora: $agora,
                        );
                    }
                }
            });
    }

    private function servidorIdParaProfessor(object $professor, mixed $agora): int
    {
        $payload = [
            'user_id' => $professor->user_id ? (int) $professor->user_id : null,
            'id_escola' => $professor->id_escola ? (int) $professor->id_escola : null,
            'setor_id' => $professor->escola_setor_id ? (int) $professor->escola_setor_id : null,
            'matricula' => $professor->matricula,
            'nome' => $professor->nome,
            'email' => $professor->email,
            'telefone' => $professor->telefone,
            'status' => 'ativo',
            'updated_at' => $agora,
        ];

        if ($professor->servidor_id && DB::table('servidores')->where('id', $professor->servidor_id)->exists()) {
            DB::table('servidores')
                ->where('id', $professor->servidor_id)
                ->update($payload);

            return (int) $professor->servidor_id;
        }

        $payload['created_at'] = $agora;

        return (int) DB::table('servidores')->insertGetId($payload);
    }

    private function garantirVinculoFuncao(
        int $servidorId,
        int $funcaoId,
        ?int $escolaId,
        ?int $setorId,
        string $origem,
        ?string $portaria,
        mixed $agora,
    ): void {
        $existente = DB::table('servidor_funcao_administrativa')
            ->where('servidor_id', $servidorId)
            ->where('funcao_administrativa_id', $funcaoId)
            ->where('origem', $origem)
            ->where('status', 'ativo')
            ->first();

        $payload = [
            'id_escola' => $escolaId,
            'setor_id' => $setorId,
            'status' => 'ativo',
            'portaria' => $portaria,
            'data_fim' => null,
            'updated_at' => $agora,
        ];

        if ($existente) {
            DB::table('servidor_funcao_administrativa')
                ->where('id', $existente->id)
                ->update($payload);

            return;
        }

        DB::table('servidor_funcao_administrativa')->insert([
            ...$payload,
            'servidor_id' => $servidorId,
            'funcao_administrativa_id' => $funcaoId,
            'origem' => $origem,
            'created_at' => $agora,
        ]);
    }
};
