<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SaldoEleitoralCalendarService
{
    /** @return array{fixed: array<string, string>, easterOffsets: array<int, string>} */
    public function holidayRules(): array
    {
        return [
            'fixed' => [
                '01-01' => 'Confraternização Universal',
                '04-21' => 'Tiradentes',
                '05-01' => 'Dia do Trabalhador',
                '06-26' => 'Aniversário de Umuarama',
                '08-15' => 'Nossa Senhora da Assunção',
                '09-07' => 'Independência do Brasil',
                '10-04' => 'São Francisco de Assis',
                '10-12' => 'Nossa Senhora Aparecida',
                '11-02' => 'Finados',
                '11-15' => 'Proclamação da República',
                '11-20' => 'Dia da Consciência Negra',
                '12-25' => 'Natal',
            ],
            'easterOffsets' => [
                -2 => 'Paixão de Cristo',
                60 => 'Corpus Christi',
            ],
        ];
    }

    /** @param array<int, mixed> $dates @return list<string> */
    public function validateUsageDates(array $dates, int $expectedCount): array
    {
        $normalized = [];

        foreach ($dates as $date) {
            if (! is_string($date)) {
                throw ValidationException::withMessages(['datas' => 'Selecione datas válidas para o uso do saldo.']);
            }

            try {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
            } catch (\Throwable) {
                $parsed = false;
            }

            if (! $parsed || $parsed->format('Y-m-d') !== $date) {
                throw ValidationException::withMessages(['datas' => 'Selecione datas válidas para o uso do saldo.']);
            }

            if ($parsed->isWeekend() || $this->holidayName($parsed)) {
                throw ValidationException::withMessages(['datas' => 'Fins de semana e feriados não podem ser selecionados.']);
            }

            $normalized[] = $date;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        if (count($normalized) !== $expectedCount) {
            throw ValidationException::withMessages(['datas' => 'A quantidade de datas deve corresponder aos dias de saldo solicitados.']);
        }

        return $normalized;
    }

    private function holidayName(CarbonImmutable $date): ?string
    {
        $rules = $this->holidayRules();
        $fixed = $rules['fixed'][$date->format('m-d')] ?? null;

        if ($fixed) {
            return $fixed;
        }

        $year = $date->year;
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $easterPart = $h + $l - 7 * $m + 114;
        $easter = CarbonImmutable::createFromFormat('!Y-m-d', sprintf(
            '%04d-%02d-%02d',
            $year,
            intdiv($easterPart, 31),
            ($easterPart % 31) + 1,
        ));

        foreach ($rules['easterOffsets'] as $offset => $name) {
            if ($date->equalTo($easter->addDays((int) $offset))) {
                return $name;
            }
        }

        return null;
    }
}
