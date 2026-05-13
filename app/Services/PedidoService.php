<?php

namespace App\Services;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\FeedbackPedido;
use App\Models\Pedido;
use App\Models\PedidoHistorico;
use App\Models\PedidoProblema;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PedidoService
{
    /*
    |--------------------------------------------------------------------------
    | PERMISSOES E ESCOPO
    |--------------------------------------------------------------------------
    */

    public function ehAdmin(?User $user): bool
    {
        return $user?->hasRole('Admin') ?? false;
    }

    public function podeListarTodos(?User $user): bool
    {
        return $this->ehAdmin($user)
            || ($user?->hasPermissionTo('Listar Todos os Pedidos') ?? false);
    }

    public function podeGerenciarPedidos(?User $user): bool
    {
        return $user?->hasPermissionTo('Editar Pedidos') ?? false;
    }

    public function podeCriarPedidos(?User $user): bool
    {
        return $user?->hasPermissionTo('Criar Pedidos') ?? false;
    }

    public function podeEnviarParaEmpresa(?User $user): bool
    {
        return ($user?->hasPermissionTo('Enviar Pedidos para Empresa') ?? false)
            && $this->usuarioEhSetor($user, 'Obras');
    }

    public function podeVincularAdicionais(?User $user): bool
    {
        return $user?->hasPermissionTo('Vincular Pedidos Adicionais') ?? false;
    }

    public function usuarioEhSetor(?User $user, string $nomeSetor): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->podeListarTodos($user)) {
            return true;
        }

        return $user->pertenceAoSetorOperacionalNome($nomeSetor);
    }

    public function podeGerenciarRegistro(Pedido $pedido, ?User $user): bool
    {
        if (! $user || ! $user->hasPermissionTo('Editar Pedidos')) {
            return false;
        }

        if ($pedido->is_pedido_adicional) {
            return false;
        }

        if ($pedido->tipoStatus?->finaliza_pedido || $pedido->tipoStatus?->cancela_pedido) {
            return false;
        }

        if ($this->podeListarTodos($user)) {
            return true;
        }

        $setorIds = $user->idsSetoresOperacionais();

        return $setorIds !== []
            && filled($pedido->setor_id)
            && in_array((int) $pedido->setor_id, $setorIds, true);
    }

    public function contarPedidosNovos(?User $user): ?string
    {
        if (! $this->podeGerenciarPedidos($user)) {
            return null;
        }

        $statusAberto = $this->statusPorNome('Em Aberto');

        if (! $statusAberto) {
            return null;
        }

        $query = Pedido::query()
            ->where('ativo', true)
            ->where('is_pedido_adicional', false)
            ->where('tipo_status_id', $statusAberto->id);

        $this->aplicarEscopo($query, $user);

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public function queryPorPerfil(Builder $query, ?User $user): Builder
    {
        $query->where('ativo', true);

        return $this->aplicarEscopo($query, $user);
    }

    public function queryTabela(?User $user): Builder
    {
        $query = Pedido::query()
            ->with(['ultimoHistorico', 'tipoStatus', 'tipoManutencao', 'setor', 'pedidoPrincipal'])
            ->where('ativo', true);

        return $this->aplicarEscopo($query, $user);
    }

    public function queryTabTodos(?User $user): Builder
    {
        return $this->queryTabela($user)->orderByDesc('updated_at');
    }

    protected function aplicarEscopo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->podeListarTodos($user)) {
            return $query;
        }

        $setorIds = $user->idsSetoresOperacionais();

        if ($setorIds !== []) {
            return $query->whereIn('setor_id', $setorIds);
        }

        if ($user->id_escola) {
            return $query->where('escola_id', $user->id_escola);
        }

        return $query->nenhum();
    }

    /*
    |--------------------------------------------------------------------------
    | CRIACAO
    |--------------------------------------------------------------------------
    */

    public function criarPedido(array $data, User $solicitante): Pedido
    {
        return DB::transaction(function () use ($data, $solicitante): Pedido {
            $statusInicial = $this->statusPorNome('Em Aberto', true);
            $setorInicial = Setor::setorGeral();

            if (! $setorInicial) {
                throw new \RuntimeException('Nenhum setor foi configurado para receber os pedidos iniciais.');
            }

            if (! TipoManutencao::query()->whereKey($data['tipo_manutencao_id'])->where('ativo', true)->exists()) {
                throw new \RuntimeException('Tipo de manutencao indisponivel para novos pedidos.');
            }

            $pedido = Pedido::create([
                'tipo_manutencao_id' => $data['tipo_manutencao_id'],
                'descricao_pedido' => $data['descricao_pedido'],
                'nome_solicitante' => $data['nome_solicitante'],
                'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
                'solicitante_id' => $solicitante->id,
                'escola_id' => $solicitante->id_escola,
                'tipo_status_id' => $statusInicial->id,
                'setor_id' => $setorInicial->id,
                'data_solicitacao' => now(),
                'data_identificacao_problema' => $data['data_identificacao_problema'] ?? now(),
                'ativo' => true,
            ]);

            $this->criarProblemasDoPedido(
                $pedido,
                $data['tipo_manutencao_opcao_ids'] ?? [],
                $data['descricao_pedido'] ?? null
            );

            if (filled($data['arquivos'] ?? null)) {
                foreach (array_filter($data['arquivos']) as $path) {
                    $this->salvarArquivoPedido(
                        pedido: $pedido,
                        path: $path,
                        tipo: TipoArquivoPedido::FOTOS_PROBLEMA,
                        usuarioId: $solicitante->id
                    );
                }
            }

            $this->registrarHistorico($pedido, null, $statusInicial->id, $solicitante, 'Pedido criado');

            return $pedido;
        });
    }

    public function criarPedidosAdicionais(Pedido $pedidoPrincipal, array $adicionais, User $usuario): Collection
    {
        return DB::transaction(function () use ($pedidoPrincipal, $adicionais, $usuario): Collection {
            $statusAdicional = $this->statusPedidoAdicional();
            $criados = collect();

            foreach ($adicionais as $data) {
                if (blank($data['descricao_pedido'] ?? null) || blank($data['tipo_manutencao_id'] ?? null)) {
                    continue;
                }

                if (! TipoManutencao::query()->whereKey($data['tipo_manutencao_id'])->where('ativo', true)->exists()) {
                    continue;
                }

                $pedido = Pedido::create([
                    'pedido_principal_id' => $pedidoPrincipal->id,
                    'is_pedido_adicional' => true,
                    'tipo_manutencao_id' => $data['tipo_manutencao_id'],
                    'descricao_pedido' => $data['descricao_pedido'],
                    'nome_solicitante' => $data['nome_solicitante'] ?? $usuario->name,
                    'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
                    'solicitante_id' => $usuario->id,
                    'escola_id' => $pedidoPrincipal->escola_id,
                    'tipo_status_id' => $statusAdicional->id,
                    'setor_id' => $pedidoPrincipal->setor_id,
                    'empresa_contratada_id' => $pedidoPrincipal->empresa_contratada_id,
                    'data_solicitacao' => now(),
                    'data_identificacao_problema' => $data['data_identificacao_problema'] ?? now(),
                    'ativo' => true,
                ]);

                $this->criarProblemasDoPedido(
                    $pedido,
                    $data['tipo_manutencao_opcao_ids'] ?? [],
                    $data['descricao_pedido']
                );

                $this->registrarHistorico(
                    $pedido,
                    null,
                    $statusAdicional->id,
                    $usuario,
                    "Pedido adicional vinculado ao protocolo {$pedidoPrincipal->numero_protocolo}."
                );

                $this->registrarHistorico(
                    $pedidoPrincipal,
                    $pedidoPrincipal->tipo_status_id,
                    $pedidoPrincipal->tipo_status_id,
                    $usuario,
                    "Pedido adicional {$pedido->numero_protocolo} vinculado a esta solicitacao."
                );

                $criados->push($pedido);
            }

            return $criados;
        });
    }

    protected function criarProblemasDoPedido(Pedido $pedido, array $opcaoIds, ?string $fallbackDescricao = null): void
    {
        $opcoes = TipoManutencaoOpcao::query()
            ->whereIn('id', collect($opcaoIds)->filter()->map(fn ($id) => (int) $id)->all())
            ->where('tipo_manutencao_id', $pedido->tipo_manutencao_id)
            ->where('ativo', true)
            ->get()
            ->keyBy('id');

        foreach ($opcaoIds as $opcaoId) {
            $opcao = $opcoes->get((int) $opcaoId);

            if (! $opcao) {
                continue;
            }

            $pedido->problemas()->create([
                'tipo_manutencao_id' => $pedido->tipo_manutencao_id,
                'tipo_manutencao_opcao_id' => $opcao->id,
                'texto_problema' => $opcao->texto,
            ]);
        }

        if ($pedido->problemas()->exists()) {
            return;
        }

        $pedido->problemas()->create([
            'tipo_manutencao_id' => $pedido->tipo_manutencao_id,
            'tipo_manutencao_opcao_id' => null,
            'texto_problema' => Str::limit($fallbackDescricao ?: 'Problema informado no pedido', 255, ''),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ALTERACAO DE STATUS
    |--------------------------------------------------------------------------
    */

    public function alterarStatus(
        Pedido $pedido,
        TipoStatus $novoStatus,
        User $usuario,
        ?string $descricao = null
    ): void {
        $statusAnteriorId = $pedido->tipo_status_id;

        $pedido->update([
            'tipo_status_id' => $novoStatus->id,
            'responsavel_id' => $usuario->id,
        ]);

        $this->registrarHistorico($pedido, $statusAnteriorId, $novoStatus->id, $usuario, $descricao);

        if ($novoStatus->finaliza_pedido) {
            $pedido->update(['data_entrega' => now()]);
        }
    }

    public function encaminharParaSetor(Pedido $pedido, Setor $setorDestino, User $usuario, ?string $descricao = null): void
    {
        DB::transaction(function () use ($pedido, $setorDestino, $usuario, $descricao): void {
            $statusAnteriorId = $pedido->tipo_status_id;
            $statusEncaminhado = $this->statusPorNome('Encaminhado ao Setor', true);
            $statusAberto = $this->statusPorNome('Em Aberto', true);

            $pedido->update([
                'tipo_status_id' => $statusAberto->id,
                'setor_id' => $setorDestino->id,
                'responsavel_id' => $usuario->id,
            ]);

            $this->registrarHistorico(
                $pedido,
                $statusAnteriorId,
                $statusEncaminhado->id,
                $usuario,
                $descricao ?: "Pedido encaminhado para o setor {$setorDestino->nome}."
            );

            $this->registrarHistorico(
                $pedido,
                $statusEncaminhado->id,
                $statusAberto->id,
                $usuario,
                "Pedido recebido pelo setor {$setorDestino->nome} com status Em Aberto."
            );
        });
    }

    public function assumirPedido(Pedido $pedido, User $usuario): void
    {
        $statusEmAberto = $this->statusPorNome('Em Aberto');
        $statusReaberto = $this->statusPorNome('Reaberto');
        $statusAnalise = $this->statusPorNome('Em Análise');

        if (! $statusAnalise) {
            return;
        }

        $statusPermitidos = collect([$statusEmAberto?->id, $statusReaberto?->id])
            ->filter()
            ->all();

        if (
            in_array((int) $pedido->tipo_status_id, $statusPermitidos, true)
            && ($this->podeListarTodos($usuario) || $usuario->podeGerenciarSetor($pedido->setor))
        ) {
            $this->alterarStatus(
                $pedido,
                $statusAnalise,
                $usuario,
                'Pedido assumido para análise por '.$usuario->name.'.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AVALIACAO
    |--------------------------------------------------------------------------
    */

    public function problemasParaAvaliacao(Pedido $pedido, bool $criarPadrao = true): Collection
    {
        if ($criarPadrao && ! $pedido->problemas()->exists()) {
            $this->criarProblemasDoPedido($pedido, [], $pedido->descricao_pedido);
        }

        $pedidoIds = collect([$pedido->id])
            ->merge($pedido->pedidosAdicionais()->pluck('id'))
            ->values()
            ->all();

        return PedidoProblema::query()
            ->with(['pedido.tipoManutencao'])
            ->whereIn('pedido_id', $pedidoIds)
            ->orderBy('pedido_id')
            ->orderBy('id')
            ->get();
    }

    public function avaliarPedido(Pedido $pedido, array $data, User $usuario): FeedbackPedido
    {
        return DB::transaction(function () use ($pedido, $data, $usuario): FeedbackPedido {
            $adicionaisCriados = collect();

            if (! empty($data['adicionar_adicionais']) && ! empty($data['pedidos_adicionais'])) {
                $adicionaisCriados = $this->criarPedidosAdicionais($pedido, $data['pedidos_adicionais'], $usuario);
                $pedido->refresh();
            }

            $problemas = $this->problemasParaAvaliacao($pedido);
            $avaliacoes = collect($data['avaliacoes'] ?? []);
            $dadosAdicionais = collect($data['pedidos_adicionais'] ?? [])->values();
            $adicionalIndicePorPedidoId = $adicionaisCriados
                ->values()
                ->mapWithKeys(fn (Pedido $adicional, int $indice): array => [$adicional->id => $indice]);

            $dadosDaAvaliacao = function (PedidoProblema $problema) use ($avaliacoes, $dadosAdicionais, $adicionalIndicePorPedidoId): array {
                $avaliacao = $avaliacoes->get((string) $problema->id)
                    ?? $avaliacoes->get($problema->id);

                if (is_array($avaliacao) && $avaliacao !== []) {
                    return $avaliacao;
                }

                $indiceAdicional = $adicionalIndicePorPedidoId->get($problema->pedido_id);

                if ($indiceAdicional !== null) {
                    return (array) $dadosAdicionais->get($indiceAdicional, []);
                }

                return [];
            };

            $notas = $problemas
                ->map(fn (PedidoProblema $problema): int => (int) ($dadosDaAvaliacao($problema)['valor'] ?? 3))
                ->map(fn (int $nota): int => max(1, min(5, $nota)));

            $notaGeral = $notas->isNotEmpty()
                ? (int) round($notas->avg())
                : (int) ($data['valor'] ?? 3);

            $reabrirPedido = (bool) ($data['reabrir_pedido'] ?? false);

            $feedback = $pedido->feedbacks()->create([
                'valor' => max(1, min(5, $notaGeral)),
                'descricao' => $data['descricao'] ?? null,
                'reabrir_pedido' => $reabrirPedido,
            ]);

            foreach ($problemas as $problema) {
                $avaliacao = $dadosDaAvaliacao($problema);
                $resultado = ResultadoFeedbackPedido::tryFrom($avaliacao['resultado'] ?? '')
                    ?? ResultadoFeedbackPedido::Atendido;

                $feedback->itens()->create([
                    'pedido_id' => $problema->pedido_id,
                    'pedido_problema_id' => $problema->id,
                    'valor' => max(1, min(5, (int) ($avaliacao['valor'] ?? 3))),
                    'resultado' => $resultado,
                    'comentario' => $avaliacao['comentario'] ?? null,
                ]);
            }

            if (! empty($data['fotos_conclusao'])) {
                foreach ($data['fotos_conclusao'] as $path) {
                    $this->salvarArquivoPedido(
                        pedido: $pedido,
                        path: $path,
                        tipo: TipoArquivoPedido::FOTOS_CONCLUSAO,
                        usuarioId: $usuario->id,
                        descricao: 'Fotos da conclusão do serviço'
                    );
                }
            }

            $novoStatus = $reabrirPedido
                ? $this->statusPorNome('Reaberto', true)
                : $this->statusPorNome('Concluído', true);

            $this->alterarStatus(
                $pedido,
                $novoStatus,
                $usuario,
                $reabrirPedido
                    ? 'Pedido reaberto após avaliação do solicitante.'
                    : 'Pedido concluído e avaliado por problema.'
            );

            return $feedback;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORICO E UTILITARIOS
    |--------------------------------------------------------------------------
    */

    public function registrarHistorico(
        Pedido $pedido,
        ?int $statusAnteriorId,
        int $statusNovoId,
        User $usuario,
        ?string $descricao = null
    ): PedidoHistorico {
        return PedidoHistorico::create([
            'pedido_id' => $pedido->id,
            'status_anterior_id' => $statusAnteriorId,
            'status_novo_id' => $statusNovoId,
            'usuario_id' => $usuario->id,
            'setor_id' => $pedido->setor_id,
            'descricao_alteracao' => $descricao,
        ]);
    }

    public function statusPorNome(string $nome, bool $fail = false): ?TipoStatus
    {
        $query = TipoStatus::query()
            ->whereIn('nome', $this->aliasesTexto($nome));

        return $fail ? $query->firstOrFail() : $query->first();
    }

    private function statusPedidoAdicional(): TipoStatus
    {
        $status = $this->statusPorNome('Pedido Adicional');

        if ($status) {
            $status->forceFill([
                'cor' => $status->cor ?: '#64748b',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ])->save();

            return $status->refresh();
        }

        return TipoStatus::query()->create([
            'nome' => 'Pedido Adicional',
            'cor' => '#64748b',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => true,
        ]);
    }

    public function setorPorNome(string $nome): ?Setor
    {
        return Setor::query()
            ->where('ativo', true)
            ->whereIn('nome', $this->aliasesTexto($nome))
            ->first();
    }

    private function aliasesTexto(string $nome): array
    {
        $aliases = [$nome];

        if (function_exists('mb_convert_encoding')) {
            $aliases[] = mb_convert_encoding($nome, 'UTF-8', 'ISO-8859-1');

            if (str_contains($nome, 'Ã') || str_contains($nome, 'Â')) {
                $aliases[] = mb_convert_encoding($nome, 'ISO-8859-1', 'UTF-8');
            }
        }

        return collect($aliases)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function salvarArquivoPedido(
        Pedido $pedido,
        string $path,
        TipoArquivoPedido $tipo,
        int $usuarioId,
        ?string $descricao = null
    ): void {
        $mime = 'application/octet-stream';

        try {
            $mime = Storage::mimeType("public/{$path}") ?: $mime;
        } catch (\Throwable) {
            try {
                $mime = Storage::disk('public')->mimeType($path) ?: $mime;
            } catch (\Throwable) {
                //
            }
        }

        $pedido->arquivos()->create([
            'usuario_id' => $usuarioId,
            'tipo_arquivo' => $tipo,
            'caminho' => $path,
            'nome_original' => basename($path),
            'mime_type' => $mime,
            'descricao' => $descricao,
        ]);
    }
}
