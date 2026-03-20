<?php

namespace Database\Factories;

use App\Models\Escola;
use App\Models\Serie;
use Illuminate\Database\Eloquent\Factories\Factory;

class TurmaFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 1;

        return [
            'codigo' => 'TUR' . str_pad($counter++, 3, '0', STR_PAD_LEFT),
            'nome' => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'turno' => fake()->randomElement(['manha', 'tarde', 'noite']),
            'id_serie' => Serie::inRandomOrder()->value('id') ?? Serie::factory(),
            'id_escola' => Escola::inRandomOrder()->value('id') ?? Escola::factory(),
        ];
    }
}
