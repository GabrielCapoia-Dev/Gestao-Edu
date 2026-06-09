<?php

namespace App\Services;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\FeedbackPedido;
use App\Models\Pedido;
use App\Models\PedidoHistorico;
use App\Models\PedidoProblema;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function podeVerTodosOsPedidos(?User $user): bool
    {
        return $this->podeListarTodos($user)
            || app(UserSetorAccessService::class)->hasGlobalAccess($user);
    }

    public function escolaIdsParaEscopo(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return collect($user->idsEscolasVinculadas())
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
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
        return $user?->hasPermissionTo('Enviar Pedidos para Empresa') ?? false;
    }

    public function podeVincularAdicionais(?User $user): bool
    {
        return $user?->hasPermissionTo('Vincular Pedidos Adicionais') ?? false;
    }

    public function statusOptionsParaAlteracaoEmMassa(?User $user): array
    {
        if (! $this->podeGerenciarPedidos($user)) {
            return [];
        }

        $statusComFluxoProprioIds = collect([
            'Encaminhado ao Setor',
            'Enviado para Empresa',
            'Pedido Adicional',
        ])
            ->map(fn (string $nome): ?TipoStatus => $this->statusPorNome($nome))
            ->filter()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ordemPreferencial = collect([
            'Em Aberto',
            'Reaberto',
            'Em Análise',
            'Em Manutenção',
        ])
            ->map(fn (string $nome): ?TipoStatus => $this->statusPorNome($nome))
            ->filter()
            ->values();

        $ordemPorId = $ordemPreferencial
            ->mapWithKeys(fn (TipoStatus $status, int $index): array => [(int) $status->id => $index])
            ->all();

        return TipoStatus::query()
            ->where('ativo', true)
            ->where('finaliza_pedido', false)
            ->where('cancela_pedido', false)
            ->when($statusComFluxoProprioIds !== [], fn (Builder $query) => $query->whereNotIn('id', $statusComFluxoProprioIds))
            ->get()
            ->sortBy(fn (TipoStatus $status): array => [$ordemPorId[(int) $status->id] ?? 999, $status->nome])
            ->mapWithKeys(fn (TipoStatus $status): array => [(int) $status->id => $status->nome])
            ->toArray();
    }

    public function usuarioEhSetor(?User $user, string $nomeSetor): bool
    {
        return false;
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

        if ($this->podeVerTodosOsPedidos($user)) {
            return true;
        }

        $escolaIds = $this->escolaIdsParaEscopo($user);

        if ($escolaIds !== []) {
            return in_array((int) $pedido->escola_id, $escolaIds, true);
        }

        $access = app(UserSetorAccessService::class);

        return $access->canAccessSetor($user, $pedido->setor_id)
            || $access->canAccessSetor($user, $pedido->setor_origem_id);
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
        /** @var Builder $query */
        $query = $this->aplicarEscopoConsulta($query, $user);

        return $query;
    }

    public function aplicarEscopoConsulta(
        Builder|QueryBuilder $query,
        ?User $user,
        string $escolaColumn = 'escola_id',
        string $setorColumn = 'setor_id',
        string $setorOrigemColumn = 'setor_origem_id'
    ): Builder|QueryBuilder {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $access = app(UserSetorAccessService::class);

        if ($this->podeListarTodos($user) || $access->hasGlobalAccess($user)) {
            return $query;
        }

        $escolaIds = $this->escolaIdsParaEscopo($user);

        if ($escolaIds !== []) {
            return $query->whereIn($escolaColumn, $escolaIds);
        }

        $setorIds = $access->visibleSetorIds($user);

        if ($setorIds !== []) {
            return $query->where(function ($builder) use ($setorIds, $setorColumn, $setorOrigemColumn): void {
                $builder->whereIn($setorColumn, $setorIds)
                    ->orWhereIn($setorOrigemColumn, $setorIds);
            });
        }

        return $query->whereRaw('1 = 0');
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
            $escolaId = $this->escolaIdDoSolicitante($solicitante);
            $setorOrigemId = app(UserSetorAccessService::class)->primarySetorId($solicitante)
                ?: ($escolaId ? Escola::query()->whereKey($escolaId)->value('setor_id') : null);

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
                'escola_id' => $escolaId,
                'tipo_status_id' => $statusInicial->id,
                'setor_id' => $setorInicial->id,
                'setor_origem_id' => $setorOrigemId,
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
                foreach (array_filter($data['arquivos']) as $arquivo) {
                    $this->salvarArquivoPedido(
                        pedido: $pedido,
                        arquivo: $arquivo,
                        tipo: TipoArquivoPedido::FOTOS_PROBLEMA,
                        usuarioId: $solicitante->id,
                        directory: 'pedidos'
                    );
                }
            }

            $this->registrarHistorico($pedido, null, $statusInicial->id, $solicitante, 'Pedido criado');

            return $pedido;
        });
    }

    public function criarPedidosAdicionais(Pedido $pedidoPrincipal, array $adicionais, User $usuario): Collection
    {
        $this->validarPedidosAdicionais($adicionais, exigirAvaliacao: false);

        return DB::transaction(function () use ($pedidoPrincipal, $adicionais, $usuario): Collection {
            $statusAdicional = $this->statusPedidoAdicional();
            $criados = collect();

            foreach ($adicionais as $data) {
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
                    'setor_origem_id' => $pedidoPrincipal->setor_origem_id,
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

                foreach (array_filter((array) ($data['arquivos'] ?? [])) as $arquivo) {
                    $this->salvarArquivoPedido(
                        pedido: $pedido,
                        arquivo: $arquivo,
                        tipo: TipoArquivoPedido::FOTOS_PROBLEMA,
                        usuarioId: $usuario->id,
                        descricao: 'Fotos do pedido adicional',
                        directory: 'pedidos/adicionais'
                    );
                }

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

    private function escolaIdDoSolicitante(User $solicitante): ?int
    {
        if (filled($solicitante->id_escola)) {
            return (int) $solicitante->id_escola;
        }

        $escolaIds = collect($solicitante->idsEscolasVinculadas())
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return $escolaIds->count() === 1 ? $escolaIds->first() : null;
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
            if (! $usuario->hasPermissionTo('Encaminhar Pedidos para Setor')) {
                throw new \RuntimeException('Usuario sem permissao para encaminhar pedidos para setor.');
            }

            app(UserSetorAccessService::class)->assertCanUseSetor($usuario, (int) $setorDestino->id);

            $statusAnteriorId = $pedido->tipo_status_id;
            $statusEncaminhado = $this->statusPorNome('Encaminhado ao Setor', true);

            $pedido->update([
                'tipo_status_id' => $statusEncaminhado->id,
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
        });
    }

    public function empresaContratadaDisponivelParaEnvio(EmpresaContratada|int $empresa, User $usuario): EmpresaContratada
    {
        $empresa = $empresa instanceof EmpresaContratada
            ? $empresa
            : EmpresaContratada::query()->whereKey($empresa)->first();

        if (! $empresa || ! $empresa->ativo) {
            throw ValidationException::withMessages([
                'empresa_contratada_id' => 'Empresa indisponivel para envio de pedidos.',
            ]);
        }

        if (! app(UserSetorAccessService::class)->canAccessSetor($usuario, (int) $empresa->setor_id)) {
            throw ValidationException::withMessages([
                'empresa_contratada_id' => 'Voce nao tem permissao para usar esta empresa.',
            ]);
        }

        return $empresa;
    }

    public function enviarParaEmpresa(
        Pedido $pedido,
        EmpresaContratada|int $empresa,
        User $usuario,
        ?string $descricao = null
    ): bool {
        return DB::transaction(function () use ($pedido, $empresa, $usuario, $descricao): bool {
            if (! $this->podeEnviarParaEmpresa($usuario)) {
                throw new \RuntimeException('Usuario sem permissao para enviar pedidos para empresa.');
            }

            if (! $this->podeGerenciarRegistro($pedido, $usuario)) {
                return false;
            }

            $empresa = $this->empresaContratadaDisponivelParaEnvio($empresa, $usuario);
            $statusAnteriorId = $pedido->tipo_status_id;
            $statusEnviadoEmpresa = $this->statusPorNome('Enviado para Empresa', true);

            $pedido->update([
                'tipo_status_id' => $statusEnviadoEmpresa->id,
                'empresa_contratada_id' => $empresa->id,
                'responsavel_id' => $usuario->id,
            ]);

            $this->registrarHistorico(
                $pedido,
                $statusAnteriorId,
                $statusEnviadoEmpresa->id,
                $usuario,
                $descricao ?: "Pedido enviado para a empresa {$empresa->nome}."
            );

            return true;
        });
    }

    public function assumirPedido(Pedido $pedido, User $usuario): void
    {
        $statusEmAberto = $this->statusPorNome('Em Aberto');
        $statusReaberto = $this->statusPorNome('Reaberto');
        $statusEncaminhado = $this->statusPorNome('Encaminhado ao Setor');
        $statusAnalise = $this->statusPorNome('Em Análise');

        if (! $statusAnalise) {
            return;
        }

        $statusPermitidos = collect([$statusEmAberto?->id, $statusReaberto?->id, $statusEncaminhado?->id])
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

    public function avaliarPedido(Pedido $pedido, array $data, User $usuario): ?FeedbackPedido
    {
        $this->validarAvaliacaoPedido($pedido, $data);

        return DB::transaction(function () use ($pedido, $data, $usuario): ?FeedbackPedido {
            $reabrirPedido = (bool) ($data['reabrir_pedido'] ?? false);

            if ($reabrirPedido) {
                $this->alterarStatus(
                    $pedido,
                    $this->statusPorNome('Reaberto', true),
                    $usuario,
                    $data['descricao'] ?: 'Pedido reaberto para nova execução.'
                );

                return null;
            }

            $problemas = $this->problemasParaAvaliacao($pedido);
            $avaliacoes = collect($data['avaliacoes'] ?? []);

            $dadosDaAvaliacao = function (PedidoProblema $problema) use ($avaliacoes): array {
                $avaliacao = $avaliacoes->get((string) $problema->id)
                    ?? $avaliacoes->get($problema->id);

                if (is_array($avaliacao) && $avaliacao !== []) {
                    return $avaliacao;
                }

                return [];
            };

            $notas = $problemas
                ->map(fn (PedidoProblema $problema): int => (int) ($dadosDaAvaliacao($problema)['valor'] ?? 3))
                ->map(fn (int $nota): int => max(1, min(5, $nota)));

            $notaGeral = $notas->isNotEmpty()
                ? (int) round($notas->avg())
                : (int) ($data['valor'] ?? 3);

            $feedback = $pedido->feedbacks()->create([
                'valor' => max(1, min(5, $notaGeral)),
                'descricao' => $data['descricao'] ?? null,
                'reabrir_pedido' => false,
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
                foreach ($data['fotos_conclusao'] as $arquivo) {
                    $this->salvarArquivoPedido(
                        pedido: $pedido,
                        arquivo: $arquivo,
                        tipo: TipoArquivoPedido::FOTOS_CONCLUSAO,
                        usuarioId: $usuario->id,
                        descricao: 'Fotos da conclusão do serviço',
                        directory: 'pedidos/conclusao'
                    );
                }
            }

            $this->alterarStatus(
                $pedido,
                $this->statusPorNome('Concluído', true),
                $usuario,
                'Pedido concluído e avaliado por problema.'
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

    private function validarAvaliacaoPedido(Pedido $pedido, array $data): void
    {
        $errors = [];

        if (blank($data['descricao'] ?? null)) {
            $errors['descricao'] = 'Descreva a avaliacao geral do pedido.';
        }

        $reabrirPedido = (bool) ($data['reabrir_pedido'] ?? false);

        if (! $reabrirPedido) {
            foreach ($this->problemasParaAvaliacao($pedido, false) as $problema) {
                $avaliacao = collect($data['avaliacoes'] ?? [])->get((string) $problema->id)
                    ?? collect($data['avaliacoes'] ?? [])->get($problema->id);

                if (blank($avaliacao['comentario'] ?? null)) {
                    $errors["avaliacoes.{$problema->id}.comentario"] = 'Descreva a avaliacao deste problema.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validarPedidosAdicionais(array $adicionais, bool $exigirAvaliacao): void
    {
        $errors = $this->errosPedidosAdicionais($adicionais, $exigirAvaliacao);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function errosPedidosAdicionais(array $adicionais, bool $exigirAvaliacao): array
    {
        $errors = [];

        foreach (array_values($adicionais) as $index => $data) {
            if (blank($data['tipo_manutencao_id'] ?? null)) {
                $errors["pedidos_adicionais.{$index}.tipo_manutencao_id"] = 'Informe o tipo de manutencao do pedido adicional.';
            }

            if (blank($data['descricao_pedido'] ?? null)) {
                $errors["pedidos_adicionais.{$index}.descricao_pedido"] = 'Descreva o pedido adicional.';
            }

            if (count(array_filter((array) ($data['arquivos'] ?? []))) === 0) {
                $errors["pedidos_adicionais.{$index}.arquivos"] = 'Envie ao menos uma foto do pedido adicional.';
            }

            if ($exigirAvaliacao && blank($data['comentario'] ?? null)) {
                $errors["pedidos_adicionais.{$index}.comentario"] = 'Descreva a avaliacao do pedido adicional.';
            }
        }

        return $errors;
    }

    private function salvarArquivoPedido(
        Pedido $pedido,
        string|UploadedFile $arquivo,
        TipoArquivoPedido $tipo,
        int $usuarioId,
        ?string $descricao = null,
        string $directory = 'pedidos'
    ): void {
        [$path, $nomeOriginal] = $this->armazenarArquivoPedido($arquivo, $directory);
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
            'nome_original' => $nomeOriginal,
            'mime_type' => $mime,
            'descricao' => $descricao,
        ]);
    }

    private function armazenarArquivoPedido(string|UploadedFile $arquivo, string $directory): array
    {
        if (! $arquivo instanceof UploadedFile) {
            $path = trim(str_replace('\\', '/', $arquivo), '/');

            return [$path, basename($path)];
        }

        $nomeOriginal = $arquivo->getClientOriginalName() ?: basename($arquivo->getPathname());
        $extensao = $arquivo->getClientOriginalExtension()
            ?: $arquivo->guessExtension()
            ?: 'bin';

        $path = $arquivo->storePubliclyAs(
            trim($directory, '/'),
            ((string) Str::ulid()).'.'.$extensao,
            'public'
        );

        if (! is_string($path) || blank($path)) {
            throw new \RuntimeException('Nao foi possivel salvar o arquivo do pedido no storage publico.');
        }

        return [$path, $nomeOriginal];
    }
}
