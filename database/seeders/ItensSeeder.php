<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;

class ItensSeeder extends Seeder
{
    public function run(): void
    {
        $itens = [
            // ─── CEREAIS E DERIVADOS ─────────────────────────────────────
            ['nome' => 'Arroz integral',              'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Arroz tipo 1',                'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Arroz tipo 2',                'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Aveia em flocos',             'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Biscoito maisena',            'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Biscoito recheado chocolate', 'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Pastel de carne',             'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Pastel de queijo',            'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Massa de pastel',             'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Pipoca',                      'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Polenta',                     'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],
            ['nome' => 'Torrada',                     'tipo_item' => 'cereal_derivado', 'unidade_medida' => 'kg'],

            // ─── VERDURAS E HORTALIÇAS ────────────────────────────────────
            ['nome' => 'Abóbora cabotiã',    'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Abóbora menina',     'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Abóbora moranga',    'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Abóbora pescoço',    'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Abobrinha italiana', 'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Jurubeba',           'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Mandioca',           'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Farofa de mandioca', 'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Manjericão',         'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Maxixe',             'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Mostarda',           'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Seleta de legumes',  'tipo_item' => 'hortalica', 'unidade_medida' => 'lt'],
            ['nome' => 'Serralha',           'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Molho de tomate',    'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Purê de tomate',     'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],
            ['nome' => 'Vagem',              'tipo_item' => 'hortalica', 'unidade_medida' => 'kg'],

            // ─── FRUTAS E DERIVADOS ───────────────────────────────────────
            ['nome' => 'Abacate',              'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Abacaxi',              'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Banana da terra',      'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Banana figo',          'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Banana maçã',          'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Banana nanica',        'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Banana ouro',          'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Banana prata',         'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Pêra',                 'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Pêssego',              'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Tangerina poncã',      'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Uva itália',           'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],
            ['nome' => 'Uva rubi',             'tipo_item' => 'fruta', 'unidade_medida' => 'kg'],

            // ─── PESCADOS ─────────────────────────────────────────────────
            ['nome' => 'Abadejo',      'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Atum em óleo', 'tipo_item' => 'pescado', 'unidade_medida' => 'lt'],
            ['nome' => 'Atum fresco',  'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Bacalhau',     'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Pescada',      'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Pintado',      'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Salmão',       'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Sardinha',     'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],
            ['nome' => 'Sardinha em óleo', 'tipo_item' => 'pescado', 'unidade_medida' => 'lt'],
            ['nome' => 'Tucunaré',     'tipo_item' => 'pescado', 'unidade_medida' => 'kg'],

            // ─── CARNES BOVINAS ───────────────────────────────────────────
            ['nome' => 'Apresuntado',     'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Caldo de carne',  'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Acém bovino',     'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Almôndega',       'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Músculo bovino',  'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Paleta bovina',   'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Carne seca',      'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Croquete de carne', 'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Hambúrguer',      'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Quibe',           'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],
            ['nome' => 'Salame',          'tipo_item' => 'carne_bovina', 'unidade_medida' => 'kg'],

            // ─── AVES ─────────────────────────────────────────────────────
            ['nome' => 'Caldo de galinha',  'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Coxinha de frango', 'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Empada de frango',  'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Asa de frango',     'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Frango caipira',    'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Coração de frango', 'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Coxa de frango',    'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Fígado de frango',  'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Filé de frango',    'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Frango inteiro',    'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Peito de frango',   'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Sobrecoxa de frango', 'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Linguiça de frango', 'tipo_item' => 'ave', 'unidade_medida' => 'kg'],
            ['nome' => 'Peru',              'tipo_item' => 'ave', 'unidade_medida' => 'kg'],

            // ─── CARNES SUÍNAS ────────────────────────────────────────────
            ['nome' => 'Linguiça de porco', 'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Mortadela',         'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Bisteca suína',     'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Costela suína',     'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Lombo suíno',       'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Orelha suína',      'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Pernil suíno',      'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Rabo suíno',        'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Presunto',          'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],
            ['nome' => 'Toucinho',          'tipo_item' => 'carne_suina', 'unidade_medida' => 'kg'],

            // ─── LEITE E DERIVADOS ────────────────────────────────────────
            ['nome' => 'Creme de leite',         'tipo_item' => 'laticinios', 'unidade_medida' => 'kg'],
            ['nome' => 'Iogurte natural',        'tipo_item' => 'laticinios', 'unidade_medida' => 'kg'],
            ['nome' => 'Iogurte desnatado',      'tipo_item' => 'laticinios', 'unidade_medida' => 'kg'],
            ['nome' => 'Leite condensado',       'tipo_item' => 'laticinios', 'unidade_medida' => 'lt'],
            ['nome' => 'Queijo prato',           'tipo_item' => 'laticinios', 'unidade_medida' => 'kg'],
            ['nome' => 'Requeijão cremoso',      'tipo_item' => 'laticinios', 'unidade_medida' => 'kg'],
            ['nome' => 'Ricota',                 'tipo_item' => 'laticinios', 'unidade_medida' => 'kg'],

            // ─── BEBIDAS ──────────────────────────────────────────────────
            ['nome' => 'Café',              'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],
            ['nome' => 'Chá mate',          'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],
            ['nome' => 'Água de coco',      'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],
            ['nome' => 'Refrigerante cola', 'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],
            ['nome' => 'Refrigerante guaraná', 'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],
            ['nome' => 'Refrigerante laranja', 'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],
            ['nome' => 'Refrigerante limão', 'tipo_item' => 'bebida', 'unidade_medida' => 'lt'],

            // ─── INDUSTRIALIZADOS/PREPARADOS ──────────────────────────────
            ['nome' => 'Azeitona verde',        'tipo_item' => 'industrializado', 'unidade_medida' => 'lt'],
            ['nome' => 'Milho verde',        'tipo_item' => 'industrializado', 'unidade_medida' => 'lt'],
            ['nome' => 'Leite de coco',         'tipo_item' => 'industrializado', 'unidade_medida' => 'kg'],
            ['nome' => 'Maionese',              'tipo_item' => 'industrializado', 'unidade_medida' => 'kg'],
            ['nome' => 'Tapioca',               'tipo_item' => 'industrializado', 'unidade_medida' => 'kg'],

        ];

        foreach ($itens as $item) {
            Item::firstOrCreate(
                ['nome' => $item['nome']],
                [
                    'tipo_item'      => $item['tipo_item'],
                    'unidade_medida' => $item['unidade_medida'],
                    'ativo'          => true,
                ]
            );
        }
    }
}
