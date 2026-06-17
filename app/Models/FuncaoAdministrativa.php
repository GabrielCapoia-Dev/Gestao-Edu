<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FuncaoAdministrativa extends Model
{
    public const CODIGO_PROFESSOR = 'professor';

    public const CATEGORIA_GERAL = 'geral';
    public const CATEGORIA_PEDAGOGICO = 'pedagogico';
    public const CATEGORIA_ADMINISTRATIVO = 'administrativo';
    public const CATEGORIA_OPERACIONAL = 'operacional';

    protected $table = 'funcao_administrativa';

    protected $fillable = [
        'codigo',
        'nome',
        'categoria',
        'ativo',
        'exige_professor',
        'tem_relacao_turma',
        'direcao_escolar',
        'coordenacao_pedagogica',
    ];

    protected function casts(): array
    {
        return [
            'codigo' => 'string',
            'nome' => 'string',
            'categoria' => 'string',
            'ativo' => 'boolean',
            'exige_professor' => 'boolean',
            'tem_relacao_turma' => 'boolean',
            'direcao_escolar' => 'boolean',
            'coordenacao_pedagogica' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FuncaoAdministrativa $funcao): void {
            if (blank($funcao->codigo)) {
                $funcao->codigo = static::codigoDisponivelParaNome((string) $funcao->nome, $funcao->id);
            }

            $funcao->categoria = static::normalizarCategoria($funcao->categoria);

            if (blank($funcao->categoria)) {
                $funcao->categoria = static::CATEGORIA_GERAL;
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
        return static::query()->firstOrCreate(
            ['codigo' => self::CODIGO_PROFESSOR],
            [
                'nome' => 'Professor',
                'categoria' => self::CATEGORIA_PEDAGOGICO,
                'ativo' => true,
                'exige_professor' => true,
                'tem_relacao_turma' => false,
            ],
        );
    }

    public function servidorFuncoes(): HasMany
    {
        return $this->hasMany(ServidorFuncaoAdministrativa::class, 'funcao_administrativa_id');
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
                'data_inicio',
                'data_fim',
            ])
            ->withTimestamps();
    }

    private static function codigoDisponivelParaNome(string $nome, ?int $ignoreId = null): string
    {
        $base = Str::slug($nome) ?: 'funcao';
        $codigo = $base;
        $sufixo = 2;

        while (static::query()
            ->where('codigo', $codigo)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $codigo = "{$base}-{$sufixo}";
            $sufixo++;
        }

        return $codigo;
    }
}
