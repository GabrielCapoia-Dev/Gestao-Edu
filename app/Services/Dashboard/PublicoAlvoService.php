<?php

namespace App\Services\Dashboard;

use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Role;
use App\Models\Setor;
use App\Models\User;
use App\Support\Dashboard\DashboardUserContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicoAlvoService
{
    private const TARGET_FIELDS = [
        'usuarios' => 'usuarios_ids',
        'roles' => 'roles_ids',
        'permissoes' => 'permissoes_ids',
        'funcoesAdministrativas' => 'funcoes_administrativas_ids',
        'escolas' => 'escolas_ids',
        'setores' => 'setores_ids',
    ];

    public function __construct(
        private readonly DashboardUserContextFactory $contextFactory,
    ) {}

    public function criar(User $ator, array $dados): PublicoAlvo
    {
        $dados = $this->validar($ator, $dados);

        return DB::transaction(function () use ($ator, $dados): PublicoAlvo {
            $publicoAlvo = PublicoAlvo::query()->create([
                'modo_correspondencia' => $dados['modo_correspondencia'],
                'todos_usuarios' => $dados['todos_usuarios'],
                'escopo_global' => $dados['escopo_global'],
            ]);

            return $this->sincronizarRelacoes($publicoAlvo, $ator, $dados);
        });
    }

    public function atualizar(PublicoAlvo $publicoAlvo, User $ator, array $dados): PublicoAlvo
    {
        if (! $this->podeGerenciar($publicoAlvo, $ator)) {
            throw new AuthorizationException('Você não pode alterar este público-alvo.');
        }

        $dados = $this->validar($ator, $dados);

        return DB::transaction(function () use ($publicoAlvo, $ator, $dados): PublicoAlvo {
            $publicoAlvo->update([
                'modo_correspondencia' => $dados['modo_correspondencia'],
                'todos_usuarios' => $dados['todos_usuarios'],
                'escopo_global' => $dados['escopo_global'],
            ]);

            return $this->sincronizarRelacoes($publicoAlvo, $ator, $dados);
        });
    }

    /**
     * @return array{
     *     modo_correspondencia: string,
     *     todos_usuarios: bool,
     *     escopo_global: bool,
     *     usuarios_ids: list<int>,
     *     roles_ids: list<int>,
     *     permissoes_ids: list<int>,
     *     funcoes_administrativas_ids: list<int>,
     *     escolas_ids: list<int>,
     *     setores_ids: list<int>
     * }
     */
    public function validar(User $ator, array $dados): array
    {
        $contexto = $this->contexto($ator);
        $modo = $dados['modo_correspondencia'] ?? PublicoAlvoModoCorrespondencia::Qualquer;
        $modo = $modo instanceof PublicoAlvoModoCorrespondencia
            ? $modo
            : PublicoAlvoModoCorrespondencia::tryFrom((string) $modo);

        if (! $modo) {
            throw ValidationException::withMessages([
                'modo_correspondencia' => 'Selecione um modo de correspondência válido.',
            ]);
        }

        $normalizados = [
            'modo_correspondencia' => $modo->value,
            'todos_usuarios' => filter_var(
                $dados['todos_usuarios'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            ),
            'escopo_global' => $contexto->escopoGlobal,
        ];

        foreach (self::TARGET_FIELDS as $field) {
            $normalizados[$field] = $this->normalizarIds($dados[$field] ?? []);
        }

        $temCriterios = collect(self::TARGET_FIELDS)
            ->contains(fn (string $field): bool => $normalizados[$field] !== []);

        if ($normalizados['todos_usuarios'] && $temCriterios) {
            throw ValidationException::withMessages([
                'todos_usuarios' => 'Todos os usuários não pode ser combinado com outros critérios.',
            ]);
        }

        if (! $normalizados['todos_usuarios'] && ! $temCriterios) {
            throw ValidationException::withMessages([
                'publico_alvo' => 'Selecione ao menos um critério de público-alvo.',
            ]);
        }

        if (! $contexto->escopoGlobal && $contexto->escolaIds === [] && $contexto->setorVisivelIds === []) {
            throw ValidationException::withMessages([
                'publico_alvo' => 'Seu usuário não possui escola ou setor autorizado para definir este público-alvo.',
            ]);
        }

        $this->validarReferencias($normalizados);
        $this->validarEscopoDoAtor($ator, $contexto, $normalizados);

        return $normalizados;
    }

    public function corresponde(
        PublicoAlvo $publicoAlvo,
        User|DashboardUserContext $usuario,
    ): bool {
        if (! $publicoAlvo->exists) {
            return false;
        }

        return $this->queryPara($usuario)
            ->whereKey($publicoAlvo->getKey())
            ->exists();
    }

    public function queryPara(User|DashboardUserContext $usuario): Builder
    {
        $contexto = $this->contexto($usuario);

        return PublicoAlvo::query()
            ->where(fn (Builder $query): Builder => $this->aplicarEnvelope($query, $contexto))
            ->where(fn (Builder $query): Builder => $this->aplicarCorrespondencia($query, $contexto));
    }

    public function podeGerenciar(
        PublicoAlvo $publicoAlvo,
        User|DashboardUserContext $ator,
    ): bool {
        if (! $publicoAlvo->exists) {
            return false;
        }

        $contexto = $this->contexto($ator);

        if ($contexto->escopoGlobal) {
            return true;
        }

        if ((bool) $publicoAlvo->escopo_global) {
            return false;
        }

        if (
            $publicoAlvo->relationLoaded('escopoEscolas')
            && $publicoAlvo->relationLoaded('escopoSetores')
        ) {
            $escolaIds = $this->normalizarIds($publicoAlvo->escopoEscolas->modelKeys());

            if ($escolaIds !== []) {
                return array_diff($escolaIds, $contexto->escolaIds) === [];
            }

            $setorIds = $this->normalizarIds($publicoAlvo->escopoSetores->modelKeys());

            return $setorIds !== []
                && array_diff($setorIds, $contexto->setorVisivelIds) === [];
        }

        return $this->queryGerenciavelPor($contexto)
            ->whereKey($publicoAlvo->getKey())
            ->exists();
    }

    public function queryGerenciavelPor(User|DashboardUserContext $ator): Builder
    {
        $contexto = $this->contexto($ator);

        if ($contexto->escopoGlobal) {
            return PublicoAlvo::query();
        }

        return PublicoAlvo::query()
            ->where('escopo_global', false)
            ->where(function (Builder $envelope) use ($contexto): void {
                $envelope
                    ->where(function (Builder $porEscola) use ($contexto): void {
                        $porEscola
                            ->whereHas('escopoEscolas')
                            ->whereDoesntHave(
                                'escopoEscolas',
                                fn (Builder $escolas): Builder => $this->whereIdsFora(
                                    $escolas,
                                    $contexto->escolaIds,
                                ),
                            );
                    })
                    ->orWhere(function (Builder $porSetor) use ($contexto): void {
                        $porSetor
                            ->whereDoesntHave('escopoEscolas')
                            ->whereHas('escopoSetores')
                            ->whereDoesntHave(
                                'escopoSetores',
                                fn (Builder $setores): Builder => $this->whereIdsFora(
                                    $setores,
                                    $contexto->setorVisivelIds,
                                ),
                            );
                    });
            });
    }

    /** @throws AuthorizationException */
    public function duplicar(PublicoAlvo $origem, User $ator): PublicoAlvo
    {
        if (! $this->podeGerenciar($origem, $ator)) {
            throw new AuthorizationException('Você não pode duplicar este público-alvo.');
        }

        $origem->loadMissing(array_keys(self::TARGET_FIELDS));

        $dados = [
            'modo_correspondencia' => $origem->modo_correspondencia,
            'todos_usuarios' => (bool) $origem->todos_usuarios,
        ];

        foreach (self::TARGET_FIELDS as $relation => $field) {
            $dados[$field] = $origem->{$relation}->modelKeys();
        }

        return $this->criar($ator, $dados);
    }

    public function aplicarEscopo(
        Builder $query,
        User|DashboardUserContext $usuario,
        string $foreignKey = 'publico_alvo_id',
    ): Builder {
        $column = str_contains($foreignKey, '.')
            ? $foreignKey
            : $query->getModel()->qualifyColumn($foreignKey);

        return $query->whereIn(
            $column,
            $this->queryPara($usuario)->select('publicos_alvo.id'),
        );
    }

    private function sincronizarRelacoes(
        PublicoAlvo $publicoAlvo,
        User $ator,
        array $dados,
    ): PublicoAlvo {
        foreach (self::TARGET_FIELDS as $relation => $field) {
            $publicoAlvo->{$relation}()->sync($dados[$field]);
        }

        $contexto = $this->contexto($ator);

        $publicoAlvo->escopoEscolas()->sync(
            $contexto->escopoGlobal ? [] : $contexto->escolaIds,
        );
        $publicoAlvo->escopoSetores()->sync(
            $contexto->escopoGlobal ? [] : $contexto->setorVisivelIds,
        );

        return $publicoAlvo->fresh([
            ...array_keys(self::TARGET_FIELDS),
            'escopoEscolas',
            'escopoSetores',
        ]);
    }

    private function validarReferencias(array $dados): void
    {
        $this->assertIdsExistem(User::query(), $dados['usuarios_ids'], 'usuarios_ids');
        $this->assertIdsExistem(
            Role::query()->where('guard_name', 'web'),
            $dados['roles_ids'],
            'roles_ids',
        );
        $this->assertIdsExistem(
            Permission::query()->where('guard_name', 'web'),
            $dados['permissoes_ids'],
            'permissoes_ids',
        );
        $this->assertIdsExistem(
            FuncaoAdministrativa::query()->where('ativo', true),
            $dados['funcoes_administrativas_ids'],
            'funcoes_administrativas_ids',
        );
        $this->assertIdsExistem(
            Escola::query()->where('ativo', true),
            $dados['escolas_ids'],
            'escolas_ids',
        );
        $this->assertIdsExistem(
            Setor::query()->where('ativo', true),
            $dados['setores_ids'],
            'setores_ids',
        );
    }

    private function validarEscopoDoAtor(
        User $ator,
        DashboardUserContext $contextoAtor,
        array $dados,
    ): void {
        if ($contextoAtor->escopoGlobal) {
            return;
        }

        $this->assertSubconjunto(
            $dados['escolas_ids'],
            $contextoAtor->escolaIds,
            'escolas_ids',
            'Selecione apenas escolas vinculadas ao seu usuário.',
        );
        $this->assertSubconjunto(
            $dados['setores_ids'],
            $contextoAtor->setorVisivelIds,
            'setores_ids',
            'Selecione apenas setores permitidos para o seu usuário.',
        );

        if ($dados['usuarios_ids'] === []) {
            return;
        }

        $usuarios = User::query()
            ->whereKey($dados['usuarios_ids'])
            ->get();

        $foraDoEscopo = $usuarios->contains(function (User $usuario) use ($contextoAtor): bool {
            $contextoUsuario = $this->contexto($usuario);

            return ! $this->contextoDentroDoEnvelope($contextoUsuario, $contextoAtor);
        });

        if ($foraDoEscopo) {
            throw ValidationException::withMessages([
                'usuarios_ids' => 'Selecione apenas usuários pertencentes ao seu escopo de escola ou setor.',
            ]);
        }
    }

    private function contextoDentroDoEnvelope(
        DashboardUserContext $usuario,
        DashboardUserContext $ator,
    ): bool {
        if ($ator->escolaIds !== []) {
            return array_intersect($usuario->escolaIds, $ator->escolaIds) !== [];
        }

        return array_intersect($usuario->setorIds, $ator->setorVisivelIds) !== [];
    }

    private function aplicarEnvelope(
        Builder $query,
        DashboardUserContext $contexto,
    ): Builder {
        $query->where('escopo_global', true);

        if ($contexto->escolaIds === [] && $contexto->setorIds === []) {
            return $query;
        }

        return $query->orWhere(function (Builder $local) use ($contexto): void {
            $local
                ->where('escopo_global', false)
                ->where(function (Builder $envelope) use ($contexto): void {
                    if ($contexto->escolaIds !== []) {
                        $envelope->where(function (Builder $porEscola) use ($contexto): void {
                            $porEscola->whereHas(
                                'escopoEscolas',
                                fn (Builder $escolas): Builder => $escolas->whereKey($contexto->escolaIds),
                            );
                        });
                    }

                    if ($contexto->setorIds !== []) {
                        $method = $contexto->escolaIds === [] ? 'where' : 'orWhere';

                        $envelope->{$method}(function (Builder $porSetor) use ($contexto): void {
                            $porSetor
                                ->whereDoesntHave('escopoEscolas')
                                ->whereHas(
                                    'escopoSetores',
                                    fn (Builder $setores): Builder => $setores->whereKey($contexto->setorIds),
                                );
                        });
                    }
                });
        });
    }

    private function aplicarCorrespondencia(
        Builder $query,
        DashboardUserContext $contexto,
    ): Builder {
        return $query
            ->where('todos_usuarios', true)
            ->orWhere(function (Builder $segmentado) use ($contexto): void {
                $segmentado
                    ->where('todos_usuarios', false)
                    ->where(function (Builder $modos) use ($contexto): void {
                        $modos
                            ->where(function (Builder $qualquer) use ($contexto): void {
                                $qualquer
                                    ->where(
                                        'modo_correspondencia',
                                        PublicoAlvoModoCorrespondencia::Qualquer->value,
                                    )
                                    ->where(fn (Builder $matches): Builder => $this->aplicarQualquerMatch(
                                        $matches,
                                        $contexto,
                                    ));
                            })
                            ->orWhere(function (Builder $todos) use ($contexto): void {
                                $todos
                                    ->where(
                                        'modo_correspondencia',
                                        PublicoAlvoModoCorrespondencia::Todos->value,
                                    )
                                    ->where(fn (Builder $configurados): Builder => $this->aplicarTemCriterio(
                                        $configurados,
                                    ));

                                $this->aplicarTodosMatches($todos, $contexto);
                            });
                    });
            });
    }

    private function aplicarQualquerMatch(
        Builder $query,
        DashboardUserContext $contexto,
    ): Builder {
        foreach ($this->matchingIds($contexto) as $index => $item) {
            $method = $index === 0 ? 'whereHas' : 'orWhereHas';
            $query->{$method}(
                $item['relation'],
                fn (Builder $related): Builder => $this->whereIds($related, $item['ids']),
            );
        }

        return $query;
    }

    private function aplicarTemCriterio(Builder $query): Builder
    {
        foreach (array_keys(self::TARGET_FIELDS) as $index => $relation) {
            $method = $index === 0 ? 'whereHas' : 'orWhereHas';
            $query->{$method}($relation);
        }

        return $query;
    }

    private function aplicarTodosMatches(
        Builder $query,
        DashboardUserContext $contexto,
    ): void {
        foreach ($this->matchingIds($contexto) as $item) {
            $query->where(function (Builder $categoria) use ($item): void {
                $categoria
                    ->whereDoesntHave($item['relation'])
                    ->orWhereHas(
                        $item['relation'],
                        fn (Builder $related): Builder => $this->whereIds($related, $item['ids']),
                    );
            });
        }
    }

    /** @return list<array{relation: string, ids: list<int>}> */
    private function matchingIds(DashboardUserContext $contexto): array
    {
        return [
            ['relation' => 'usuarios', 'ids' => [$contexto->userId]],
            ['relation' => 'roles', 'ids' => $contexto->roleIds],
            ['relation' => 'permissoes', 'ids' => $contexto->permissionIds],
            ['relation' => 'funcoesAdministrativas', 'ids' => $contexto->funcaoAdministrativaIds],
            ['relation' => 'escolas', 'ids' => $contexto->escolaIds],
            ['relation' => 'setores', 'ids' => $contexto->setorIds],
        ];
    }

    private function whereIds(Builder $query, array $ids): Builder
    {
        return $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereKey($ids);
    }

    private function whereIdsFora(Builder $query, array $ids): Builder
    {
        return $ids === []
            ? $query
            : $query->whereKeyNot($ids);
    }

    private function assertIdsExistem(Builder $query, array $ids, string $field): void
    {
        if ($ids === []) {
            return;
        }

        $existentes = (clone $query)
            ->whereKey($ids)
            ->pluck($query->getModel()->getQualifiedKeyName())
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (array_diff($ids, $existentes) === []) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Uma ou mais opções selecionadas são inválidas ou estão inativas.',
        ]);
    }

    private function assertSubconjunto(
        array $selecionados,
        array $permitidos,
        string $field,
        string $message,
    ): void {
        if (array_diff($selecionados, $permitidos) === []) {
            return;
        }

        throw ValidationException::withMessages([$field => $message]);
    }

    /** @return list<int> */
    private function normalizarIds(mixed $ids): array
    {
        return collect(is_array($ids) ? $ids : [$ids])
            ->filter(fn ($id): bool => filled($id) && is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function contexto(User|DashboardUserContext $usuario): DashboardUserContext
    {
        if ($usuario instanceof DashboardUserContext) {
            return $usuario;
        }

        return $this->contextFactory->make($usuario);
    }
}
