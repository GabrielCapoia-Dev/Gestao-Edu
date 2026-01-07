<?php

namespace App\Services;

use App\Models\User;
use App\Models\Escola;
use App\Models\IgnoredUser;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /** 
     * Metodos Publicos 
     */

    /** Verifica com base na regra no GATE se o usuario é admin */
    public function ehAdmin(?User $user = null): bool
    {
        return Gate::allows('admin-only', $user);
    }
    public function podeVerLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Laudos de Aluno');
    }
    public function podeAnexarLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Anexar Laudos de Aluno');
    }
    public function podeExcluirLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Excluir Laudos de Aluno');
    }
    public function podeBaixarLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Baixar Laudos de Aluno');
    }

    /** Lista os usuários que não tem a role de Admin (whereDoesntHave retorna quem não tem a role) */
    public function listarUsuariosQuery(Builder $base, ?User $user): Builder
    {
        if (! $this->ehAdmin($user)) {
            $base->whereDoesntHave('roles', fn($q) => $q->where('name', 'Admin'));
        }
        return $base;
    }

    /** Retorna um icone com quantidade de novos usuários */
    public function badgeNavegacaoParaNovosUsuarios(?User $user): ?string
    {
        if (! $user || ! $this->ehAdmin($user)) return null;

        $ignorados = IgnoredUser::where('admin_id', $user->id)->pluck('user_id')->toArray();
        $count = User::where('email_approved', false)->whereNotIn('id', $ignorados)->count();

        return $count > 0 ? (string) $count : null;
    }

    /** Se existem usuários com email pendente, sincroniza para o admin */
    public function sincronizarIgnoradosParaAdmin(User $admin): void
    {
        if (! $this->ehAdmin($admin)) return;

        $pendentes = User::where('email_approved', false)->pluck('id');
        foreach ($pendentes as $userId) {
            IgnoredUser::firstOrCreate([
                'admin_id' => $admin->id,
                'user_id'  => $userId,
            ]);
        }
    }

    /**
     *  Metodos Privados
     */

    /** Retorna as opções de roles para o select de roles no formulário */
    private function opcoesDeRoles(Builder $base, ?User $user): Builder
    {
        return $this->ehAdmin($user) ? $base : $base->where('name', '!=', 'Admin');
    }

    /** Desabilita o campo de role se:
     * O admin estiver editando a propria conta
     * Se o usuário estiver editando a propria conta
     * 
     *  Não desabilita se:
     * Estiver criando um novo usuário
     */
    private function desabilitarCampoRole(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create' || ! $record) return false;
        if ($record->hasRole('Admin') && $user->id == 1) return false;
        if ($record->hasRole('Admin')) return true;
        if ($user && $record->id === $user->id) return true;
        return false;
    }

    /** Verifica se o admin pode ver o toggle de aprovação de email */
    private function podeVerToggleAprovacaoEmail(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create') return true;
        if (! $user || ! $this->ehAdmin($user)) return false;
        if ($record && ($record->hasRole('Admin') || ($user && $record->id === $user->id))) return false;
        return true;
    }

    /** Verifica se o usuario pode desabilitar o toggle de aprovação de email */
    private function desabilitarToggleAprovacaoEmail(?User $user, ?User $record): bool
    {
        return $user && $record && $user->id === $record->id;
    }


    private function podeSelecionarRegistro(?User $user, User $record): bool
    {
        if ($record->hasRole('Admin')) {
            return false;
        }

        if (! $this->ehAdmin($user) && $record->hasRole('Admin')) {
            return false;
        }

        return true;
    }


    public function podeDeletar(?User $user, User $record): bool
    {
        if (! $user) return false;
        if ($record->id === 1) return false;
        if ($record->id === $user->id) return false;
        return $this->ehAdmin($user);
    }

    public function podeDeletarEmLote(?User $user, iterable $records): bool
    {
        if (!$this->ehAdmin($user)) return false;
        foreach ($records as $record) {
            if ($record instanceof User && $record->hasRole('Admin')) return false;
        }
        return true;
    }

    /** ---------------- FORM: abstraído do Resource ---------------- */

    public function configurarFormulario(Form $form): Form
    {
        return $form->schema($this->schemaFormulario());
    }

    protected function schemaFormulario(): array
    {
        return [

            TextInput::make('codigo')
                ->label('Código')
                ->disabled()
                ->dehydrated()
                ->numeric()
                ->minValue(100),

            TextInput::make('name')
                ->label('Nome:')
                ->required()
                ->minLength(3)
                ->maxLength(100)
                ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
                ->validationMessages([
                    'regex' => 'Use apenas letras, sem caracteres especiais.',
                ]),

            TextInput::make('email')
                ->label('E-mail')
                ->unique(ignoreRecord: true)
                ->email()
                ->required(),

            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                ->helperText('Mín. 8 e máx. 30 caracteres. Deve conter letras maiúsculas, minúsculas, números e caracteres especiais.')
                ->minLength(8)
                ->maxLength(30)
                ->rules([
                    'nullable',
                    'max:30',
                    PasswordRule::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ])
                ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $context): bool => $context === 'create')
                ->validationMessages([
                    'max' => 'A senha deve ter no máximo 30 caracteres.',
                ]),


            Select::make('role')
                ->label('Nivel de acesso')
                ->relationship('roles', 'name', function (Builder $query) {
                    return $this->opcoesDeRoles($query, Auth::user());
                })
                ->preload()
                ->required()
                ->disabled(
                    fn(string $context, ?User $record) =>
                    $this->desabilitarCampoRole(Auth::user(), $record, $context)
                ),

            Toggle::make('email_approved')
                ->label('Verificação de acesso')
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->default(true)
                ->visible(
                    fn(?User $record, string $context) =>
                    $this->podeVerToggleAprovacaoEmail(Auth::user(), $record, $context)
                ),

            Section::make('Vínculo com Escola')
                ->icon('heroicon-o-identification')
                ->description('Aqui mostra se o usuário esta vinculado a uma escola.')
                ->schema([
                    Select::make('id_escola')
                        ->label('Escola')
                        ->options(fn() => $this->opcoesDeEscolasParaCampo(Auth::user()))
                        ->searchable()
                        ->preload()
                        ->afterStateHydrated(function ($state, callable $set, ?User $record, string $operation) {
                            $set('id_escola', $this->escolaInicialParaForm($record, Auth::user(), $operation));
                        })
                        ->default(fn(?User $record) => $this->escolaInicialParaForm($record, Auth::user(), 'create'))
                        ->disabled(fn(string $operation) => $this->deveTravarCampoEscola(Auth::user(), $operation))
                        ->dehydrated(true),
                ])
                ->visible(
                    fn(?User $record, string $context) =>
                    $this->podeVerSecaoEscola(Auth::user(), $record, $context)
                ),
        ];
    }
    /** Opções para o select de Escola conforme quem está acessando */
    public function opcoesDeEscolasParaCampo(?User $currentUser): array
    {
        if ($this->ehAdmin($currentUser)) {
            return Escola::query()->orderBy('nome')->pluck('nome', 'id')->toArray();
        }

        if (filled($currentUser?->id_escola)) {
            return Escola::query()->whereKey($currentUser->id_escola)->pluck('nome', 'id')->toArray();
        }

        return [];
    }

    private function podeVerSecaoEscola(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create') return true;
        if (! $record) return false;
        if ($record->hasRole('Admin')) return false;
        if ($user && $record->id === $user->id) return false;
        if ($context === 'edit') return true;
        return false;
    }

    /**
     * Deve travar o campo Escola?
     * - Admin: nunca
     * - Não-admin:
     *    - create: se tem escola vinculada, TRAVA (para criar apenas na sua escola)
     *    - edit: sempre TRAVA (não-admin não altera escola do usuário)
     */
    public function deveTravarCampoEscola(?User $currentUser, string $context): bool
    {
        if ($this->ehAdmin($currentUser)) {
            return false;
        }

        if ($context === 'create' && filled($currentUser?->id_escola)) {
            return true;
        }

        if ($context === 'edit') {
            return true;
        }

        return false;
    }


    /**
     * Valor inicial do campo Escola:
     * - Edit: usa a escola do registro se houver; senão cai pro vínculo do usuário atual (se houver)
     * - Create: se o usuário atual tem escola, usa ela; caso contrário, null (admin escolhe)
     */
    public function escolaInicialParaForm(?User $record, ?User $currentUser, string $context): ?int
    {
        if ($record && filled($record->id_escola)) {
            return (int) $record->id_escola;
        }

        if ($context === 'create') {
            return $currentUser?->id_escola ?? null;
        }

        return $currentUser?->id_escola ?? null;
    }





    /** Configura a tabela completa (paginações, colunas, filtros, ações, ordenação). */
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->checkIfRecordIsSelectableUsing(fn(User $record) => $this->podeSelecionarRegistro($user, $record))
            ->columns($this->colunasTabela())
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    protected function colunasTabela(): array
    {
        return [
            Tables\Columns\TextColumn::make('escola.nome')
                ->label('Escola')
                ->wrap()
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('name')
                ->label('Nome de usuário')
                ->wrap()
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('email')
                ->label('E-mail')
                ->wrap()
                ->searchable(),

            Tables\Columns\ToggleColumn::make('email_approved')
                ->label('Verificação de Acesso')
                ->sortable()
                ->disabled(
                    fn(User $record) =>
                    $this->desabilitarToggleAprovacaoEmail(Auth::user(), $record)
                )
                ->visible(
                    fn() =>
                    $this->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table')
                )
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->columnSpan(1),

            Tables\Columns\TextColumn::make('email_verified_at')
                ->label('Verificado em')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->formatStateUsing(function ($state, User $record) {
                    if (! $record->email_approved) {
                        return '--/--/-- --:--:--';
                    }
                    return $state ? $state->format('d/m/Y H:i:s') : '-';
                }),

            Tables\Columns\TextColumn::make('role')
                ->label('Nivel de acesso')
                ->sortable()
                ->getStateUsing(fn(User $record) => $record->roles->first()?->name ?? '-')
                ->toggleable(isToggledHiddenByDefault: false),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Criado em')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function acoesTabela(?User $user): array
    {
        return [
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()
                ->before(function (User $record, Tables\Actions\DeleteAction $action) use ($user) {
                    if (! $this->podeDeletar($user, $record)) {
                        $action->failure();
                        $action->halt();
                    }
                })
                ->disabled(
                    fn(User $record) => ($record->id === 1) || (Auth::id() === $record->id)
                )
                ->visible(
                    fn() =>
                    $this->ehAdmin(Auth::user())
                ),
        ];
    }

    protected function acoesEmMassa(?User $user): array
    {
        return [
            Tables\Actions\DeleteBulkAction::make()
                ->before(function ($records, $action) use ($user) {
                    if (! $this->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn() => $this->ehAdmin(Auth::user())),
        ];
    }
}
