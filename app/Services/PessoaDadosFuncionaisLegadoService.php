<?php

namespace App\Services;

use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PessoaDadosFuncionaisLegadoService
{
    /**
     * @return array{analisadas:int,elegiveis:int,normalizadas:int,ambiguas:int,sem_matriculas:int}
     */
    public function executar(bool $aplicar = false, ?string $email = null): array
    {
        $stats = [
            'analisadas' => 0,
            'elegiveis' => 0,
            'normalizadas' => 0,
            'ambiguas' => 0,
            'sem_matriculas' => 0,
        ];

        if (! Schema::hasTable('servidores')
            || ! Schema::hasTable('professor_matriculas')
            || ! Schema::hasColumn('servidores', 'carga_horaria')
            || ! Schema::hasColumn('servidores', 'jornada')) {
            return $stats;
        }

        $email = Pessoa::normalizarEmail($email);

        Pessoa::withTrashed()
            ->with('matriculas:id,servidor_id,matricula,turno')
            ->where(function (Builder $pendentes): void {
                $pendentes->whereNull('carga_horaria')->orWhereNull('jornada');
            })
            ->when($email, fn (Builder $query): Builder => $query->where('email_normalizado', $email))
            ->orderBy('id')
            ->chunkById(100, function ($pessoas) use ($aplicar, &$stats): void {
                foreach ($pessoas as $pessoa) {
                    $stats['analisadas']++;
                    $inferidos = $this->inferir($pessoa->matriculas->all());

                    if ($inferidos === null) {
                        $pessoa->matriculas->isEmpty()
                            ? $stats['sem_matriculas']++
                            : $stats['ambiguas']++;

                        continue;
                    }

                    if (($pessoa->carga_horaria !== null && (int) $pessoa->carga_horaria !== $inferidos['carga_horaria'])
                        || ($pessoa->jornada !== null && (bool) $pessoa->jornada !== $inferidos['jornada'])) {
                        $stats['ambiguas']++;

                        continue;
                    }

                    $payload = [];
                    if ($pessoa->carga_horaria === null) {
                        $payload['carga_horaria'] = $inferidos['carga_horaria'];
                    }
                    if ($pessoa->jornada === null) {
                        $payload['jornada'] = $inferidos['jornada'];
                    }

                    if ($payload === []) {
                        continue;
                    }

                    $stats['elegiveis']++;

                    if (! $aplicar) {
                        continue;
                    }

                    $payload['updated_at'] = now();
                    DB::table('servidores')->where('id', $pessoa->id)->update($payload);
                    $stats['normalizadas']++;
                }
            });

        return $stats;
    }

    /**
     * @param  array<int, mixed>  $matriculas
     * @return array{carga_horaria:int,jornada:bool}|null
     */
    private function inferir(array $matriculas): ?array
    {
        $itens = collect($matriculas)
            ->map(fn ($matricula): array => [
                'matricula' => mb_strtolower(trim((string) $matricula->matricula)),
                'turno' => (string) $matricula->turno,
            ])
            ->values();

        if ($itens->count() === 1) {
            $turno = $itens->first()['turno'];

            if ($turno === 'integral') {
                return ['carga_horaria' => Pessoa::CARGA_HORARIA_40, 'jornada' => false];
            }

            if (in_array($turno, ['manha', 'tarde'], true)) {
                return ['carga_horaria' => Pessoa::CARGA_HORARIA_20, 'jornada' => false];
            }

            return null;
        }

        $turnos = $itens->pluck('turno')->sort()->values()->all();
        $numeros = $itens->pluck('matricula')->filter();

        if ($itens->count() === 2
            && $turnos === ['manha', 'tarde']
            && $numeros->count() === 2
            && $numeros->unique()->count() === 2) {
            return ['carga_horaria' => Pessoa::CARGA_HORARIA_20, 'jornada' => true];
        }

        return null;
    }
}
