<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoHistorico;
use App\Models\TipoStatus;
use App\Models\Setor;
use App\Models\User;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\TipoArquivoPedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PedidoService
{
    /*
    |--------------------------------------------------------------------------
    | PERMISSÕES
    |--------------------------------------------------------------------------
    */

    public function ehAdmin(?User $user): bool
    {
        return $user?->hasRole('Admin') ?? false;
    }

    public function podeGerenciarPedidos(?User $user): bool
    {
        return $user?->hasPermissionTo('Editar Pedidos') ?? false;
    }

    public function podeCriarPedidos(?User $user): bool
    {
        return $user?->hasPermissionTo('Criar Pedidos') ?? false;
    }

    /**
     * Verifica se o usuário pode gerenciar um registro específico (visibilidade do botão "Gerenciar").
     */
    public function podeGerenciarRegistro(Pedido $pedido, ?User $user): bool
    {
        if (! $user) return false;

        if (! $user->hasPermissionTo('Editar Pedidos')) return false;

        if ($pedido->tipoStatus?->finaliza_pedido || $pedido->tipoStatus?->cancela_pedido) {
            return false;
        }

        // Sem setor → pode gerenciar tudo
        if (! $user->setor_id) return true;

        // Com setor → somente pedidos do mesmo setor
        return $pedido->setor_id === $user->setor_id;
    }

    /*
    |--------------------------------------------------------------------------
    | BADGE
    |--------------------------------------------------------------------------
    */

    public function contarPedidosNovos(?User $user): ?string
    {
        if (! $this->podeGerenciarPedidos($user)) {
            return null;
        }

        if ($user?->setor?->nome === 'Obras') {
            $statusBase = TipoStatus::where('ativo', true)
                ->where('nome', 'Encaminhado ao Setor')
                ->first();
        } else {
            $statusBase = TipoStatus::where('ativo', true)
                ->where('finaliza_pedido', false)
                ->where('cancela_pedido', false)
                ->first();
        }

        if (! $statusBase) return null;

        $query = Pedido::where('ativo', true)
            ->where('tipo_status_id', $statusBase->id);

        if (! $this->ehAdmin($user) && $user?->id_escola) {
            $query->where('escola_id', $user->id_escola);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY BASE POR PERFIL
    |--------------------------------------------------------------------------
    */

    public function queryPorPerfil(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('ativo', true);

        if ($this->ehAdmin($user)) {
            return $query;
        }

        if ($user->id_escola) {
            $query->where('escola_id', $user->id_escola);
        }

        return $query;
    }

    public function queryTabela(?User $user): Builder
    {
        $query = Pedido::query()
            ->with('ultimoHistorico')
            ->where('ativo', true);

        if (! $user) return $query->nenhum();

        if ($user->hasRole('Admin') || $user->hasPermissionTo('Listar Todos os Pedidos')) {
            return $query;
        }

        if ($user->id_escola) {
            return $query->where('escola_id', $user->id_escola);
        }

        return $query->nenhum();
    }

    public function queryTabTodos(?User $user): Builder
    {
        $query = Pedido::query()
            ->with('ultimoHistorico')
            ->where('ativo', true);

        if (! $user) return $query->nenhum();

        if ($user->hasRole('Admin') || $user->hasPermissionTo('Listar Todos os Pedidos')) {
            return $query->orderByDesc('updated_at');
        }

        if ($user->id_escola) {
            return $query->where('escola_id', $user->id_escola)->orderByDesc('updated_at');
        }

        return $query->nenhum();
    }

    /*
    |--------------------------------------------------------------------------
    | CRIAÇÃO
    |--------------------------------------------------------------------------
    */

    public function criarPedido(array $data, User $solicitante): Pedido
    {
        $statusInicial = TipoStatus::where('nome', 'Em Aberto')->firstOrFail();

        $setorEducacao = Setor::where('nome', 'Educação')
            ->where('ativo', true)
            ->firstOrFail();

        $pedido = Pedido::create([
            'tipo_manutencao_id' => $data['tipo_manutencao_id'],
            'descricao_pedido'   => $data['descricao_pedido'],
            'nome_solicitante'   => $data['nome_solicitante'],
            'nivel_prioridade'   => NivelEmergenciaPedido::INDEFINIDO,
            'solicitante_id'     => $solicitante->id,
            'escola_id'          => $solicitante->id_escola,
            'tipo_status_id'     => $statusInicial->id,
            'setor_id'           => $setorEducacao->id,
            'data_solicitacao'   => now(),
            'ativo'              => true,
        ]);

        if (filled($data['arquivos'])) {
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
    }

    /*
    |--------------------------------------------------------------------------
    | ALTERAÇÃO DE STATUS
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

    /*
    |--------------------------------------------------------------------------
    | ASSUMIR PEDIDO (ação "Gerenciar")
    |--------------------------------------------------------------------------
    */

    public function assumirPedido(Pedido $pedido, User $usuario): void
    {
        $statusEmAberto    = TipoStatus::where('nome', 'Em Aberto')->first();
        $statusEncaminhado = TipoStatus::where('nome', 'Encaminhado ao Setor')->first();
        $statusAnalise     = TipoStatus::where('nome', 'Em Análise')->first();

        if (! $statusAnalise) return;

        $podeIrParaAnalise = false;

        if ($statusEmAberto && $pedido->tipo_status_id === $statusEmAberto->id) {
            $podeIrParaAnalise = true;
        }

        if (
            $statusEncaminhado &&
            $pedido->tipo_status_id === $statusEncaminhado->id &&
            $usuario->setor?->nome === 'Obras'
        ) {
            $podeIrParaAnalise = true;
        }

        if ($podeIrParaAnalise) {
            $this->alterarStatus(
                $pedido,
                $statusAnalise,
                $usuario,
                'Pedido assumido para análise por ' . $usuario->name . '.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AVALIAR PEDIDO (ação "Avaliar")
    |--------------------------------------------------------------------------
    */

    public function avaliarPedido(Pedido $pedido, array $data, User $usuario): void
    {
        $nota = (int) $data['valor'];

        $statusConcluido = TipoStatus::where('ativo', true)->where('nome', 'Concluído')->firstOrFail();
        $statusReaberto  = TipoStatus::where('ativo', true)->where('nome', 'Reaberto')->firstOrFail();

        $pedido->feedbacks()->create([
            'valor'     => $nota,
            'descricao' => $data['descricao'] ?? null,
        ]);

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

        $novoStatus = $nota === 1 ? $statusReaberto : $statusConcluido;

        $this->alterarStatus(
            $pedido,
            $novoStatus,
            $usuario,
            $nota === 1
                ? 'Pedido reaberto automaticamente após avaliação com nota 1.'
                : 'Pedido concluído e avaliado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HISTÓRICO
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
            'pedido_id'           => $pedido->id,
            'status_anterior_id'  => $statusAnteriorId,
            'status_novo_id'      => $statusNovoId,
            'usuario_id'          => $usuario->id,
            'setor_id'            => $pedido->setor_id,
            'descricao_alteracao' => $descricao,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ARQUIVOS
    |--------------------------------------------------------------------------
    */

    private function salvarArquivoPedido(
        Pedido $pedido,
        string $path,
        TipoArquivoPedido $tipo,
        int $usuarioId,
        ?string $descricao = null
    ): void {
        $mime = Storage::mimeType("public/{$path}");

        $pedido->arquivos()->create([
            'usuario_id'    => $usuarioId,
            'tipo_arquivo'  => $tipo,
            'caminho'       => $path,
            'nome_original' => basename($path),
            'mime_type'     => $mime,
            'descricao'     => $descricao,
        ]);
    }
}
