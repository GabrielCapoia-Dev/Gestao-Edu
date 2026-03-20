<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SerieFactory extends Factory
{
    protected static int $index = 0;

    public function definition(): array
    {
        $series = [
            ['codigo' => 'SER001', 'nome' => 'Berçário'],
            ['codigo' => 'SER002', 'nome' => 'Infantil 1'],
            ['codigo' => 'SER003', 'nome' => 'Infantil 2'],
            ['codigo' => 'SER004', 'nome' => 'Infantil 3'],
            ['codigo' => 'SER005', 'nome' => 'Infantil 4'],
            ['codigo' => 'SER005', 'nome' => 'Infantil 5'],
            ['codigo' => 'SER006', 'nome' => '1º Ano'],
            ['codigo' => 'SER007', 'nome' => '2º Ano'],
            ['codigo' => 'SER008', 'nome' => '3º Ano'],
            ['codigo' => 'SER009', 'nome' => '4º Ano'],
            ['codigo' => 'SER010', 'nome' => '5º Ano'],
        ];

        return $series[self::$index++];
    }
}
