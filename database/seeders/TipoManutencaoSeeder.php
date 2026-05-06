<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;

class TipoManutencaoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nome' => 'Elétrica Interna', 'descricao' => 'Problemas elétricos gerais'],
            ['nome' => 'Elétrica Externa',  'descricao' => 'Instalações e rede elétrica'],
            ['nome' => 'Hidráulica',        'descricao' => 'Vazamentos e encanamentos'],
            ['nome' => 'Pintura',           'descricao' => 'Pintura em geral'],
            ['nome' => 'Telhado',           'descricao' => 'Reparos em telhado'],
            ['nome' => 'Ar-condicionado',   'descricao' => 'Manutenção de ar-condicionado'],
            ['nome' => 'Infileração',       'descricao' => 'Infiltrações e umidade'],
            ['nome' => 'Reforma',           'descricao' => 'Reformas em geral'],
            ['nome' => 'Limpeza de Calhas', 'descricao' => 'Limpeza e desobstrução de calhas'],
        ];

        foreach ($tipos as $tipo) {
            $tipoManutencao = TipoManutencao::firstOrCreate(
                ['nome' => $tipo['nome']],
                [
                    'descricao'    => $tipo['descricao'],
                    'ativo'        => true,
                    'alterado_por' => 'Seeder',
                ]
            );

            foreach ($this->opcoesParaTipo($tipo['nome']) as $texto) {
                TipoManutencaoOpcao::firstOrCreate(
                    [
                        'tipo_manutencao_id' => $tipoManutencao->id,
                        'texto' => $texto,
                    ],
                    [
                        'ativo' => true,
                        'alterado_por' => 'Seeder',
                    ]
                );
            }
        }
    }

    private function opcoesParaTipo(string $nome): array
    {
        return match ($nome) {
            'ElÃ©trica Interna', 'ElÃ©trica Externa' => [
                'Sem luz em uma sala',
                'Sem luz na unidade',
                'Disjuntor queimado',
                'Cheiro de queimado no quadro de energia',
            ],
            'HidrÃ¡ulica' => [
                'Torneira vazando',
                'Banheiro sem Ã¡gua',
                'Cano rompido',
                'Caixa d\'Ã¡gua com problema',
            ],
            'Pintura' => [
                'Parede descascando',
                'Sala precisa de pintura',
                'Pintura externa danificada',
            ],
            'Telhado' => [
                'Goteira em sala',
                'Telha quebrada',
                'Forro danificado por chuva',
            ],
            default => [
                'ServiÃ§o corretivo',
                'ServiÃ§o preventivo',
                'AvaliaÃ§Ã£o tÃ©cnica necessÃ¡ria',
            ],
        };
    }
}
