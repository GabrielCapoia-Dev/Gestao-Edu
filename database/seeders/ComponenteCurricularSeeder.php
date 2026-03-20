<?php

namespace Database\Seeders;

use App\Models\ComponenteCurricular;
use App\Models\Serie;
use Illuminate\Database\Seeder;

class ComponenteCurricularSeeder extends Seeder
{
    public function run(): void
    {
        $componentesBase = [
            'portugues' => 'Português',
            'matematica' => 'Matemática',
            'historia' => 'História',
            'geografia' => 'Geografia',
            'ciencias' => 'Ciências',
            'arte_ensino_religioso' => 'Arte e Ensino Religioso',
            'educacao_fisica' => 'Educação Física',
            'ingles' => 'Inglês',
            'corpo_gesto_movimento' => 'Corpo, Gesto e Movimento',
            'eu_outro_nos' => 'O Eu, O Outro e o Nós',
            'escuta_fala_pensamento' => 'Escuta, fala, pensamento e imaginação',
            'tracos_sons_cores' => 'Traços, sons, cores e formas',
            'espaco_tempo_quantidade' => 'Espaços, tempos, quantidades, relações e transformações',
            'acompanhamento_pedagogico_portugues' => 'Acom. Ped. de Língua Port.',
            'acompanhamento_pedagogico_matematica' => 'Acom. Ped. de Matemática',
            'teatro_danca_musica' => 'Laboratório de Teatro, Dança e Música',
            'atividades_esportivas' => 'Atividades Esportivas',
            'recreacao_jogos' => 'Recreação e Jogos',
        ];

        $componentes = [];

        foreach ($componentesBase as $codigo => $nome) {
            $componentes[$codigo] = ComponenteCurricular::firstOrCreate([
                'codigo' => $codigo,
                'nome' => $nome,
            ]);
        }

        // Educação Infantil
        $infantilSeries = Serie::whereIn('nome', [
            'INFANTIL 1',
            'INFANTIL 2',
            'INFANTIL 3',
            'INFANTIL 4',
            'INFANTIL 5',
            'BERÇÁRIO',
        ])->get();
        // Base

        $turmaBase = Serie::whereIn('nome', [
            '1º Ano - Base',
            '2º Ano - Base',
            '3º Ano - Base',
            '4º Ano - Base',
            '5º Ano - Base',
        ])->get();

        foreach ($turmaBase as $serie) {
            $serie->componentesCurriculares()->sync([
                $componentes['acompanhamento_pedagogico_portugues']->id,
                $componentes['acompanhamento_pedagogico_matematica']->id,
                $componentes['teatro_danca_musica']->id,
                $componentes['atividades_esportivas']->id,
                $componentes['recreacao_jogos']->id,
            ]);
        }

        foreach ($infantilSeries as $serie) {
            $serie->componentesCurriculares()->sync([
                $componentes['corpo_gesto_movimento']->id,
                $componentes['tracos_sons_cores']->id,
                $componentes['eu_outro_nos']->id,
                $componentes['escuta_fala_pensamento']->id,
                $componentes['espaco_tempo_quantidade']->id,
            ]);
        }

        // Ensino Fundamental
        $fundamentalSeries = Serie::where('nome', 'like', '%Ano')->get();

        foreach ($fundamentalSeries as $serie) {
            $serie->componentesCurriculares()->sync([
                $componentes['portugues']->id,
                $componentes['matematica']->id,
                $componentes['historia']->id,
                $componentes['geografia']->id,
                $componentes['ciencias']->id,
                $componentes['educacao_fisica']->id,
                $componentes['arte_ensino_religioso']->id,
            ]);
        }
    }
}
