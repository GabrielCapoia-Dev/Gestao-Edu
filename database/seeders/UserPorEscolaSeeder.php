<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Escola;
use Illuminate\Support\Str;

class UserPorEscolaSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('pt_BR');

        $escolas = Escola::all();

        foreach ($escolas as $escola) {

            // Evita criar duplicado para mesma escola
            if (User::where('id_escola', $escola->id)->exists()) {
                continue;
            }

            User::create([
                'id_escola' => $escola->id,
                'name' => $faker->name(),
                'email' => Str::slug($faker->unique()->userName()) . '@teste.com',
                'password' => 'Senha@123', // será hasheado automaticamente pelo cast
                'email_approved' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}