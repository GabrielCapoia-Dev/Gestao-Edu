<?php

namespace App\Services\Avaliacoes;

use App\Exceptions\ResponsaveisParecerInvalidosException;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Turma;
use Illuminate\Support\Carbon;

class ParecerResponsaveisResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolver(Turma $turma, ?Carbon $momento = null, bool $bloquear = false): array
    {
        $momento ??= now();
        $turma->loadMissing('escola:id,nome', 'serie:id,nome');

        $diretores = ServidorFuncaoAdministrativa::query()
            ->where('id_escola', (int) $turma->id_escola)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotNull('data_inicio')
            ->whereDate('data_inicio', '<=', $momento->toDateString())
            ->where(function ($query) use ($momento): void {
                $query
                    ->whereNull('data_fim')
                    ->orWhereDate('data_fim', '>=', $momento->toDateString());
            })
            ->whereHas('servidor', fn ($query) => $query->where('status', Servidor::STATUS_ATIVO))
            ->whereHas('funcaoAdministrativa', fn ($query) => $query
                ->where('ativo', true)
                ->where('direcao_escolar', true)
                ->where('coordenacao_pedagogica', false)
                ->where('secretaria_escolar', false))
            ->with([
                'servidor:id,nome,status',
                'funcaoAdministrativa:id,nome,direcao_escolar,coordenacao_pedagogica',
            ])
            ->when($bloquear, fn ($query) => $query->lockForUpdate())
            ->get();

        if ($diretores->count() !== 1) {
            throw new ResponsaveisParecerInvalidosException(
                $diretores->isEmpty()
                    ? 'A escola não possui direção ativa e vigente.'
                    : 'A escola possui mais de uma direção ativa.'
            );
        }

        /** @var ServidorFuncaoAdministrativa $diretor */
        $diretor = $diretores->first();

        $coordenacoes = ServidorFuncaoTurma::query()
            ->where('turma_id', (int) $turma->id)
            ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->whereNotNull('data_inicio')
            ->whereDate('data_inicio', '<=', $momento->toDateString())
            ->where(function ($query) use ($momento): void {
                $query
                    ->whereNull('data_fim')
                    ->orWhereDate('data_fim', '>=', $momento->toDateString());
            })
            ->whereHas('servidorFuncaoAdministrativa', function ($query) use ($turma, $momento): void {
                $query
                    ->where('id_escola', (int) $turma->id_escola)
                    ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                    ->whereNotNull('data_inicio')
                    ->whereDate('data_inicio', '<=', $momento->toDateString())
                    ->where(function ($vigencia) use ($momento): void {
                        $vigencia
                            ->whereNull('data_fim')
                            ->orWhereDate('data_fim', '>=', $momento->toDateString());
                    })
                    ->whereHas('servidor', fn ($servidor) => $servidor->where('status', Servidor::STATUS_ATIVO))
                    ->whereHas('funcaoAdministrativa', fn ($funcao) => $funcao
                        ->where('ativo', true)
                        ->where('direcao_escolar', false)
                        ->where('coordenacao_pedagogica', true)
                        ->where('secretaria_escolar', false));
            })
            ->with([
                'servidorFuncaoAdministrativa.servidor:id,nome,status',
                'servidorFuncaoAdministrativa.funcaoAdministrativa:id,nome,direcao_escolar,coordenacao_pedagogica',
            ])
            ->when($bloquear, fn ($query) => $query->lockForUpdate())
            ->get();

        if ($coordenacoes->count() !== 1) {
            throw new ResponsaveisParecerInvalidosException(
                $coordenacoes->isEmpty()
                    ? 'A turma não possui coordenação ativa e vigente.'
                    : 'A turma possui mais de uma coordenação ativa.'
            );
        }

        /** @var ServidorFuncaoTurma $coordenacaoTurma */
        $coordenacaoTurma = $coordenacoes->first();
        $coordenador = $coordenacaoTurma->servidorFuncaoAdministrativa;

        $portariaDiretor = trim((string) $diretor->portaria);
        $portariaCoordenador = trim((string) $coordenador?->portaria);

        if ($portariaDiretor === '') {
            throw new ResponsaveisParecerInvalidosException('A direção não possui portaria informada.');
        }

        if ($portariaCoordenador === '') {
            throw new ResponsaveisParecerInvalidosException('A coordenação não possui portaria informada.');
        }

        if ((int) $diretor->servidor_id === (int) $coordenador?->servidor_id
            && $portariaDiretor !== $portariaCoordenador) {
            throw new ResponsaveisParecerInvalidosException(
                'Os vínculos de Diretor(a) e Coordenador(a) da mesma pessoa possuem portarias diferentes.'
            );
        }

        return [
            'v' => 1,
            'capturado_em' => $momento->toIso8601String(),
            'escola' => [
                'id' => (int) $turma->id_escola,
                'nome' => (string) ($turma->escola?->nome ?? ''),
            ],
            'turma' => [
                'id' => (int) $turma->id,
                'nome' => (string) $turma->nome,
                'turno' => (string) $turma->turno,
            ],
            'serie' => [
                'id' => (int) ($turma->id_serie ?? 0),
                'nome' => (string) ($turma->serie?->nome ?? ''),
            ],
            'diretor' => $this->dadosVinculo($diretor),
            'coordenador' => [
                ...$this->dadosVinculo($coordenador),
                'vinculo_turma_id' => (int) $coordenacaoTurma->id,
                'vinculo_turma_inicio' => $coordenacaoTurma->data_inicio?->toDateString(),
                'vinculo_turma_fim' => $coordenacaoTurma->data_fim?->toDateString(),
            ],
        ];
    }

    /**
     * @return array{diretor: string, coordenacao: string, tem_diretor: bool, tem_coordenacao: bool, pode_exportar: bool, motivo_bloqueio: string}
     */
    public function elegibilidade(Turma $turma): array
    {
        try {
            $snapshot = $this->resolver($turma);
            $dados = $this->dadosParaDocumento($snapshot);

            return [
                ...$dados,
                'tem_diretor' => true,
                'tem_coordenacao' => true,
                'pode_exportar' => true,
                'motivo_bloqueio' => '',
            ];
        } catch (ResponsaveisParecerInvalidosException $exception) {
            return [
                'diretor' => '',
                'coordenacao' => '',
                'tem_diretor' => false,
                'tem_coordenacao' => false,
                'pode_exportar' => false,
                'motivo_bloqueio' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{diretor: string, coordenacao: string}
     */
    public function dadosParaDocumento(array $snapshot): array
    {
        $diretor = is_array($snapshot['diretor'] ?? null) ? $snapshot['diretor'] : [];
        $coordenador = is_array($snapshot['coordenador'] ?? null) ? $snapshot['coordenador'] : [];

        if (empty($diretor['pessoa_id']) || empty($coordenador['pessoa_id'])) {
            throw new ResponsaveisParecerInvalidosException('O snapshot de responsáveis do parecer está incompleto.');
        }

        return [
            'diretor' => $this->formatarResponsavel($diretor),
            'coordenacao' => $this->formatarResponsavel($coordenador),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosVinculo(?ServidorFuncaoAdministrativa $vinculo): array
    {
        return [
            'vinculo_id' => (int) ($vinculo?->id ?? 0),
            'pessoa_id' => (int) ($vinculo?->servidor_id ?? 0),
            'funcao_administrativa_id' => (int) ($vinculo?->funcao_administrativa_id ?? 0),
            'nome' => (string) ($vinculo?->servidor?->nome ?? ''),
            'portaria' => trim((string) ($vinculo?->portaria ?? '')),
            'inicio' => $vinculo?->data_inicio?->toDateString(),
            'fim' => $vinculo?->data_fim?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $responsavel
     */
    private function formatarResponsavel(array $responsavel): string
    {
        return implode(' - ', array_filter([
            trim((string) ($responsavel['nome'] ?? '')),
            trim((string) ($responsavel['portaria'] ?? '')),
        ]));
    }
}
