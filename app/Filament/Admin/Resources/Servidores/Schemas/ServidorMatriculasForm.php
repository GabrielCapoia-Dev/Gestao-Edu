<?php

namespace App\Filament\Admin\Resources\Servidores\Schemas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Turma;
use App\Services\UserService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

/**
 * Matrículas funcionais da Pessoa e lotações pedagógicas do Professor.
 * UX em “abas” (item headers do Repeater) com + para nova matrícula/escola.
 */
class ServidorMatriculasForm
{
    public static function section(): Section
    {
        return Section::make('Matrículas e lotações')
            ->description('Cada aba é uma matrícula da Pessoa (máx. 2). Professor também informa as lotações por escola. Integral ocupa manhã e tarde.')
            ->icon('heroicon-o-academic-cap')
            ->schema([
                Repeater::make('matriculas_professor')
                    ->label('Matrículas')
                    ->extraAttributes([
                        'class' => 'pe-tabbed-repeater pe-matriculas-tabs',
                        'data-pe-tabs-label' => 'Matrículas',
                    ])
                    ->schema([
                        Hidden::make('id'),

                        TextInput::make('matricula')
                            ->label('Nº da matrícula')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),

                        Select::make('turno')
                            ->label('Turno')
                            ->options(function (Get $get, ?string $state): array {
                                $atual = (string) ($state ?? $get('turno') ?? '');
                                $todosTurnos = self::turnosDoEstado(self::matriculasDoEstado($get));
                                $turnosIrmaos = self::turnosExcetoUmaOcorrencia($todosTurnos, $atual);

                                return PessoaMatricula::turnosDisponiveisParaItem($turnosIrmaos, $atual ?: null);
                            })
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(fn (Get $get): ?string => self::helperTurnoItem($get)),

                        Repeater::make('escolas')
                            ->label('Escolas / lotações do Professor')
                            ->extraAttributes([
                                'class' => 'pe-tabbed-repeater pe-escolas-tabs',
                                'data-pe-tabs-label' => 'Escolas',
                            ])
                            ->schema([
                                Hidden::make('id'),

                                Select::make('id_escola')
                                    ->label('Escola / CMEI')
                                    ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),

                                Repeater::make('vinculos_turma_componente')
                                    ->label('Turmas e componentes (opcional)')
                                    ->schema([
                                        Select::make('turma_id')
                                            ->label('Turma')
                                            ->options(function (Get $get): array {
                                                return ServidorResource::turmasOptionsPublic(
                                                    $get('../../id_escola') ?? $get('id_escola'),
                                                    $get('../../../turno') ?? $get('../../turno'),
                                                );
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        Select::make('componente_curricular_id')
                                            ->label('Componente')
                                            ->options(fn (Get $get): array => ServidorResource::componentesOptionsPublic($get('turma_id')))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get): bool => filled($get('turma_id'))),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addActionLabel('+ Turma')
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemHeaders()
                                    ->itemLabel(fn (array $state): string => self::vinculoTurmaComponenteLabel($state))
                                    ->visible(fn (Get $get): bool => filled($get('id_escola')))
                                    ->columnSpanFull()
                                    ->reorderable(false),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->minItems(0)
                            ->addActionLabel('+ Escola')
                            ->itemHeaders()
                            ->itemLabel(function (array $state): string {
                                $id = $state['id_escola'] ?? null;
                                if (! filled($id)) {
                                    return 'Nova escola';
                                }

                                $nome = Escola::query()->whereKey((int) $id)->value('nome');

                                return $nome ? (string) $nome : 'Escola #'.$id;
                            })
                            ->columnSpanFull()
                            ->reorderable(false)
                            ->cloneable(false)
                            ->visible(fn (Get $get): bool => self::cargoDoEstado($get) === ServidorResource::CARGO_PROFESSOR),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->minItems(1)
                    ->maxItems(PessoaMatricula::MAX_POR_PESSOA)
                    ->addable(function (Get $get): bool {
                        return PessoaMatricula::podeAdicionarMatricula(self::matriculasDoEstado($get));
                    })
                    ->addActionLabel('+ Matrícula')
                    ->itemHeaders()
                    ->itemLabel(function (array $state): string {
                        $mat = filled($state['matricula'] ?? null) ? (string) $state['matricula'] : 'Nova matrícula';
                        $turno = Professor::turnosOptions()[$state['turno'] ?? ''] ?? null;

                        return $turno ? "{$mat} · {$turno}" : $mat;
                    })
                    ->helperText('Máximo de 2 matrículas: manhã e tarde. Integral é única e já cobre os dois turnos.')
                    ->columnSpanFull()
                    ->reorderable(false)
                    ->cloneable(false)
                    ->live(),

                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->columnSpanFull()
            ->collapsible();
    }

    /** @param array<string, mixed> $state */
    public static function vinculoTurmaComponenteLabel(array $state): string
    {
        $turmaId = $state['turma_id'] ?? null;
        $componenteId = $state['componente_curricular_id'] ?? null;

        $turma = filled($turmaId)
            ? Turma::query()->with('serie:id,nome')->find((int) $turmaId)
            : null;
        $componente = filled($componenteId)
            ? ComponenteCurricular::query()->whereKey((int) $componenteId)->value('nome')
            : null;

        $partesTurma = collect([
            $turma?->serie?->nome,
            $turma?->nome,
        ])->filter()->implode(' - ');

        if ($turma && filled($turma->turno)) {
            $turno = mb_strtolower(Professor::turnosOptions()[$turma->turno] ?? (string) $turma->turno);
            $partesTurma .= " ({$turno})";
        }

        return collect([$partesTurma, $componente])
            ->filter()
            ->implode(' · ') ?: 'Novo vínculo';
    }

    /**
     * @param  list<string>  $todos
     * @return list<string>
     */
    private static function turnosExcetoUmaOcorrencia(array $todos, string $atual): array
    {
        if ($atual === '') {
            return array_values(array_unique($todos));
        }

        $removido = false;
        $out = [];
        foreach ($todos as $t) {
            if (! $removido && $t === $atual) {
                $removido = true;

                continue;
            }
            $out[] = $t;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private static function matriculasDoEstado(Get $get): array
    {
        foreach (['../../matriculas_professor', '../../../matriculas_professor', 'matriculas_professor', '../'] as $path) {
            $matriculas = $get($path);

            if (is_array($matriculas)) {
                $normalizadas = array_filter(
                    $matriculas,
                    fn ($item): bool => is_array($item)
                        && (array_key_exists('matricula', $item) || array_key_exists('turno', $item)),
                );

                if ($normalizadas !== []) {
                    return $normalizadas;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $matriculas
     * @return list<string>
     */
    private static function turnosDoEstado(array $matriculas): array
    {
        $turnos = [];

        foreach ($matriculas as $item) {
            if (filled($item['turno'] ?? null)) {
                $turnos[] = (string) $item['turno'];
            }
        }

        return $turnos;
    }

    private static function helperTurnoItem(Get $get): ?string
    {
        $turnos = self::turnosDoEstado(self::matriculasDoEstado($get));

        if (in_array('integral', $turnos, true)) {
            return 'Integral já cobre manhã e tarde — não adicione outra matrícula.';
        }

        if (in_array('manha', $turnos, true) && ! in_array('tarde', $turnos, true) && count($turnos) === 1) {
            return 'Com manhã, a segunda matrícula só pode ser tarde.';
        }

        if (in_array('tarde', $turnos, true) && ! in_array('manha', $turnos, true) && count($turnos) === 1) {
            return 'Com tarde, a segunda matrícula só pode ser manhã.';
        }

        return null;
    }

    private static function cargoDoEstado(Get $get): ?string
    {
        foreach (['../../cargo', '../../../cargo', '../../../../cargo', '../../../../../cargo', 'cargo'] as $path) {
            $cargo = $get($path);

            if (is_string($cargo) && $cargo !== '') {
                return $cargo;
            }
        }

        return null;
    }
}
