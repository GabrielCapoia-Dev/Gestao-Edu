<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escola_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'escola_id'], 'uniq_escola_user');
        });

        $agora = now();

        $vinculosPorUsuario = DB::table('users')
            ->whereNotNull('id_escola')
            ->select('id as user_id', 'id_escola as escola_id')
            ->get()
            ->map(fn ($item): array => [
                'user_id' => (int) $item->user_id,
                'escola_id' => (int) $item->escola_id,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])
            ->all();

        if ($vinculosPorUsuario !== []) {
            DB::table('escola_user')->upsert(
                $vinculosPorUsuario,
                ['user_id', 'escola_id'],
                ['updated_at']
            );
        }

        $vinculosPedagogicos = DB::table('turma_componente_professor as tcp')
            ->join('professores as p', 'p.id', '=', 'tcp.professor_id')
            ->join('turmas as t', 't.id', '=', 'tcp.turma_id')
            ->whereNotNull('p.user_id')
            ->where('tcp.tem_professor', true)
            ->select('p.user_id', 't.id_escola as escola_id')
            ->distinct()
            ->get()
            ->map(fn ($item): array => [
                'user_id' => (int) $item->user_id,
                'escola_id' => (int) $item->escola_id,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])
            ->all();

        if ($vinculosPedagogicos !== []) {
            DB::table('escola_user')->upsert(
                $vinculosPedagogicos,
                ['user_id', 'escola_id'],
                ['updated_at']
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('escola_user');
    }
};
