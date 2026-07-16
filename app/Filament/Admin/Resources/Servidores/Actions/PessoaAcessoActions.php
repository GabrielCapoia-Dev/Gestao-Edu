<?php

namespace App\Filament\Admin\Resources\Servidores\Actions;

use App\Models\Servidor;
use App\Models\User;
use App\Services\PessoaUsuarioService;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PessoaAcessoActions
{
    /** @return array<int, Action> */
    public static function recordActions(): array
    {
        return [
            Action::make('criar_acesso')
                ->label('Criar acesso')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->visible(fn (Servidor $record): bool => ! $record->user
                    && Gate::allows('createUserAccess', $record)
                    && Gate::allows('create', User::class))
                ->slideOver()
                ->modalHeading('Criar acesso ao sistema')
                ->modalDescription(fn (Servidor $record): string => "Crie ou vincule a conta de {$record->nome} usando o e-mail da ficha.")
                ->schema([
                    TextInput::make('senha')
                        ->label('Senha inicial')
                        ->default('Mudar@1234')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(30)
                        ->rules([PasswordRule::min(8)->mixedCase()->numbers()->symbols()]),
                    Toggle::make('email_approved')
                        ->label('Liberar acesso imediatamente')
                        ->default(true)
                        ->visible(fn (): bool => Gate::allows('toggleEmailApproval', [User::class, null, 'create'])),
                ])
                ->action(function (Servidor $record, array $data): void {
                    app(PessoaUsuarioService::class)->criarOuVincularAcesso(
                        $record,
                        Auth::user(),
                        (string) ($data['senha'] ?? ''),
                        (bool) ($data['email_approved'] ?? false),
                    );

                    Notification::make()
                        ->title('Acesso criado')
                        ->body('A conta foi vinculada à pessoa e recebeu os níveis funcionais do cargo.')
                        ->success()
                        ->send();
                }),

            Action::make('gerenciar_acesso')
                ->label('Gerenciar acesso')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->visible(fn (Servidor $record): bool => $record->user
                    && Gate::allows('viewAny', User::class)
                    && Gate::allows('applyPermissions', $record->user))
                ->slideOver()
                ->modalHeading('Níveis e permissões de acesso')
                ->modalDescription(fn (Servidor $record): string => "{$record->nome} • {$record->user?->email}")
                ->fillForm(fn (Servidor $record): array => [
                    'roles' => app(UserService::class)->idsNiveisAdicionais($record->user),
                    'email_approved' => (bool) $record->user?->email_approved,
                ])
                ->schema(function (Servidor $record): array {
                    $service = app(UserService::class);
                    $user = $record->user;

                    if (! $user) {
                        return [];
                    }

                    return [
                        Toggle::make('email_approved')
                            ->label('Acesso liberado')
                            ->visible(fn (): bool => Gate::allows('toggleEmailApproval', [$user, 'table'])),
                        Select::make('roles')
                            ->label('Níveis adicionais')
                            ->helperText('O nível funcional do cargo é preservado e não pode ser removido aqui.')
                            ->options(fn (): array => $service->opcoesDeRolesParaSelect(Auth::user()))
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Components\Group::make()
                            ->schema(fn (Get $get): array => $service->checkboxesPermissoesComEstado(
                                $user->loadMissing('roles.permissions'),
                                $get,
                                Auth::user(),
                            )),
                    ];
                })
                ->action(function (Servidor $record, array $data): void {
                    $user = $record->user;

                    if (! $user) {
                        throw new AuthorizationException('A pessoa não possui conta de acesso.');
                    }

                    $service = app(UserService::class);
                    $service->sincronizarNiveisAdicionais(
                        $user,
                        $service->idsDeRolesSelecionadas($data),
                        operador: Auth::user(),
                    );
                    $service->sincronizarPermissoesDiretas(
                        $user->fresh(),
                        $service->permissoesSelecionadas($data),
                        operador: Auth::user(),
                    );

                    if (array_key_exists('email_approved', $data)) {
                        $service->definirAprovacao(
                            $user->fresh(),
                            (bool) $data['email_approved'],
                            Auth::user(),
                        );
                    }

                    Notification::make()
                        ->title('Acesso atualizado')
                        ->success()
                        ->send();
                }),

            Action::make('redefinir_senha')
                ->label('Redefinir senha')
                ->icon('heroicon-o-key')
                ->color('danger')
                ->visible(fn (Servidor $record): bool => $record->user
                    && Gate::allows('viewAny', User::class)
                    && Gate::allows('resetPassword', $record->user))
                ->modalHeading('Redefinir senha')
                ->modalDescription('A pessoa deverá cadastrar uma nova senha no próximo acesso.')
                ->schema([
                    TextInput::make('senha')
                        ->label('Nova senha')
                        ->default('Mudar@1234')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(30)
                        ->rules([PasswordRule::min(8)->mixedCase()->numbers()->symbols()]),
                ])
                ->action(function (Servidor $record, array $data): void {
                    app(UserService::class)->redefinirSenha(
                        $record->user,
                        (string) ($data['senha'] ?? ''),
                        Auth::user(),
                    );

                    Notification::make()
                        ->title('Senha redefinida')
                        ->body('A troca de senha será exigida no próximo acesso.')
                        ->success()
                        ->send();
                }),

            Action::make('excluir_acesso')
                ->label('Excluir acesso')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Excluir conta de acesso')
                ->modalDescription('A ficha da pessoa, o cargo, as matrículas e o histórico serão preservados.')
                ->visible(fn (Servidor $record): bool => $record->user
                    && Gate::allows('viewAny', User::class)
                    && app(UserService::class)->podeDeletar(Auth::user(), $record->user))
                ->action(function (Servidor $record): void {
                    app(PessoaUsuarioService::class)->excluirContaDaPessoa($record, Auth::user());

                    Notification::make()
                        ->title('Conta de acesso excluída')
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @return array<int, BulkAction> */
    public static function bulkActions(): array
    {
        return [
            BulkAction::make('verificacao_acesso_em_massa')
                ->label('Verificação de acesso')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class)
                    && Gate::allows('toggleEmailApproval', [User::class, null, 'table']))
                ->schema([
                    Select::make('acao')
                        ->label('Ação')
                        ->options([
                            'approve' => 'Aprovar acesso',
                            'disapprove' => 'Desaprovar acesso',
                        ])
                        ->default('approve')
                        ->required(),
                ])
                ->action(function ($records, array $data): void {
                    $service = app(UserService::class);
                    $afetados = 0;
                    $ignorados = 0;

                    foreach ($records as $record) {
                        try {
                            if (! $record instanceof Servidor || ! $record->user) {
                                $ignorados++;

                                continue;
                            }

                            $service->definirAprovacao(
                                $record->user,
                                ($data['acao'] ?? 'approve') === 'approve',
                                Auth::user(),
                            );
                            $afetados++;
                        } catch (AuthorizationException) {
                            $ignorados++;
                        }
                    }

                    self::notificarResultado('Acessos atualizados', $afetados, $ignorados);
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('redefinir_senha_em_massa')
                ->label('Redefinir senhas')
                ->icon('heroicon-o-key')
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class)
                    && Gate::allows('resetPasswordAny', User::class))
                ->schema([
                    TextInput::make('senha')
                        ->label('Nova senha')
                        ->default('Mudar@1234')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(30)
                        ->rules([PasswordRule::min(8)->mixedCase()->numbers()->symbols()]),
                ])
                ->action(function ($records, array $data): void {
                    $service = app(UserService::class);
                    [$afetados, $ignorados] = self::aplicarEmUsuarios(
                        $records,
                        fn (User $user) => $service->redefinirSenha(
                            $user,
                            (string) ($data['senha'] ?? ''),
                            Auth::user(),
                        ),
                    );
                    self::notificarResultado('Senhas redefinidas', $afetados, $ignorados);
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('niveis_em_massa')
                ->label('Editar níveis')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class)
                    && Gate::allows('applyPermissionsAny', User::class))
                ->schema([
                    Select::make('modo')
                        ->label('Como aplicar')
                        ->options([
                            'add' => 'Adicionar níveis',
                            'replace' => 'Substituir níveis adicionais',
                            'remove' => 'Remover níveis selecionados',
                        ])
                        ->default('add')
                        ->required(),
                    Select::make('roles')
                        ->label('Níveis de acesso')
                        ->options(fn (): array => app(UserService::class)->opcoesDeRolesParaSelect(Auth::user()))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function ($records, array $data): void {
                    $service = app(UserService::class);
                    [$afetados, $ignorados] = self::aplicarEmUsuarios(
                        $records,
                        fn (User $user) => $service->sincronizarNiveisAdicionais(
                            $user,
                            $service->idsDeRolesSelecionadas($data),
                            (string) ($data['modo'] ?? 'add'),
                            Auth::user(),
                        ),
                    );
                    self::notificarResultado('Níveis de acesso atualizados', $afetados, $ignorados);
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('permissoes_em_massa')
                ->label('Editar permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class)
                    && Gate::allows('applyPermissionsAny', User::class))
                ->schema(fn (Get $get): array => [
                    Select::make('modo')
                        ->label('Como aplicar')
                        ->options([
                            'add' => 'Adicionar permissões',
                            'replace' => 'Substituir permissões diretas',
                            'remove' => 'Remover permissões diretas',
                        ])
                        ->default('add')
                        ->required(),
                    TextInput::make('buscar_permissao')
                        ->label('Pesquisar permissão')
                        ->live(debounce: 100)
                        ->dehydrated(false),
                    ...app(UserService::class)->checkboxesPermissoesEmMassa(
                        $get,
                        Auth::user(),
                    ),
                ])
                ->action(function ($records, array $data): void {
                    $service = app(UserService::class);
                    [$afetados, $ignorados] = self::aplicarEmUsuarios(
                        $records,
                        fn (User $user) => $service->sincronizarPermissoesDiretas(
                            $user,
                            $service->permissoesSelecionadas($data),
                            (string) ($data['modo'] ?? 'add'),
                            Auth::user(),
                        ),
                    );
                    self::notificarResultado('Permissões atualizadas', $afetados, $ignorados);
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('excluir_acessos_em_massa')
                ->label('Excluir contas de acesso')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('As fichas de Pessoas, cargos, matrículas e históricos serão preservados.')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class)
                    && (Auth::user()?->hasAnyPermissionTo(['Excluir Usuários', 'Excluir Usuarios']) ?? false))
                ->action(function ($records): void {
                    $service = app(PessoaUsuarioService::class);
                    $afetados = 0;
                    $ignorados = 0;

                    foreach ($records as $record) {
                        try {
                            if (! $record instanceof Servidor || ! $record->user) {
                                $ignorados++;

                                continue;
                            }

                            $service->excluirContaDaPessoa($record, Auth::user());
                            $afetados++;
                        } catch (AuthorizationException) {
                            $ignorados++;
                        }
                    }

                    self::notificarResultado('Contas de acesso excluídas', $afetados, $ignorados);
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /** @return array{0: int, 1: int} */
    private static function aplicarEmUsuarios(iterable $records, callable $callback): array
    {
        $afetados = 0;
        $ignorados = 0;

        foreach ($records as $record) {
            try {
                if (! $record instanceof Servidor || ! $record->user) {
                    $ignorados++;

                    continue;
                }

                $callback($record->user);
                $afetados++;
            } catch (AuthorizationException) {
                $ignorados++;
            }
        }

        return [$afetados, $ignorados];
    }

    private static function notificarResultado(string $titulo, int $afetados, int $ignorados): void
    {
        Notification::make()
            ->title($titulo)
            ->body($ignorados > 0
                ? "{$afetados} pessoa(s) atualizada(s); {$ignorados} ignorada(s)."
                : "{$afetados} pessoa(s) atualizada(s).")
            ->success()
            ->send();
    }
}
