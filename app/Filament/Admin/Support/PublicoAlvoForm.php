<?php

namespace App\Filament\Admin\Support;

use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use App\Models\PublicoAlvo;
use App\Models\User;
use App\Services\Dashboard\PublicoAlvoOptionsService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;

final class PublicoAlvoForm
{
    public const STATE_PATH = 'publico_alvo';

    /**
     * Campos compartilhados pelos avisos e eventos manuais.
     * As opcoes de usuarios, escolas e setores ja chegam limitadas ao contexto
     * do responsavel. A validacao definitiva continua no PublicoAlvoService.
     *
     * @return array<int, Section>
     */
    public static function schema(?User $user, bool $visivel = true): array
    {
        $options = app(PublicoAlvoOptionsService::class);
        $escolas = $user ? $options->escolas($user) : [];
        $setores = $user ? $options->setores($user) : [];

        return [
            Section::make('Público-alvo')
                ->description('Combine critérios para definir quem receberá este conteúdo. O alcance nunca ultrapassa seu próprio contexto de acesso.')
                ->statePath(self::STATE_PATH)
                ->columns(2)
                ->visible($visivel)
                ->dehydrated($visivel)
                ->schema([
                    Toggle::make('todos_usuarios')
                        ->label('Todos os usuários do meu escopo')
                        ->helperText('Para usuários com acesso global, inclui todo o sistema. Para os demais, respeita escolas e setores autorizados.')
                        ->default(true)
                        ->live()
                        ->columnSpanFull(),

                    Select::make('modo_correspondencia')
                        ->label('Combinação entre categorias')
                        ->options(PublicoAlvoModoCorrespondencia::options())
                        ->default(PublicoAlvoModoCorrespondencia::Qualquer->value)
                        ->native(false)
                        ->required()
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios'))
                        ->columnSpanFull(),

                    Select::make('usuarios_ids')
                        ->label('Usuários específicos')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $user
                            ? $options->buscarUsuarios($user, $search)
                            : [])
                        ->getOptionLabelsUsing(fn (array $values): array => $user
                            ? $options->rotulosUsuarios($user, $values)
                            : [])
                        ->helperText('Digite ao menos parte do nome ou e-mail para pesquisar.')
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios')),

                    Select::make('roles_ids')
                        ->label('Níveis de acesso')
                        ->options($options->roles())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios')),

                    Select::make('permissoes_ids')
                        ->label('Permissões')
                        ->options($options->permissoes())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios')),

                    Select::make('funcoes_administrativas_ids')
                        ->label('Cargos e funções administrativas')
                        ->options($options->funcoesAdministrativas())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios')),

                    Select::make('escolas_ids')
                        ->label('Escolas')
                        ->options($escolas)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios')),

                    Select::make('setores_ids')
                        ->label('Setores')
                        ->options($setores)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => ! (bool) $get('todos_usuarios')),
                ]),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function separar(array $data): array
    {
        $publicoAlvo = Arr::pull($data, self::STATE_PATH, []);

        return [$data, self::normalizar(is_array($publicoAlvo) ? $publicoAlvo : [])];
    }

    /** @return array<string, mixed> */
    public static function normalizar(array $data): array
    {
        $todosUsuarios = (bool) ($data['todos_usuarios'] ?? true);
        $modo = $data['modo_correspondencia'] ?? PublicoAlvoModoCorrespondencia::Qualquer->value;

        if ($modo instanceof PublicoAlvoModoCorrespondencia) {
            $modo = $modo->value;
        }

        if (! PublicoAlvoModoCorrespondencia::tryFrom((string) $modo)) {
            $modo = PublicoAlvoModoCorrespondencia::Qualquer->value;
        }

        $payload = [
            'modo_correspondencia' => (string) $modo,
            'todos_usuarios' => $todosUsuarios,
            'usuarios_ids' => self::ids($data['usuarios_ids'] ?? []),
            'roles_ids' => self::ids($data['roles_ids'] ?? []),
            'permissoes_ids' => self::ids($data['permissoes_ids'] ?? []),
            'funcoes_administrativas_ids' => self::ids($data['funcoes_administrativas_ids'] ?? []),
            'escolas_ids' => self::ids($data['escolas_ids'] ?? []),
            'setores_ids' => self::ids($data['setores_ids'] ?? []),
        ];

        if ($todosUsuarios) {
            foreach (self::criterionKeys() as $key) {
                $payload[$key] = [];
            }
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function paraFormulario(?PublicoAlvo $publicoAlvo): array
    {
        if (! $publicoAlvo) {
            return self::normalizar([]);
        }

        $publicoAlvo->loadMissing([
            'usuarios:id',
            'roles:id',
            'permissoes:id',
            'funcoesAdministrativas:id',
            'escolas:id',
            'setores:id',
        ]);

        return self::normalizar([
            'modo_correspondencia' => $publicoAlvo->modo_correspondencia,
            'todos_usuarios' => $publicoAlvo->todos_usuarios,
            'usuarios_ids' => $publicoAlvo->usuarios->pluck('id')->all(),
            'roles_ids' => $publicoAlvo->roles->pluck('id')->all(),
            'permissoes_ids' => $publicoAlvo->permissoes->pluck('id')->all(),
            'funcoes_administrativas_ids' => $publicoAlvo->funcoesAdministrativas->pluck('id')->all(),
            'escolas_ids' => $publicoAlvo->escolas->pluck('id')->all(),
            'setores_ids' => $publicoAlvo->setores->pluck('id')->all(),
        ]);
    }

    /** @return list<string> */
    private static function criterionKeys(): array
    {
        return [
            'usuarios_ids',
            'roles_ids',
            'permissoes_ids',
            'funcoes_administrativas_ids',
            'escolas_ids',
            'setores_ids',
        ];
    }

    /** @return list<int> */
    private static function ids(mixed $ids): array
    {
        return collect(is_iterable($ids) ? $ids : [])
            ->filter(fn ($id): bool => filled($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

}
