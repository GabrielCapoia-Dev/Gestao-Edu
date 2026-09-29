<?php

namespace App\Services\Dashboard;

use App\Models\EventoCalendario;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class EventoCalendarioLocalizacaoService
{
    /** @return list<array{label: string, referencia: string, lat: float, lng: float, origem: string}> */
    public function buscar(string $termo, bool $somenteSalvos = false): array
    {
        $termo = trim($termo);
        if (mb_strlen($termo) < 2) return [];

        $salvos = EventoCalendario::query()->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereNotNull('endereco_mapa')
            ->where(fn ($q) => $q->where('local', 'like', "%{$termo}%")->orWhere('endereco_mapa', 'like', "%{$termo}%"))
            ->orderByRaw('case when local = ? then 0 else 1 end', [$termo])->latest('id')->limit(10)
            ->get(['local', 'endereco_mapa', 'latitude', 'longitude'])
            ->map(fn (EventoCalendario $evento): array => [
                'label' => $evento->endereco_mapa,
                'referencia' => (string) $evento->local,
                'lat' => (float) $evento->latitude,
                'lng' => (float) $evento->longitude,
                'origem' => 'salvo',
            ])->unique(fn (array $item): string => $item['lat'].':'.$item['lng'])->values()->all();

        if ($somenteSalvos) return $salvos;

        $externos = Cache::remember('eventos:geocode:'.sha1(mb_strtolower($termo)), now()->addMinutes(30), function () use ($termo): array {
            $response = $this->clienteNominatim()->get('https://nominatim.openstreetmap.org/search', [
                'q' => $termo, 'format' => 'jsonv2', 'limit' => 10, 'countrycodes' => 'br',
                'addressdetails' => 1, 'namedetails' => 1, 'accept-language' => 'pt-BR',
            ]);
            if (! $response->successful()) return [];

            return collect($response->json())->map(fn (array $item): ?array => isset($item['lat'], $item['lon'], $item['display_name']) ? [
                'label' => (string) $item['display_name'], 'referencia' => (string) $item['display_name'], 'lat' => (float) $item['lat'], 'lng' => (float) $item['lon'], 'origem' => 'osm',
            ] : null)->filter()->values()->all();
        });

        return collect([...$salvos, ...$externos])->unique(fn (array $item): string => $item['lat'].':'.$item['lng'])->take(10)->values()->all();
    }

    public function reverter(float $latitude, float $longitude): ?string
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) return null;

        return Cache::remember('eventos:reverse-geocode:'.sha1($latitude.':'.$longitude), now()->addDays(7), function () use ($latitude, $longitude): ?string {
            $response = $this->clienteNominatim()->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $latitude, 'lon' => $longitude, 'format' => 'jsonv2', 'addressdetails' => 1,
                'zoom' => 18, 'layer' => 'address', 'accept-language' => 'pt-BR',
            ]);
            if (! $response->successful()) return null;

            return $this->formatarEndereco($response->json('address'), $response->json('display_name'));
        });
    }

    private function clienteNominatim(): \Illuminate\Http\Client\PendingRequest
    {
        $urlAplicacao = rtrim((string) config('app.url', 'https://gestaoedu.local'), '/');

        return Http::acceptJson()
            ->withUserAgent(sprintf('Gestao-Edu/1.0 (+%s)', $urlAplicacao))
            ->timeout(8)
            ->retry(1, 200, throw: false);
    }

    /** @param array<string, mixed>|null $endereco */
    private function formatarEndereco(?array $endereco, ?string $descricao): ?string
    {
        if (! is_array($endereco)) return filled($descricao) ? $descricao : null;

        $logradouro = $endereco['road'] ?? $endereco['pedestrian'] ?? $endereco['residential'] ?? null;
        $bairro = $endereco['suburb'] ?? $endereco['neighbourhood'] ?? $endereco['city_district'] ?? null;
        $cidade = $endereco['city'] ?? $endereco['town'] ?? $endereco['village'] ?? $endereco['municipality'] ?? null;
        $estado = $endereco['state'] ?? null;
        $numero = $endereco['house_number'] ?? null;

        $partes = [filled($logradouro) ? trim($logradouro.(filled($numero) ? ', '.$numero : '')) : null, $bairro, $cidade, $estado];
        $formatado = collect($partes)->filter()->implode(' · ');

        return $formatado !== '' ? $formatado : (filled($descricao) ? $descricao : null);
    }
}
