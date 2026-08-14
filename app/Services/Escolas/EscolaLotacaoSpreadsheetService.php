<?php

namespace App\Services\Escolas;

use App\Models\LocalTrabalho;
use App\Models\Lotacao;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EscolaLotacaoSpreadsheetService
{
    private const MAX_ROWS = 10000;

    private const EXPECTED_HEADERS = [
        'local de trabalho',
        'numero da lotacao',
        'nome da lotacao',
    ];

    private const LEGACY_HEADERS = [
        'escola',
        'numero da lotacao',
        'nome da lotacao',
    ];

    public function importar(string $caminhoArquivo, User $usuario, string $disk = 'local'): array
    {
        $caminhoCompleto = Storage::disk($disk)->path($caminhoArquivo);

        try {
            $linhas = $this->validarLinhas($this->carregarLinhas($caminhoCompleto), $usuario);

            try {
                return DB::transaction(function () use ($linhas): array {
                    $criadas = 0;
                    $atualizadas = 0;

                    foreach ($linhas as $linha) {
                        /** @var Lotacao|null $existente */
                        $existente = $linha['lotacao_existente'];

                        if ($existente) {
                            $existente->update(['nome' => $linha['nome_lotacao']]);
                            $atualizadas++;

                            continue;
                        }

                        $linha['local_trabalho']->lotacoes()->create([
                            'codigo' => $linha['numero_lotacao'],
                            'nome' => $linha['nome_lotacao'],
                        ]);
                        $criadas++;
                    }

                    return [
                        'total_importado' => count($linhas),
                        'criadas' => $criadas,
                        'atualizadas' => $atualizadas,
                    ];
                });
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }

                throw new InvalidArgumentException(
                    'Uma lotação foi cadastrada por outro processo durante a importação. Atualize a planilha e tente novamente.',
                    previous: $exception,
                );
            }
        } finally {
            Storage::disk($disk)->delete($caminhoArquivo);
        }
    }

    /** @return list<array{0: mixed, 1: mixed, 2: mixed}> */
    private function carregarLinhas(string $caminhoCompleto): array
    {
        $reader = IOFactory::createReaderForFile($caminhoCompleto);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($caminhoCompleto);
        $sheet = $spreadsheet->getActiveSheet();
        $ultimaLinha = $sheet->getHighestDataRow();
        $linhas = [];

        for ($numeroLinha = 1; $numeroLinha <= $ultimaLinha; $numeroLinha++) {
            $linhas[] = $sheet->rangeToArray(
                "A{$numeroLinha}:C{$numeroLinha}",
                null,
                true,
                true,
                false,
            )[0];
        }

        $spreadsheet->disconnectWorksheets();

        return $linhas;
    }

    /**
     * @param  list<array{0: mixed, 1: mixed, 2: mixed}>  $rows
     * @return list<array{local_trabalho: LocalTrabalho, numero_lotacao: string, nome_lotacao: string, lotacao_existente: Lotacao|null}>
     */
    private function validarLinhas(array $rows, User $usuario): array
    {
        if (count($rows) < 2) {
            throw new InvalidArgumentException('A planilha deve conter o cabeçalho e ao menos uma lotação.');
        }

        $headers = array_map(fn (mixed $value): string => $this->normalizarTexto($value), $rows[0]);

        if ($headers !== self::EXPECTED_HEADERS && $headers !== self::LEGACY_HEADERS) {
            throw new InvalidArgumentException(
                'Cabeçalho inválido. Use exatamente: Local de trabalho, Número da lotação, Nome da lotação.',
            );
        }

        $linhas = collect(array_slice($rows, 1))
            ->map(fn (array $row, int $index): array => [
                'numero_linha' => $index + 2,
                'nome_local_trabalho' => $this->valor($row[0] ?? null),
                'numero_lotacao' => $this->valor($row[1] ?? null),
                'nome_lotacao' => $this->valor($row[2] ?? null),
            ])
            ->reject(fn (array $linha): bool => blank($linha['nome_local_trabalho'])
                && blank($linha['numero_lotacao'])
                && blank($linha['nome_lotacao']))
            ->values();

        if ($linhas->isEmpty()) {
            throw new InvalidArgumentException('A planilha não possui lotações preenchidas.');
        }

        if ($linhas->count() > self::MAX_ROWS) {
            throw new InvalidArgumentException('A planilha pode conter no máximo 10.000 lotações.');
        }

        $locais = $this->locaisPermitidos($usuario);
        $lotacoesExistentes = Lotacao::query()
            ->get(['id', 'escola_id', 'codigo', 'nome'])
            ->keyBy(fn (Lotacao $lotacao): string => $this->normalizarTexto($lotacao->codigo));
        $numerosEncontrados = [];
        $erros = [];

        $validadas = $linhas->map(function (array $linha) use (
            $locais,
            $lotacoesExistentes,
            &$numerosEncontrados,
            &$erros,
        ): ?array {
            $numeroLinha = $linha['numero_linha'];
            $nomeLocal = $linha['nome_local_trabalho'];
            $numeroLotacao = $linha['numero_lotacao'];
            $nomeLotacao = $linha['nome_lotacao'];

            if ($nomeLocal === null) {
                $erros[] = "Linha {$numeroLinha}: informe o Local de trabalho.";
            }

            if ($numeroLotacao === null) {
                $erros[] = "Linha {$numeroLinha}: informe o Número da lotação.";
            }

            if ($nomeLotacao === null) {
                $erros[] = "Linha {$numeroLinha}: informe o Nome da lotação.";
            }

            if ($nomeLocal === null || $numeroLotacao === null || $nomeLotacao === null) {
                return null;
            }

            if (mb_strlen($numeroLotacao) > 100) {
                $erros[] = "Linha {$numeroLinha}: o Número da lotação deve possuir no máximo 100 caracteres.";
            }

            if (mb_strlen($nomeLotacao) > 150) {
                $erros[] = "Linha {$numeroLinha}: o Nome da lotação deve possuir no máximo 150 caracteres.";
            }

            $chaveNumero = $this->normalizarTexto($numeroLotacao);

            if (isset($numerosEncontrados[$chaveNumero])) {
                $erros[] = "Linha {$numeroLinha}: a lotação {$numeroLotacao} aparece mais de uma vez na planilha.";

                return null;
            }

            $numerosEncontrados[$chaveNumero] = true;
            $chaveLocal = $this->normalizarTexto($nomeLocal);
            /** @var Collection<int, LocalTrabalho>|null $locaisComNome */
            $locaisComNome = $locais->get($chaveLocal);

            if (! $locaisComNome || $locaisComNome->isEmpty()) {
                $erros[] = "Linha {$numeroLinha}: o local de trabalho {$nomeLocal} não foi encontrado ou está fora do seu escopo.";

                return null;
            }

            if ($locaisComNome->count() > 1) {
                $erros[] = "Linha {$numeroLinha}: existem vários locais de trabalho ativos com o nome {$nomeLocal}.";

                return null;
            }

            /** @var LocalTrabalho $local */
            $local = $locaisComNome->first();
            /** @var Lotacao|null $existente */
            $existente = $lotacoesExistentes->get($chaveNumero);

            if ($existente && (int) $existente->escola_id !== (int) $local->id) {
                $erros[] = "Linha {$numeroLinha}: a lotação {$numeroLotacao} já pertence a outro local de trabalho.";

                return null;
            }

            if (mb_strlen($numeroLotacao) > 100 || mb_strlen($nomeLotacao) > 150) {
                return null;
            }

            return [
                'local_trabalho' => $local,
                'numero_lotacao' => $numeroLotacao,
                'nome_lotacao' => $nomeLotacao,
                'lotacao_existente' => $existente,
            ];
        })->filter()->values();

        if ($erros !== []) {
            $exibidos = array_slice($erros, 0, 10);
            $restantes = count($erros) - count($exibidos);

            if ($restantes > 0) {
                $exibidos[] = "Há mais {$restantes} erro(s) na planilha.";
            }

            throw new InvalidArgumentException(implode(' ', $exibidos));
        }

        return $validadas->all();
    }

    /** @return Collection<string, Collection<int, LocalTrabalho>> */
    private function locaisPermitidos(User $usuario): Collection
    {
        return app(UserSetorAccessService::class)
            ->applySetorScope(LocalTrabalho::query()->ativas(), $usuario)
            ->get()
            ->groupBy(fn (LocalTrabalho $local): string => $this->normalizarTexto($local->nome));
    }

    private function valor(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizarTexto(mixed $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->squish()
            ->value();
    }
}
