<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\Professor;

class AvaliacaoDocumentoCompatibilidadeProjector
{
    public function __construct(private readonly AvaliacaoAlunoDocumentoService $documentos)
    {
    }

    public function projetar(int $avaliacaoId, int $alunoId): void
    {
        $aluno = Aluno::query()->find($alunoId);
        if (! $aluno) {
            return;
        }

        $respostas = AvaliacaoRespostaOperacional::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->get();
        $informacoes = AvaliacaoInformacaoOperacional::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->get();
        $professores = Professor::query()
            ->whereIn('id', $respostas->pluck('professor_id')->merge($informacoes->pluck('professor_id'))->filter()->unique())
            ->pluck('nome', 'id');
        $payload = [
            'v' => 1,
            'pautas' => $respostas->mapWithKeys(fn ($item): array => [(string) $item->pauta_id => array_filter([
                'pauta_id' => (int) $item->pauta_id,
                'alternativa_id' => $item->alternativa_id ? (int) $item->alternativa_id : null,
                'observacao' => $item->observacao,
                'professor_id' => $item->professor_id ? (int) $item->professor_id : null,
                'professor_nome' => (string) ($professores[(int) $item->professor_id] ?? ''),
                'componente_curricular_id' => $item->componente_curricular_id ? (int) $item->componente_curricular_id : null,
                'respondido_em' => $item->respondido_em?->toIso8601String(),
            ], fn ($valor) => $valor !== null && $valor !== '')])->all(),
            'informacoes_complementares' => $informacoes->mapWithKeys(fn ($item): array => [(string) $item->componente_chave => array_filter([
                'componente_curricular_id' => $item->componente_curricular_id ? (int) $item->componente_curricular_id : null,
                'professor_id' => $item->professor_id ? (int) $item->professor_id : null,
                'professor_nome' => (string) ($professores[(int) $item->professor_id] ?? ''),
                'texto' => $item->texto,
            ], fn ($valor) => $valor !== null && $valor !== '')])->all(),
        ];

        $this->documentos->substituirPayloadCompatibilidade($avaliacaoId, $aluno, $payload);
    }
}
