<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AlunoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('pt_BR');

        $turmasCount = Turma::query()->count();
        if ($turmasCount === 0) {
            $this->command?->warn('Nenhuma turma encontrada. Execute o TurmaSeeder antes (ou crie turmas manualmente).');
            return;
        }

        Turma::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($turmas) use ($faker) {
                foreach ($turmas as $turma) {
                    $quantidade = random_int(5, 20);

                    for ($i = 0; $i < $quantidade; $i++) {
                        Aluno::create([
                            'nome' => $faker->name(),
                            'cgm' => $this->gerarCgmUnico((int) $turma->id),
                            'data_nascimento' => $faker->dateTimeBetween('-15 years', '-6 years')->format('Y-m-d'),
                            'id_turma' => (int) $turma->id,
                        ]);
                    }
                }
            });
    }

    private function gerarCgmUnico(int $turmaId): string
    {
        do {
            $cgm = Str::padLeft((string) random_int(0, 99999999), 8, '0') . '-' . $turmaId;
        } while (Aluno::query()->where('cgm', $cgm)->exists());

        return $cgm;
    }
}

