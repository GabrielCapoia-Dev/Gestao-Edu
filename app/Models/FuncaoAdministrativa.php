<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FuncaoAdministrativa extends Model
{
    use HasUuidCodigo;

    public const CATEGORIA_GERAL = 'geral';
    public const CATEGORIA_PEDAGOGICO = 'pedagogico';
    public const CATEGORIA_ADMINISTRATIVO = 'administrativo';
    public const CATEGORIA_OPERACIONAL = 'operacional';

    public const TIPO_DIRECAO = 'direcao';
    public const TIPO_COORDENACAO = 'coordenacao';
    public const TIPO_SECRETARIA = 'secretaria';

    protected $table = 'funcao_administrativa';

    protected $fillable = [
        'codigo',
        'nome',
        'categoria',
        'ativo',
        'exige_professor',
        'concede_acesso_sistema',
        'tem_relacao_turma',
        'direcao_escolar',
        'coordenacao_pedagogica',
        'secretaria_escolar',
    ];

    protected function casts(): array
    {
        return [
            'codigo' => 'string',
            'nome' => 'string',
            'categoria' => 'string',
            'ativo' => 'boolean',
            'exige_professor' => 'boolean',
            'concede_acesso_sistema' => 'boolean',
            'tem_relacao_turma' => 'boolean',
            'direcao_escolar' => 'boolean',
            'coordenacao_pedagogica' => 'boolean',
            'secretaria_escolar' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FuncaoAdministrativa $funcao): void {
            $funcao->categoria = static::normalizarCategoria($funcao->categoria);

            if (blank($funcao->categoria)) {
                $funcao->categoria = static::CATEGORIA_GERAL;
            }

            if (collect([
                $funcao->direcao_escolar,
                $funcao->coordenacao_pedagogica,
                $funcao->secretaria_escolar,
            ])->filter()->count() > 1) {
                throw ValidationException::withMessages([
                    'funcao_administrativa' => 'Uma função pode representar apenas um tipo da Equipe Gestora.',
                ]);
            }
        });
    }

    public static function categoriasOptions(?string $categoriaAtual = null): array
    {
        if (! Schema::hasTable((new static())->getTable()) || ! Schema::hasColumn((new static())->getTable(), 'categoria')) {
            return [];
        }

        return collect(static::query()
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->all())
            ->push(static::normalizarCategoria($categoriaAtual))
            ->filter()
            ->unique(fn (string $categoria): string => Str::lower($categoria))
            ->sortBy(fn (string $categoria): string => static::categoriaLabel($categoria))
            ->mapWithKeys(fn (string $categoria): array => [
                $categoria => static::categoriaLabel($categoria),
            ])
            ->all();
    }

    public static function normalizarCategoria(?string $categoria): string
    {
        return Str::of((string) $categoria)
            ->squish()
            ->toString();
    }

    public static function categoriaLabel(?string $categoria): string
    {
        $categoria = static::normalizarCategoria($categoria);

        if ($categoria === '') {
            return 'Geral';
        }

        return match ($categoria) {
            self::CATEGORIA_GERAL => 'Geral',
            self::CATEGORIA_PEDAGOGICO => 'Pedagógico',
            self::CATEGORIA_ADMINISTRATIVO => 'Administrativo',
            self::CATEGORIA_OPERACIONAL => 'Operacional',
            default => Str::of($categoria)
                ->replace(['_', '-'], ' ')
                ->squish()
                ->title()
                ->toString(),
        };
    }

    public static function professorPadrao(): self
    {
        $funcao = static::query()
            ->where('exige_professor', true)
            ->where(function ($query): void {
                $query
                    ->where('nome', 'Professor')
                    ->orWhere('codigo', 'professor');
            })
            ->first();

        if ($funcao) {
            return $funcao;
        }

        return static::query()->create([
            'nome' => 'Professor',
            'categoria' => self::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'exige_professor' => true,
            'tem_relacao_turma' => false,
        ]);
    }

    public static function direcaoPadrao(): self
    {
        return static::gestoraPadrao(
            tipo: self::TIPO_DIRECAO,
            codigo: 'diretor-escolar',
            nome: 'Diretor Escolar',
            categoria: self::CATEGORIA_ADMINISTRATIVO,
        );
    }

    public static function coordenacaoPadrao(): self
    {
        return static::gestoraPadrao(
            tipo: self::TIPO_COORDENACAO,
            codigo: 'coordenador-pedagogico',
            nome: 'Coordenador Pedagógico',
            categoria: self::CATEGORIA_PEDAGOGICO,
        );
    }

    public static function secretariaPadrao(): self
    {
        return static::gestoraPadrao(
            tipo: self::TIPO_SECRETARIA,
            codigo: 'secretario-escolar',
            nome: 'Secretário Escolar',
            categoria: self::CATEGORIA_ADMINISTRATIVO,
        );
    }

    public static function manutencaoPadrao(): self
    {
        $funcao = static::query()->where('codigo', 'manutencao')->first()
            ?? static::query()->where('nome', 'Manutenção')->first()
            ?? new static();

        $payload = [
            'codigo' => 'manutencao',
            'nome' => 'Manutenção',
            'categoria' => self::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ];

        $funcao->fill($payload)->save();

        return $funcao->fresh();
    }

    public static function obrasPadrao(): self
    {
        $funcao = static::query()->where('codigo', 'obras')->first()
            ?? static::query()->where('nome', 'Obras')->first()
            ?? new static();

        $funcao->fill([
            'codigo' => 'obras',
            'nome' => 'Obras',
            'categoria' => self::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ])->save();

        return $funcao->fresh();
    }

    public static function motoristaPadrao(): self
    {
        $funcao = static::query()->where('codigo', 'motorista')->first()
            ?? static::query()->where('nome', 'Motorista')->first()
            ?? new static();

        $funcao->fill([
            'codigo' => 'motorista',
            'nome' => 'Motorista',
            'categoria' => self::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => false,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ])->save();

        return $funcao->fresh();
    }

    public static function transportePadrao(): self
    {
        $funcao = static::query()->where('codigo', 'transporte')->first()
            ?? static::query()->where('nome', 'Transporte')->first()
            ?? new static();

        $funcao->fill([
            'codigo' => 'transporte',
            'nome' => 'Transporte',
            'categoria' => self::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ])->save();

        return $funcao->fresh();
    }

    public static function assessoriaPedagogicaPadrao(): self
    {
        $funcao = static::query()->where('codigo', 'assessoria-pedagogica')->first()
            ?? static::query()->where('nome', 'Assessoria Pedagógica')->first()
            ?? new static();

        $funcao->fill([
            'codigo' => 'assessoria-pedagogica',
            'nome' => 'Assessoria Pedagógica',
            'categoria' => self::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ])->save();

        return $funcao->fresh();
    }

    public function ehManutencao(): bool
    {
        return (string) $this->codigo === 'manutencao';
    }

    public function ehObras(): bool
    {
        return (string) $this->codigo === 'obras';
    }

    public function ehMotorista(): bool
    {
        return (string) $this->codigo === 'motorista';
    }

    public function ehTransporte(): bool
    {
        return (string) $this->codigo === 'transporte';
    }

    public function ehAssessoriaPedagogica(): bool
    {
        return (string) $this->codigo === 'assessoria-pedagogica';
    }

    public function ehEquipeGestora(): bool
    {
        return (bool) ($this->direcao_escolar || $this->coordenacao_pedagogica || $this->secretaria_escolar);
    }

    public function temFlagsGestorasConflitantes(): bool
    {
        return collect([
            $this->direcao_escolar,
            $this->coordenacao_pedagogica,
            $this->secretaria_escolar,
        ])->filter()->count() > 1;
    }

    public function tipoEquipeGestora(): ?string
    {
        if ($this->temFlagsGestorasConflitantes()) {
            return null;
        }

        return match (true) {
            (bool) $this->direcao_escolar => self::TIPO_DIRECAO,
            (bool) $this->coordenacao_pedagogica => self::TIPO_COORDENACAO,
            (bool) $this->secretaria_escolar => self::TIPO_SECRETARIA,
            default => null,
        };
    }

    public function scopeEquipeGestora(Builder $query): Builder
    {
        return $query->where(function (Builder $funcoes): void {
            $funcoes
                ->where('direcao_escolar', true)
                ->orWhere('coordenacao_pedagogica', true)
                ->orWhere('secretaria_escolar', true);
        });
    }

    public function scopeDirecao(Builder $query): Builder
    {
        return $query->where('direcao_escolar', true);
    }

    public function scopeCoordenacao(Builder $query): Builder
    {
        return $query->where('coordenacao_pedagogica', true);
    }

    public function scopeSecretaria(Builder $query): Builder
    {
        return $query->where('secretaria_escolar', true);
    }

    public function scopeManutencao(Builder $query): Builder
    {
        return $query->where('codigo', 'manutencao');
    }

    public function scopeObras(Builder $query): Builder
    {
        return $query->where('codigo', 'obras');
    }

    public function scopeMotorista(Builder $query): Builder
    {
        return $query->where('codigo', 'motorista');
    }

    public function scopeTransporte(Builder $query): Builder
    {
        return $query->where('codigo', 'transporte');
    }

    public function scopeAssessoriaPedagogica(Builder $query): Builder
    {
        return $query->where('codigo', 'assessoria-pedagogica');
    }

    public function servidorFuncoes(): HasMany
    {
        return $this->hasMany(ServidorFuncaoAdministrativa::class, 'funcao_administrativa_id');
    }

    public function rolesPadrao(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'funcao_administrativa_role',
            'funcao_administrativa_id',
            'role_id',
        )->withTimestamps();
    }

    public function concedeAcessoSistema(): bool
    {
        return (bool) $this->concede_acesso_sistema;
    }

    public function servidores(): BelongsToMany
    {
        return $this->belongsToMany(
            Servidor::class,
            'servidor_funcao_administrativa',
            'funcao_administrativa_id',
            'servidor_id',
        )
            ->using(ServidorFuncaoAdministrativa::class)
            ->withPivot([
                'id',
                'id_escola',
                'setor_id',
                'status',
                'origem',
                'portaria',
                'principal',
                'data_inicio',
                'data_fim',
            ])
            ->withTimestamps();
    }

    private static function gestoraPadrao(
        string $tipo,
        string $codigo,
        string $nome,
        string $categoria,
    ): self {
        $flag = match ($tipo) {
            self::TIPO_DIRECAO => 'direcao_escolar',
            self::TIPO_COORDENACAO => 'coordenacao_pedagogica',
            self::TIPO_SECRETARIA => 'secretaria_escolar',
        };

        $funcao = static::query()->where('codigo', $codigo)->first()
            ?? static::query()->where('nome', $nome)->first()
            ?? static::query()
                ->where('ativo', true)
                ->where($flag, true)
                ->where('direcao_escolar', $tipo === self::TIPO_DIRECAO)
                ->where('coordenacao_pedagogica', $tipo === self::TIPO_COORDENACAO)
                ->where('secretaria_escolar', $tipo === self::TIPO_SECRETARIA)
                ->orderBy('id')
                ->first()
            ?? new static();

        $payload = [
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => $tipo === self::TIPO_COORDENACAO,
            'direcao_escolar' => $tipo === self::TIPO_DIRECAO,
            'coordenacao_pedagogica' => $tipo === self::TIPO_COORDENACAO,
            'secretaria_escolar' => $tipo === self::TIPO_SECRETARIA,
        ];

        if (! $funcao->exists) {
            $payload = [
                'codigo' => $codigo,
                'nome' => $nome,
                'categoria' => $categoria,
                'ativo' => true,
                ...$payload,
            ];
        }

        $funcao->fill($payload)->save();

        return $funcao->fresh();
    }

}
