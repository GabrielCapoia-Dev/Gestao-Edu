<?php

namespace App\Services\Dashboard;

use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\EventoCalendario;
use App\Models\ImportacaoEventoCalendario;
use App\Models\PublicoAlvo;
use App\Models\User;
use App\Services\PessoaScopeService;
use App\Services\SetorHierarchyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventoCalendarioService
{
    public function __construct(
        private readonly PublicoAlvoService $publicos,
        private readonly PessoaScopeService $scope,
        private readonly SetorHierarchyService $setores,
    ) {}

    public function criar(
        array $dados,
        array $publico,
        User $ator,
        EventoCalendarioOrigem $origem = EventoCalendarioOrigem::MANUAL,
        ?ImportacaoEventoCalendario $importacao = null,
    ): EventoCalendario {
        Gate::forUser($ator)->authorize('create', EventoCalendario::class);
        $this->validarContextoRelacionado($dados, $ator);

        if (! Gate::forUser($ator)->allows('manageAudience', EventoCalendario::class)) {
            $publico = $this->publicoPadrao();
        }

        if (! Gate::forUser($ator)->allows('publish', EventoCalendario::class)) {
            $dados['ativo'] = false;
        }

        return DB::transaction(function () use ($dados, $publico, $ator, $origem, $importacao): EventoCalendario {
            $publicoAlvo = $this->publicos->criar($ator, $publico);

            return EventoCalendario::query()->create([
                ...$dados,
                'publico_alvo_id' => $publicoAlvo->getKey(),
                'origem' => $origem,
                'ultima_importacao_id' => $importacao?->getKey(),
                'criado_por_id' => $ator->getKey(),
                'atualizado_por_id' => $ator->getKey(),
            ]);
        });
    }

    public function atualizar(
        EventoCalendario $evento,
        array $dados,
        ?array $publico,
        User $ator,
        ?ImportacaoEventoCalendario $importacao = null,
    ): EventoCalendario {
        Gate::forUser($ator)->authorize('update', $evento);
        $this->validarContextoRelacionado($dados, $ator);

        if (! Gate::forUser($ator)->allows('publish', $evento)) {
            Arr::forget($dados, 'ativo');
        }

        return DB::transaction(function () use ($evento, $dados, $publico, $ator, $importacao): EventoCalendario {
            if ($publico !== null) {
                Gate::forUser($ator)->authorize('manageAudience', $evento);
                $this->publicos->atualizar($evento->publicoAlvo, $ator, $publico);
            }

            $evento->fill([
                ...$dados,
                'atualizado_por_id' => $ator->getKey(),
                'ultima_importacao_id' => $importacao?->getKey() ?? $evento->ultima_importacao_id,
            ])->save();

            return $evento->refresh();
        });
    }

    public function duplicar(EventoCalendario $evento, User $ator): EventoCalendario
    {
        Gate::forUser($ator)->authorize('duplicate', $evento);

        $dados = Arr::except($evento->getAttributes(), [
            'id', 'publico_alvo_id', 'created_at', 'updated_at', 'deleted_at',
            'criado_por_id', 'atualizado_por_id', 'excluido_por_id',
            'fonte_externa', 'identificador_externo', 'ultima_importacao_id',
        ]);
        $dados['titulo'] = mb_substr('Cópia de '.$evento->titulo, 0, 160);
        $dados['ativo'] = false;

        return $this->criar(
            $dados,
            $this->payloadDoPublico($evento->publicoAlvo),
            $ator,
            EventoCalendarioOrigem::MANUAL,
        );
    }

    public function alternarPublicacao(EventoCalendario $evento, User $ator): EventoCalendario
    {
        Gate::forUser($ator)->authorize('publish', $evento);

        $evento->forceFill([
            'ativo' => ! $evento->ativo,
            'atualizado_por_id' => $ator->getKey(),
        ])->save();

        return $evento->refresh();
    }

    /** @return array<string, mixed> */
    public function payloadDoPublico(PublicoAlvo $publico): array
    {
        $publico->loadMissing([
            'usuarios:id', 'roles:id', 'permissoes:id', 'funcoesAdministrativas:id',
            'escolas:id', 'setores:id',
        ]);

        return [
            'modo_correspondencia' => $publico->modo_correspondencia?->value ?? (string) $publico->modo_correspondencia,
            'todos_usuarios' => (bool) $publico->todos_usuarios,
            'usuarios_ids' => $publico->usuarios->pluck('id')->all(),
            'roles_ids' => $publico->roles->pluck('id')->all(),
            'permissoes_ids' => $publico->permissoes->pluck('id')->all(),
            'funcoes_administrativas_ids' => $publico->funcoesAdministrativas->pluck('id')->all(),
            'escolas_ids' => $publico->escolas->pluck('id')->all(),
            'setores_ids' => $publico->setores->pluck('id')->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function publicoPadrao(): array
    {
        return [
            'modo_correspondencia' => 'qualquer',
            'todos_usuarios' => true,
            'usuarios_ids' => [],
            'roles_ids' => [],
            'permissoes_ids' => [],
            'funcoes_administrativas_ids' => [],
            'escolas_ids' => [],
            'setores_ids' => [],
        ];
    }

    public function validarContextoRelacionado(array $dados, User $ator): void
    {
        $escolaId = filled($dados['escola_id'] ?? null) ? (int) $dados['escola_id'] : null;
        $setorId = filled($dados['setor_id'] ?? null) ? (int) $dados['setor_id'] : null;

        if ($escolaId && ! \App\Models\Escola::query()->whereKey($escolaId)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages([
                'escola_id' => 'A escola relacionada não existe ou está inativa.',
            ]);
        }

        if ($setorId && ! \App\Models\Setor::query()->whereKey($setorId)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages([
                'setor_id' => 'O setor relacionado não existe ou está inativo.',
            ]);
        }

        if ($escolaId && ! $this->scope->canAccessEscola($ator, $escolaId)) {
            throw ValidationException::withMessages([
                'escola_id' => 'Selecione uma escola pertencente ao seu contexto de acesso.',
            ]);
        }

        if ($setorId && ! $this->scope->canAccessSetor($ator, $setorId)) {
            throw ValidationException::withMessages([
                'setor_id' => 'Selecione um setor pertencente ao seu contexto de acesso.',
            ]);
        }

        if ($escolaId && $setorId) {
            $setorDaEscola = \App\Models\Escola::query()->whereKey($escolaId)->value('setor_id');

            if (! $setorDaEscola || ! $this->setores->isAncestorOf($setorId, (int) $setorDaEscola)) {
                throw ValidationException::withMessages([
                    'setor_id' => 'O setor relacionado deve conter a escola selecionada em sua hierarquia.',
                ]);
            }
        }
    }
}
