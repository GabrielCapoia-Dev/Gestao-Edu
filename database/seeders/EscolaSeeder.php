<?php

namespace Database\Seeders;

use App\Models\Escola;
use App\Models\Setor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EscolaSeeder extends Seeder
{
    // bounding box de Umuarama-PR (se você quiser usar depois)
    private const LAT_MIN = -23.84628485;
    private const LAT_MAX = -23.75628485;
    private const LNG_MIN = -53.40628485;
    private const LNG_MAX = -53.20628485;

    /** Palavras a ignorar no início (prefixos e títulos) */
    private array $prefixos = [
        'escola',
        'municipal',
        'cmei',
        'cei',
        'prof',
        'profa',
        'professor',
        'professora',
        'dr',
        'dra',
    ];

    /** Stopwords (também ignoradas ao escolher palavras úteis) */
    private array $stopwords = ['de', 'da', 'do', 'das', 'dos', 'e', 'dela', 'dele', 'delas', 'deles', 'a', 'o'];

    public function run(): void
    {
        // Lista atualizada que você mandou
        $cmeis = [
            'CMEI - Cecília Meireles',
            'CMEI - Cora Coralina',
            'CMEI - Graciliano Ramos',
            'CMEI - Helena Kolody',
            'CMEI - Professor Ignácio Urbainski',
            'CMEI - Jardim Birigui',
            'CMEI - Madre Paulina',
            'CMEI - Maria Arlete Alves dos Santos',
            'CMEI - Maria Montessori',
            'CMEI - Maria Yokohama Watanabe',
            'CMEI - Nelly Gonçalves',
            'CMEI - Rachel de Queiroz',
            'CMEI - Ranice Benedito de Araujo Teixeira',
            'CMEI - Rubem Alves',
            'CMEI - São Cristóvão',
            'CMEI - São Francisco de Assis',
            'CMEI - São Paulo Apóstolo',
            'CMEI - Tarsila do Amaral',
            'CMEI - Vilmar Silveira',
        ];

        $municipais = [
            'ESCOLA - Analides de Oliveira Caruso',
            'ESCOLA - Benjamin Constant',
            'ESCOLA - Cândido Portinari',
            'ESCOLA - Carlos Gomes',
            'ESCOLA - Dr. Ângelo Moreira da Fonseca',
            'ESCOLA - Dr. Germano Norberto Rudner',
            'ESCOLA - Evangélica',
            'ESCOLA - Jardim União',
            'ESCOLA - Malba Tahan',
            'ESCOLA - Manuel Bandeira',
            'ESCOLA - Maria Augusta Amaral Picelli',
            'ESCOLA - Ouro Branco',
            'ESCOLA - Padre José de Anchieta',
            'ESCOLA - Papa Pio XII',
            'ESCOLA - Paulo Freire',
            'ESCOLA - Rui Barbosa',
            'ESCOLA - São Cristóvão',
            'ESCOLA - São Francisco de Assis',
            'ESCOLA - Sebastião de Mattos',
            'ESCOLA - Senador Souza Naves',
            'ESCOLA - Serra dos Dourados',
            'ESCOLA - Tempo Integral',
            'ESCOLA - Vinicius de Morais',
        ];

        $enderecos = [
            [
                'logradouro' => 'Avenida Paraná',
                'numero' => '4521',
                'bairro' => 'Centro',
                'cep' => '87501-030',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Rua Desembargador Antônio Franco Ferreira da Costa',
                'numero' => '3200',
                'bairro' => 'Zona I',
                'cep' => '87501-120',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Avenida Rio Branco',
                'numero' => '2890',
                'bairro' => 'Zona III',
                'cep' => '87502-210',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Rua Arapongas',
                'numero' => '1100',
                'bairro' => 'Jardim Panorama',
                'cep' => '87505-150',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Rua Bahia',
                'numero' => '780',
                'bairro' => 'Zona VII',
                'cep' => '87503-040',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Rua Londrina',
                'numero' => '1500',
                'bairro' => 'Parque Industrial',
                'cep' => '87507-020',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Avenida Presidente Castelo Branco',
                'numero' => '2100',
                'bairro' => 'Centro Cívico',
                'cep' => '87504-000',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
            [
                'logradouro' => 'Rua Minas Gerais',
                'numero' => '980',
                'bairro' => 'Zona II',
                'cep' => '87502-180',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
            ],
        ];

        $indexEndereco = 0;
        $totalEnderecos = count($enderecos);
        $root = Setor::setorGeral();

        // Mantemos um set em memória para garantir unicidade durante o seed
        $codigosUsados = Escola::pluck('codigo')->filter()->map(fn($c) => Str::upper($c))->all();
        $codigosUsados = array_flip($codigosUsados); // chave = código, valor irrelevante

        // Cadastra municipais (prefixo 'E')
        foreach ($municipais as $nome) {
            $codigo = $this->gerarCodigoUnico($nome, 'E', $codigosUsados);

            $endereco = $enderecos[$indexEndereco % $totalEnderecos];
            $indexEndereco++;

            Escola::updateOrCreate(
                ['nome' => $nome],
                [
                    'codigo' => $codigo,
                    'setor_id' => $this->setorParaEscola($nome, $root)?->id,
                    'email' => Str::slug($codigo) . '@escola.pr.gov.br',
                    'telefone' => '(44) 3621-' . rand(1000, 9999),
                    'logradouro' => $endereco['logradouro'],
                    'numero' => $endereco['numero'],
                    'bairro' => $endereco['bairro'],
                    'cep' => $endereco['cep'],
                    'cidade' => $endereco['cidade'],
                    'estado' => $endereco['estado'],
                    'complemento' => null,
                    'ativo' => true,
                ]
            );

            $codigosUsados[$codigo] = true;
        }

        // Cadastra CMEIs (prefixo 'C')
        foreach ($cmeis as $nome) {
            $codigo = $this->gerarCodigoUnico($nome, 'C', $codigosUsados);

            Escola::updateOrCreate(
                ['nome' => $nome],
                [
                    'codigo' => $codigo,
                    'setor_id' => $this->setorParaEscola($nome, $root)?->id,
                    'email' => Str::slug($codigo) . '@escola.pr.gov.br',
                    'telefone' => '(44) 3621-' . rand(1000, 9999),
                    'logradouro' => $endereco['logradouro'],
                    'numero' => $endereco['numero'],
                    'bairro' => $endereco['bairro'],
                    'cep' => $endereco['cep'],
                    'cidade' => $endereco['cidade'],
                    'estado' => $endereco['estado'],
                    'complemento' => null,
                    'ativo' => true,
                ]
            );

            $codigosUsados[$codigo] = true;
        }
    }

    /**
     * Gera um código único conforme regras e resolve colisão:
     * base = PREFIXO + inicial1 + inicial2
     * se colidir, tenta incluir inicial3, depois inicial4...
     * por fim, se necessário, tenta sufixos numéricos 2..9.
     */
    private function gerarCodigoUnico(string $nome, string $prefixo, array $codigosUsados): string
    {
        [$iniciais, $palavrasUteis] = $this->iniciaisUteis($nome);

        // monta base: prefixo + 2 primeiras iniciais
        $codigo = $prefixo . ($iniciais[0] ?? '') . ($iniciais[1] ?? '');
        $codigo = Str::upper($codigo);

        if (! isset($codigosUsados[$codigo]) && ! Escola::where('codigo', $codigo)->exists()) {
            return $codigo;
        }

        // tenta acrescentar 3ª, 4ª, ... iniciais
        for ($i = 2; $i < count($iniciais); $i++) {
            $alt = $codigo . Str::upper($iniciais[$i]);
            if (! isset($codigosUsados[$alt]) && ! Escola::where('codigo', $alt)->exists()) {
                return $alt;
            }
            $codigo = $alt; // acumula (E + A + B + C, se precisar)
        }

        // fallback numérico se acabaram as iniciais
        for ($n = 2; $n <= 9; $n++) {
            $alt = $codigo . $n;
            if (! isset($codigosUsados[$alt]) && ! Escola::where('codigo', $alt)->exists()) {
                return $alt;
            }
        }

        // último recurso (improvável): hash curto
        return $codigo . substr(Str::upper(Str::random(2)), 0, 2);
    }

    private function setorParaEscola(string $nome, ?Setor $root): ?Setor
    {
        if (! $root) {
            return null;
        }

        return Setor::query()->firstOrCreate(
            [
                'parent_id' => $root->id,
                'nome' => $nome,
            ],
            [
                'status' => 'Ativo',
                'ativo' => true,
                'recebe_pedidos_iniciais' => false,
                'encaminha_pedido_para_setor_ids' => [],
                'alterado_por' => 'Seeder',
            ]
        );
    }

    /**
     * Retorna [iniciais[], palavrasÚteis[]] a partir do nome:
     * - remove prefixos (Escola/Municipal/CMEI/CEI, Prof/Dr etc.)
     * - remove stopwords (de/da/do/das/dos/e ...)
     * - remove acentos e normaliza
     */
    private function iniciaisUteis(string $nome): array
    {
        // normaliza espaços e acentos
        $clean = trim(preg_replace('/\s+/', ' ', $nome));
        $semAcento = Str::ascii($clean);

        $tokens = collect(explode(' ', $semAcento))
            ->map(fn($t) => mb_strtolower(trim($t)))
            ->filter(fn($t) => $t !== '');

        // remove prefixos do começo (enquanto existirem)
        while ($tokens->isNotEmpty() && in_array($tokens->first(), $this->prefixos, true)) {
            $tokens->shift();
        }

        // remove stopwords internas
        $uteis = $tokens->filter(fn($t) => ! in_array($t, $this->stopwords, true))->values();

        // se ficar vazio (caso extremo), usa os tokens originais mesmo
        if ($uteis->isEmpty()) {
            $uteis = $tokens->values();
        }

        // gera iniciais
        $iniciais = $uteis->map(function ($t) {
            // pega primeira letra alfabética
            if (preg_match('/[a-z]/i', $t, $m)) {
                return mb_substr($t, 0, 1);
            }

            return '';
        })
            ->filter()
            ->values()
            ->all();

        return [$iniciais, $uteis->all()];
    }
}
