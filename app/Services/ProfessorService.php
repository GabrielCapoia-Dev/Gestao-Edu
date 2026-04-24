<?php

namespace App\Services;

use App\Models\Professor;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\ComponenteCurricular;
use App\Models\Serie;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;


class ProfessorService
{
    public function __construct(
        protected UserService $userService
    ) {}


    public function configurarFormulario(Schema $schema): Schema
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $schema
            ->components([
                Section::make('Dados do Professor')
                    ->schema([
                        Select::make('id_escola')
                            ->label('Escola')
                            ->relationship('escola', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Selecione a escola')
                            ->disabled(function (?Professor $record) use ($user) {
                                // Impacto: em edicao, a escola do professor afeta filtros por unidade, vinculos com usuarios e relatorios pedagogicos.
                                return $record !== null && !$user->hasPermissionTo('Editar Escola do Professor');
                            })
                            ->columnSpanFull(),

                        TextInput::make('matricula')
                            ->label('Matrícula')
                            ->required()
                            ->disabled(function (?Professor $record) use ($user) {
                                // Impacto: matricula identifica o professor em importacoes/consultas; liberar edicao sem permissao pode quebrar conciliacao com bases externas.
                                return $record !== null && !$user->hasPermissionTo('Editar Matricula do Professor');
                            })
                            ->maxLength(255)
                            ->placeholder('Ex: PROF001'),

                        TextInput::make('nome')
                            ->label('Nome Completo')
                            ->required()
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Nome do Professor');
                            })
                            ->maxLength(255)
                            ->placeholder('Ex: João da Silva'),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->dehydrateStateUsing(fn(?string $state): string => Professor::normalizarEmail($state))
                            ->rule(function () {
                                return function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! Professor::emailInstitucionalValido((string) $value)) {
                                        $fail('Use somente e-mail institucional @edu.umuarama.pr.gov.br.');
                                    }
                                };
                            })
                            ->maxLength(255)
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Dados do Professor');
                            })
                            ->placeholder('professor@edu.umuarama.pr.gov.br'),

                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(255)
                            ->mask('(99) 99999-9999')
                            ->disabled(function (?Professor $record) use ($user) {
                                return $record !== null && !$user->hasPermissionTo('Editar Dados do Professor');
                            })
                            ->placeholder('(00) 00000-0000'),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }


    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                // Impacto: este filtro aplica o escopo do usuario na tabela. Alterar aqui pode expor professores de outras escolas ou ocultar professores vinculados por turma.
                $this->userService->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->filters($this->filtrosTabela())
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->headerActions($this->acoesCabecalho());
    }

    public function colunasTabela(): array
    {
        return [
            TextColumn::make('escola.nome')
                ->label('Escola')
                ->sortable()
                ->wrap(),

            TextColumn::make('matricula')
                ->label('Matrícula')
                ->searchable()
                ->copyable()
                ->sortable(),

            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable()
                ->copyable()
                ->wrap(),

            TextColumn::make('email')
                ->label('E-mail')
                ->searchable()
                ->copyable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('telefone')
                ->label('Telefone')
                ->searchable()
                ->placeholder('Não informado')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('componentes')
                ->label('Componentes')
                ->getStateUsing(
                    fn($record) =>
                    $record->componentesPorTurma
                        ->pluck('nome')
                        ->unique()
                        ->sort()
                        ->toArray()
                )
                ->badge()
                ->color('info')
                ->separator(',')
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('turmas_count')
                ->label('Qtd. Turmas')
                ->counts('turmas')
                ->sortable()
                ->alignCenter()
                ->badge()
                ->color('success'),

            TextColumn::make('created_at')
                ->label('Criado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function acoesCabecalho(): array
    {
        return [
            Action::make('total_listado')
                ->label(fn($livewire) => 'Total: ' . number_format(
                    $livewire->getFilteredTableQuery()->count(),
                    0,
                    ',',
                    '.'
                ))
                ->disabled()
                ->color('gray')
                ->icon('heroicon-m-list-bullet')
                ->button()
                ->extraAttributes([
                    'class' => 'cursor-default text-xl font-semibold',
                ])
        ];
    }

    public function acoesTabela(?User $user): array
    {
        return [
            ViewAction::make()
                ->modalHeading(fn($record) => "Detalhes - {$record->nome}")
                ->modalWidth('4xl')
                ->schema([
                    Section::make('Informações do Professor')
                        ->schema([
                            \Filament\Infolists\Components\TextEntry::make('escola.nome')
                                ->label('Escola'),
                            \Filament\Infolists\Components\TextEntry::make('matricula')
                                ->label('Matrícula')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('nome')
                                ->label('Nome')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('email')
                                ->label('E-mail')
                                ->copyable(),
                            \Filament\Infolists\Components\TextEntry::make('telefone')
                                ->label('Telefone')
                                ->placeholder('Não informado'),
                        ])
                        ->columns(3),


                    \Filament\Infolists\Components\TextEntry::make('turmas_lista')
                        ->label('Lista de Turmas')
                        ->getStateUsing(fn($record) => $record->id)
                        ->formatStateUsing(function ($state, $record) {
                            $turmas = \App\Models\Turma::whereHas('componentes', function ($query) use ($record) {
                                $query->where('turma_componente_professor.professor_id', $record->id);
                            })->with(['serie', 'escola', 'componentes' => function ($query) use ($record) {
                                $query->wherePivot('professor_id', $record->id);
                            }])->get();

                            if ($turmas->isEmpty()) {
                                return new \Illuminate\Support\HtmlString(
                                    '<p style="color:#6b7280;font-style:italic;">Não leciona em nenhuma turma.</p>'
                                );
                            }

                            $rows = '';
                            foreach ($turmas as $turma) {
                                $componentes = $turma->componentes->pluck('nome')->join(', ');
                                $turno = match ($turma->turno) {
                                    'manha'    => 'Manhã',
                                    'tarde'    => 'Tarde',
                                    'noite'    => 'Noite',
                                    'integral' => 'Integral',
                                    default    => $turma->turno,
                                };

                                $rows .= '
                        <tr style="border-bottom:1px solid #e5e7eb;">
                            <td style="padding:0.6rem 0.75rem;font-weight:600;color:#1d4ed8;white-space:nowrap;">
                                ' . e($turma->serie->nome) . ' — Turma ' . e($turma->nome) . '
                            </td>
                            <td style="padding:0.6rem 0.75rem;color:#374151;">
                                ' . e($turma->escola->nome) . '
                            </td>
                            <td style="padding:0.6rem 0.75rem;color:#374151;white-space:nowrap;">
                                ' . e($turno) . '
                            </td>
                            <td style="padding:0.6rem 0.75rem;color:#374151;">
                                ' . e($componentes) . '
                            </td>
                        </tr>
                    ';
                            }

                            return new \Illuminate\Support\HtmlString('
                    <div style="overflow-x:auto;border-radius:0.5rem;border:1px solid #e5e7eb;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                            <thead>
                                <tr style="background-color:#eff6ff;border-bottom:2px solid #bfdbfe;">
                                    <th style="padding:0.6rem 0.75rem;text-align:left;font-weight:600;color:#1e40af;white-space:nowrap;">
                                        Série / Turma
                                    </th>
                                    <th style="padding:0.6rem 0.75rem;text-align:left;font-weight:600;color:#1e40af;">
                                        Escola
                                    </th>
                                    <th style="padding:0.6rem 0.75rem;text-align:left;font-weight:600;color:#1e40af;white-space:nowrap;">
                                        Turno
                                    </th>
                                    <th style="padding:0.6rem 0.75rem;text-align:left;font-weight:600;color:#1e40af;">
                                        Componentes
                                    </th>
                                </tr>
                            </thead>
                            <tbody style="background-color:#ffffff;">
                                ' . $rows . '
                            </tbody>
                        </table>
                    </div>
                ');
                        })
                        ->columnSpanFull(),
                ])
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Visualizar Professores');
                }),

            EditAction::make(),


            DeleteAction::make()
                ->successNotification(null)
                ->using(function ($record) use ($user) {

                    if (! $this->userService->podeExcluirProfessores($user)) {

                        Notification::make()
                            ->title('Ação bloqueada')
                            ->body('Você não tem permissão para excluir este registro.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->delete();

                    Notification::make()
                        ->title('Excluído com sucesso')
                        ->success()
                        ->send();
                })
        ];
    }

    public function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make()
                ->visible(fn() => $this->userService->podeExcluirProfessoresEmLote(Auth::user())),
        ];
    }

    public function filtrosTabela(): array
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('escola', 'nome')
                ->searchable()
                ->preload()
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Filtrar Professores por Escola');
                }),

            SelectFilter::make('serie_id')
                ->label('Série')
                ->options(
                    Serie::query()->pluck('nome', 'id')
                )
                ->searchable()
                ->query(function ($query, array $data) {
                    // Impacto: o filtro por serie passa pela relacao de turmas; trocar para campo direto nao funciona para professor com varias turmas.
                    if (! $data['value']) {
                        return $query;
                    }

                    return $query->whereHas('turmas', function ($q) use ($data) {
                        $q->where('id_serie', $data['value']);
                    });
                })
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Filtrar Professores por Serie');
                }),

            SelectFilter::make('componente_curricular_id')
                ->label('Componente')
                ->options(
                    ComponenteCurricular::query()->pluck('nome', 'id')
                )
                ->searchable()
                ->query(function ($query, array $data) {
                    // Impacto: o filtro por componente depende do pivot turma_componente_professor; alterar esta relacao afeta tambem a visibilidade de alunos para professores.
                    if (! $data['value']) {
                        return $query;
                    }

                    return $query->whereHas('componentesPorTurma', function ($q) use ($data) {
                        $q->where('componente_curricular_id', $data['value']);
                    });
                })
                ->visible(function () use ($user): bool {
                    return $user->hasPermissionTo('Filtrar Professores por Componente');
                }),
        ];
    }

    public function forcarVinculoComEscola(array $data, ?User $auth): array
    {
        // Impacto: usuario vinculado a escola nao pode criar professor em outra unidade; remover isso quebra o isolamento entre escolas.
        if ($auth && filled($auth->id_escola)) {
            $data['id_escola'] = $auth->id_escola;
        }

        return $data;
    }
}
