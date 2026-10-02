<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use App\Services\ServidorHistoricoService;
use App\Services\SaldoEleitoralService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

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

    public function ficha(
        Servidor $servidor,
        ServidorHistoricoService $historico,
        SaldoEleitoralService $saldoEleitoral,
    ): Response
    {
        Gate::authorize('view', $servidor);
        $servidor->load([
            'matriculas' => fn ($query) => $query->withTrashed(),
            'lotacao.escola',
            'escola',
            'setor',
            'user.roles',
            'servidorFuncoes.funcaoAdministrativa',
            'servidorFuncoes.escola',
            'servidorFuncoes.setor',
            'servidorFuncoes.escolasAssessoradas',
            'saldoEleitoralMovimentacoes' => fn ($query) => $query
                ->with(['solicitante:id,name', 'aprovador:id,name', 'movimentoOrigem:id,dias,datas,created_at,decidido_em'])
                ->latest('created_at'),
        ]);
        $snapshot = $historico->capturar($servidor);
        $funcoes = $servidor->servidorFuncoes->map(fn ($vinculo): array => [
            'cargo' => $vinculo->funcaoAdministrativa?->nome ?? 'Cargo removido',
            'status' => $vinculo->status,
            'escola' => $vinculo->escola?->nome,
            'setor' => $vinculo->setor?->nome,
            'matricula' => $vinculo->matricula,
            'portaria' => $vinculo->portaria,
            'inicio' => $vinculo->data_inicio?->format('d/m/Y'),
            'fim' => $vinculo->data_fim?->format('d/m/Y'),
            'escolas_assessoradas' => $vinculo->escolasAssessoradas->pluck('nome')->all(),
        ])->all();
        $escolas = collect([$servidor->escola?->nome])
            ->merge(collect($funcoes)->pluck('escola'))
            ->merge(collect($funcoes)->pluck('escolas_assessoradas')->flatten())
            ->filter()->unique()->values()->all();
        $movimentacoesSaldo = $servidor->saldoEleitoralMovimentacoes;
        $movimentacoesServidor = $servidor->movimentacoes()->with('usuario:id,name')->latest('ocorrido_em')->get();
        $usuario = $servidor->user;

        return Pdf::loadView('relatorios.servidores.ficha', [
            'servidor' => $servidor,
            'snapshot' => $snapshot,
            'funcoes' => $funcoes,
            'escolas' => $escolas,
            'acesso' => $usuario ? [
                'email' => $usuario->email,
                'aprovado' => (bool) $usuario->email_approved,
                'status' => $usuario->trashed() ? 'Arquivado' : 'Ativo',
                'perfis' => $usuario->getRoleNames()->all(),
            ] : null,
            'movimentacoesSaldo' => $movimentacoesSaldo,
            'saldoDisponivel' => $saldoEleitoral->disponivelParaSolicitacao($servidor),
            'saldoAprovado' => $saldoEleitoral->saldoAprovado($servidor),
            'movimentacoesServidor' => $movimentacoesServidor,
            'geradoEm' => now(),
        ])->setPaper('a4', 'portrait')
            ->download('ficha-servidor-'.$servidor->getKey().'.pdf');
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
