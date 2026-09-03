<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use App\Services\UserSetorAccessService;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasUuidCodigo;
    use Notifiable;
    use SoftDeletes;
    use HasRoles {
        hasPermissionTo as protected traitHasPermissionTo;
    }

    private ?bool $ehProfessorCache = null;

    private ?bool $canAuthenticateCache = null;

    /** @var array<string, bool> */
    private array $permissionLikeCache = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'ativo' => true,
        'auth_version' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_escola',
        'setor_id',
        'name',
        'email',
        'email_approved',
        'ativo',
        'email_verified_at',
        'last_login_at',
        'last_seen_at',
        'password',
        'must_change_password',
        'google_id',
        'google_email',
        'avatar_url',
        'codigo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
        'google_refresh_token',
        'google_token_expires_in',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'ativo' => 'boolean',
            'auth_version' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel, ?bool $register = false): bool
    {
        if ($this->canAccessAdminPanel()) {
            return true;
        }

        Filament::auth()->logout();

        if ($register) {
            Notification::make()
                ->title('Cadastro Realizado')
                ->body('Usuário cadastrado com sucesso.')
                ->success()
                ->send();
        }
        Notification::make()
            ->title('Acesso indisponível')
            ->body('Seu cadastro de servidor está inativo. Entre em contato com o administrador.')
            ->icon('heroicon-o-no-symbol')
            ->duration(10000)
            ->danger()
            ->send();


        // Redireciona para o login do painel correto (Symfony RedirectResponse)
        $loginRouteName = "filament.{$panel->getId()}.auth.login";
        $loginUrl = route($loginRouteName);

        throw new HttpResponseException(new RedirectResponse($loginUrl));
    }

    public function validateAccessGoogle(?string $register, ?string $login): bool
    {
        return $this->canAccessAdminPanel();
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->canAuthenticate();
    }

    public function isOperationallyActive(): bool
    {
        return $this->canAuthenticate();
    }

    public function canAuthenticate(): bool
    {
        if ($this->canAuthenticateCache !== null) {
            return $this->canAuthenticateCache;
        }

        if ($this->trashed()) {
            return $this->canAuthenticateCache = false;
        }

        $pessoasAtivas = $this->servidores()
            ->where('status', Pessoa::STATUS_ATIVO)
            ->where(function (Builder $pessoas): void {
                $pessoas
                    ->whereHas('professores', fn (Builder $professores): Builder => $professores->where('ativo', true))
                    ->orWhereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos
                        ->whereHas('funcaoAdministrativa', fn (Builder $cargos): Builder => $cargos
                            ->where('codigo', '<>', Pessoa::CARGO_PENDENTE_CODIGO)));
            })
            ->limit(2)
            ->count();

        return $this->canAuthenticateCache = $pessoasAtivas === 1;
    }

    public function scopeCanAuthenticate(Builder $query): Builder
    {
        return $query
            ->whereNull($query->getModel()->qualifyColumn('deleted_at'))
            ->whereHas(
                'servidores',
                fn (Builder $pessoas): Builder => $pessoas
                    ->where('status', Pessoa::STATUS_ATIVO)
                    ->where(function (Builder $comCargo): void {
                        $comCargo
                            ->whereHas('professores', fn (Builder $professores): Builder => $professores->where('ativo', true))
                            ->orWhereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos
                                ->whereHas('funcaoAdministrativa', fn (Builder $cargos): Builder => $cargos
                                    ->where('codigo', '<>', Pessoa::CARGO_PENDENTE_CODIGO)));
                    }),
                '=',
                1,
            );
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Impacto: retorna false quando a permissao ainda nao existe, evitando erro fatal em telas que consultam permissoes antes do seed/comando CriarPermissoes.
        try {
            return $this->traitHasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function hasAnyPermissionTo(array $permissions, $guardName = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission, $guardName)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionLike(string $fragment): bool
    {
        $needle = Str::lower(Str::ascii($fragment));

        if (array_key_exists($needle, $this->permissionLikeCache)) {
            return $this->permissionLikeCache[$needle];
        }

        return $this->permissionLikeCache[$needle] = $this->getAllPermissions()->contains(function ($permission) use ($needle): bool {
            $name = Str::lower(Str::ascii((string) $permission->name));

            return str_contains($name, $needle);
        });
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (filled($this->avatar_url)) {
            return $this->resolveAvatarUrl((string) $this->avatar_url);
        }

        $name = str($this->name)
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->filter()
            ->join(' ');

        return 'https://ui-avatars.com/api/?name=' . rawurlencode($name ?: 'U') . '&color=000000&background=FFFFFF';
    }

    protected function resolveAvatarUrl(string $avatarUrl): string
    {
        if (Str::startsWith($avatarUrl, ['http://', 'https://', 'data:image/', '/'])) {
            return $avatarUrl;
        }

        return Storage::disk('public')->url($avatarUrl);
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function escolas(): BelongsToMany
    {
        return $this->belongsToMany(Escola::class, 'escola_user', 'user_id', 'escola_id')
            ->withTimestamps();
    }

    public function idsEscolasVinculadas(): array
    {
        $idsEquipeGestora = $this->idsEscolasDaEquipeGestoraAtiva();

        if ($idsEquipeGestora !== null) {
            return $idsEquipeGestora;
        }

        // Fluxo: primeiro usa o pivot escola_user; se ainda nao houver sincronizacao, cai para id_escola para manter compatibilidade com o modelo antigo.
        $ids = $this->escolas()
            ->pluck('escolas.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] && filled($this->id_escola)) {
            return [(int) $this->id_escola];
        }

        return $ids;
    }

    /**
     * @return array<int, int>|null Null indica que o usuario nao possui vinculo gestor ativo.
     */
    private function idsEscolasDaEquipeGestoraAtiva(): ?array
    {
        if (
            ! Schema::hasTable('servidor_funcao_administrativa')
            || ! Schema::hasTable('funcao_administrativa')
        ) {
            return null;
        }

        $flags = collect(['direcao_escolar', 'coordenacao_pedagogica', 'secretaria_escolar'])
            ->filter(fn (string $column): bool => Schema::hasColumn('funcao_administrativa', $column));

        if ($flags->isEmpty()) {
            return null;
        }

        $todosServidorIds = $this->servidores()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($todosServidorIds === []) {
            return null;
        }

        $servidorIds = $this->servidores()
            ->where('status', Servidor::STATUS_ATIVO)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $temVinculoGestor = ServidorFuncaoAdministrativa::query()
            ->whereIn('servidor_id', $todosServidorIds)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', function ($query) use ($flags): void {
                $query->where(function ($gestoras) use ($flags): void {
                    foreach ($flags as $flag) {
                        $gestoras->orWhere($flag, true);
                    }
                });
            })
            ->exists();

        if (! $temVinculoGestor) {
            return null;
        }

        if (count($servidorIds) !== 1) {
            return [];
        }

        $idsGestores = ServidorFuncaoAdministrativa::query()
            ->whereIn('servidor_id', $servidorIds)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotNull('id_escola')
            ->whereHas('funcaoAdministrativa', function ($query) use ($flags): void {
                $query->where(function ($gestorasValidas) use ($flags): void {
                    foreach ($flags as $flag) {
                        $gestorasValidas->orWhere(function ($tipo) use ($flag, $flags): void {
                            $tipo->where($flag, true);

                            foreach ($flags->reject(fn (string $outra): bool => $outra === $flag) as $outra) {
                                $tipo->where($outra, false);
                            }
                        });
                    }
                });
            })
            ->pluck('id_escola')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $idsTodosVinculos = ServidorFuncaoAdministrativa::query()
            ->whereIn('servidor_id', $servidorIds)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotNull('id_escola')
            ->pluck('id_escola')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return count($idsGestores) === 1 && $idsGestores === $idsTodosVinculos
            ? $idsGestores
            : [];
    }

    public function professores()
    {
        return $this->hasMany(Professor::class, 'user_id');
    }

    public function servidores()
    {
        return $this->hasMany(Servidor::class, 'user_id');
    }

    public function ehProfessor(): bool
    {
        return $this->ehProfessorCache ??= $this->professores()->where('ativo', true)->exists();
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }

    public function idsSetoresOperacionais(): array
    {
        return app(UserSetorAccessService::class)->visibleSetorIds($this);
    }

    public function setorPrincipalId(): ?int
    {
        return app(UserSetorAccessService::class)->primarySetorId($this);
    }

    public function setorOperacional(): ?Setor
    {
        $setorId = $this->setorPrincipalId();

        return $setorId ? Setor::find($setorId) : null;
    }

    public function podeVerSetorOperacional(?Setor $setor): bool
    {
        if (! $setor) {
            return false;
        }

        return app(UserSetorAccessService::class)->canAccessSetor($this, (int) $setor->id);
    }

    public function pertenceAoSetorOperacionalNome(string $nome): bool
    {
        $nomes = [$nome];

        if (function_exists('mb_convert_encoding')) {
            $nomes[] = mb_convert_encoding($nome, 'UTF-8', 'ISO-8859-1');

            if (str_contains($nome, 'Ã') || str_contains($nome, 'Â')) {
                $nomes[] = mb_convert_encoding($nome, 'ISO-8859-1', 'UTF-8');
            }
        }

        $ids = $this->idsSetoresOperacionais();

        if ($ids === []) {
            return false;
        }

        return Setor::query()
            ->whereIn('id', $ids)
            ->whereIn('nome', array_values(array_unique($nomes)))
            ->exists();
    }

    public function podeGerenciarSetor(?Setor $setor = null): bool
    {
        return $this->podeVerSetorOperacional($setor);
    }

    public function pertenceAoSetorGeral(): bool
    {
        return app(UserSetorAccessService::class)->hasGlobalAccess($this)
            || ($this->setorOperacional()?->ehSetorGeral() ?? false);
    }

    public static function scopeAuthUser()
    {

        return Auth::user();
    }
}
