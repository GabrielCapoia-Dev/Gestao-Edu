<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use App\Services\ServidorHistoricoService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServidorDocumentoController extends Controller
{
    public function historico(Servidor $servidor): StreamedResponse
    {
        Gate::authorize('view', $servidor);

        $items = $servidor->movimentacoes()->with('usuario:id,name')->orderBy('ocorrido_em')->get();

        return $this->csv('historico-servidor-'.$servidor->getKey().'.csv', function ($output) use ($items): void {
            fputcsv($output, ['Data e hora', 'Movimentação', 'Responsável', 'Campo', 'Antes', 'Depois'], ';');
            foreach ($items as $item) {
                foreach ($item->alteracoes as $campo => $mudanca) {
                    fputcsv($output, [
                        $item->ocorrido_em?->format('d/m/Y H:i:s'),
                        'Alteração funcional',
                        $item->usuario?->name ?? 'Sistema',
                        $this->rotulo((string) $campo),
                        $this->valorCsv($mudanca['antes'] ?? null),
                        $this->valorCsv($mudanca['depois'] ?? null),
                    ], ';');
                }
            }
        });
    }

    public function ficha(Servidor $servidor, ServidorHistoricoService $historico): StreamedResponse
    {
        Gate::authorize('view', $servidor);
        $servidor->load(['matriculas' => fn ($q) => $q->withTrashed(), 'lotacao.escola', 'user']);
        $snapshot = $historico->capturar($servidor);

        return $this->csv('ficha-servidor-'.$servidor->getKey().'.csv', function ($output) use ($servidor, $snapshot): void {
            fputcsv($output, ['Campo', 'Informação'], ';');
            foreach ([
                'Nome' => $servidor->nome,
                'CPF' => $servidor->cpf,
                'E-mail' => $servidor->email,
                'Telefone' => $servidor->telefone,
                'Status' => $servidor->status,
                'Observações' => $servidor->observacoes,
                'Matrículas e turnos' => $this->valorCsv($snapshot['matriculas']),
                'Cargos e vínculos' => $this->valorCsv($snapshot['cargo']),
                'Lotação' => $this->valorCsv($snapshot['lotacao']),
                'Turmas, séries e componentes' => $this->valorCsv($snapshot['pedagogico']),
            ] as $campo => $valor) {
                fputcsv($output, [$campo, $valor], ';');
            }
        });
    }

    private function csv(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            $write($output);
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function valorCsv(mixed $value): string
    {
        return is_array($value)
            ? (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
            : (string) ($value ?? '');
    }

    private function rotulo(string $campo): string
    {
        return match ($campo) {
            'cargo' => 'Cargo e vínculos',
            'lotacao' => 'Lotação',
            'matriculas' => 'Matrículas e turnos',
            'pedagogico' => 'Turmas, séries e componentes',
            default => $campo,
        };
    }
}
