<?php

namespace App\Models;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles {
        hasPermissionTo as protected traitHasPermissionTo;
    }

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
        'email_verified_at',
        'password',
        'google_id',
        'google_email',
        'google_token',
        'google_refresh_token',
        'google_token_expires_in',
        'codigo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_token_expires_in' => 'datetime',
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
                ->body('Usuário cadastrado com sucesso. Solicite aprovação do administrador para acessar o painel.')
                ->success()
                ->send();
        }
        Notification::make()
            ->title('Aguardando Aprovação')
            ->body('Entre em contato com o administrador para solicitar a aprovação do seu e-mail.')
            ->icon('heroicon-o-arrow-path')
            ->duration(10000)
            ->warning()
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
        return (bool) $this->email_approved
            || $this->hasPermissionTo('Acessar Painel')
            || $this->hasRole('Acessar Painel');
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
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

        return $this->getAllPermissions()->contains(function ($permission) use ($needle): bool {
            $name = Str::lower(Str::ascii((string) $permission->name));

            return str_contains($name, $needle);
        });
    }

    protected static function booted()
    {
        static::creating(function (User $user) {
            if (is_null($user->codigo)) {
                $ultimoCodigo = User::max('codigo');

                if (empty($ultimoCodigo) || $ultimoCodigo < 100) {
                    $user->codigo = 100;
                } else {
                    $user->codigo = $ultimoCodigo + 1;
                }
            }
        });

        static::updating(function (User $user) {
            if (
                $user->isDirty('email_approved') &&
                $user->email_approved &&
                is_null($user->getOriginal('email_verified_at'))
            ) {
                $user->email_verified_at = now();
            }
        });
    }


    public function hasGoogleOauth(): bool
    {
        return filled($this->google_token) || filled($this->google_refresh_token);
    }

    public function googleAccessTokenExpired(): bool
    {
        return is_null($this->google_token_expires_in)
            ? true
            : now()->greaterThan($this->google_token_expires_in);
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function professores()
    {
        return $this->hasMany(Professor::class, 'user_id');
    }

    public function ehProfessor(): bool
    {
        return $this->professores()->exists();
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }

    public function podeGerenciarSetor(?Setor $setor = null): bool
    {
        if (! $this->setor) {
            return false;
        }

        return $this->setor->podeGerenciarSetor($setor);
    }

    public function pertenceAoSetorGeral(): bool
    {
        return $this->setor?->ehSetorGeral() ?? false;
    }

    public static function scopeAuthUser()
    {

        return Auth::user();
    }
}
