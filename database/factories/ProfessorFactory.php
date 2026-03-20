<?php

namespace Database\Factories;

use App\Models\Escola;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProfessorFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->name();
        $slug = Str::slug($nome, '.');
        $escolaId = Escola::inRandomOrder()->value('id') ?? Escola::factory();

        return [
            'id_escola' => $escolaId,
            'matricula' => fake()->numerify('PROF####'),
            'nome' => $nome,
            'email' => "{$slug}." . fake()->unique()->numberBetween(1, 9999) . "@escola.edu.br",
            'telefone' => fake()->optional(0.8)->numerify('(##) 9####-####'),
        ];
    }

    private function gerarTelefoneBrasileiro(): string
    {
        $ddd = fake()->numberBetween(11, 99);
        $numero = fake()->numberBetween(90000, 99999) . fake()->numberBetween(1000, 9999);
        return "({$ddd}) {$numero}";
    }
}
