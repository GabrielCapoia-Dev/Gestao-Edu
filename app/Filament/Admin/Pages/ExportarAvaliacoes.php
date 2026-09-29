<?php

namespace App\Filament\Admin\Pages;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\PessoaScopeService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;
use UnitEnum;

class ExportarAvaliacoes extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.exportar-avaliacoes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Exportar Avaliações';

    protected static ?string $navigationLabel = 'Exportar Avaliações';

    protected static ?string $navigationParentItem = 'Turmas';

    protected static ?string $slug = 'avaliacoes-exportar';

    protected static ?int $navigationSort = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $modoListagem = 'alunos';

    public string $busca = '';

    public int $perPage = 10;

    public ?int $alunoSelecionadoId = null;

    public ?int $turmaSelecionadaId = null;

    public ?int $avaliacaoParaAlunoDaTurmaId = null;

    public ?int $alunoDaTurmaSelecionadoId = null;

    public function getHeader(): ?\Illuminate\Contracts\View\View
       {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Exportar Avaliações",
            'description' => 'Exporte as avaliações, mantenha um registro atualizado das informações.',
        ]);
    }



    public static function canAccess(): bool
    {
        return Gate::allows('export', Avaliacao::class);
    }

    public function definirModo(string $modo): void
    {
        if (! in_array($modo, ['alunos', 'turmas'], true)) {
            return;
        }

        $this->modoListagem = $modo;
        $this->busca = '';
        $this->resetPage('exportarAvaliacoesPage');
        $this->fecharModais();
    }

    public function updatedBusca(): void
    {
        $this->resetPage('exportarAvaliacoesPage');
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [5, 10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }

        $this->resetPage('exportarAvaliacoesPage');
    }

    public function abrirAluno(int $alunoId): void
    {
        $aluno = $this->buscarAlunoNoEscopo($alunoId);

        if (! $aluno) {
            return;
        }

        $this->alunoSelecionadoId = (int) $aluno->id;
        $this->turmaSelecionadaId = null;
        $this->avaliacaoParaAlunoDaTurmaId = null;
        $this->alunoDaTurmaSelecionadoId = null;
    }

    public function abrirTurma(int $turmaId): void
    {
        $turma = $this->buscarTurmaNoEscopo($turmaId);

        if (! $turma) {
            return;
        }

        $this->turmaSelecionadaId = (int) $turma->id;
        $this->alunoSelecionadoId = null;
        $this->avaliacaoParaAlunoDaTurmaId = null;
        $this->alunoDaTurmaSelecionadoId = null;
    }

    public function abrirExportacaoAlunoDaTurma(int $avaliacaoId): void
    {
        if (! $this->turmaSelecionada || ! $this->avaliacoesTurmaSelecionada->contains('id', $avaliacaoId)) {
            return;
        }

        $this->avaliacaoParaAlunoDaTurmaId = $avaliacaoId;
        $this->alunoDaTurmaSelecionadoId = null;
    }

    public function fecharModais(): void
    {
        $this->alunoSelecionadoId = null;
        $this->turmaSelecionadaId = null;
        $this->avaliacaoParaAlunoDaTurmaId = null;
        $this->alunoDaTurmaSelecionadoId = null;
    }

    public function fecharModalAlunoDaTurma(): void
    {
        $this->avaliacaoParaAlunoDaTurmaId = null;
        $this->alunoDaTurmaSelecionadoId = null;
    }

    public function getAlunosComAvaliacoesProperty(): LengthAwarePaginator
    {
        $query = Aluno::query()
            ->with([
                'turma:id,nome,turno,id_escola,id_serie',
                'turma.escola:id,nome',
                'turma.serie:id,nome',
                'turma.avaliacoes:id,nome,data_inicio,data_fim,tipo_avaliacao_id,periodo_avaliacao_id',
            ])
            ->whereHas('turma', fn (Builder $turmas): Builder => $this->aplicarEscopoTurmas($turmas))
            ->whereHas('turma.avaliacoes')
            ->where('status', '!=', Aluno::STATUS_PENDENTE);

        $this->aplicarBuscaAluno($query);

        return $query
            ->orderBy('nome')
            ->paginate($this->perPage, ['id', 'nome', 'cgm', 'id_turma', 'status', 'tipo_vinculo'], 'exportarAvaliacoesPage');
    }

    public function getTurmasComAvaliacoesProperty(): LengthAwarePaginator
    {
        $query = Turma::query()
            ->with([
                'escola:id,nome',
                'serie:id,nome',
                'avaliacoes:id,nome,data_inicio,data_fim,tipo_avaliacao_id,periodo_avaliacao_id',
            ])
            ->withCount([
                'alunos' => fn (Builder $alunos): Builder => $alunos->where('status', Aluno::STATUS_MATRICULADO),
            ])
            ->whereHas('avaliacoes');

        $this->aplicarEscopoTurmas($query);
        $this->aplicarBuscaTurma($query);

        return $query
            ->orderBy('id_escola')
            ->orderBy('id_serie')
            ->orderBy('nome')
            ->paginate($this->perPage, ['id', 'nome', 'id_escola', 'id_serie', 'turno'], 'exportarAvaliacoesPage');
    }

    public function turmaLabel(?Turma $turma): string
    {
        if (! $turma) {
            return 'Turma';
        }

        return sprintf(
            '%s - %s | %s',
            $turma->serie?->nome ?: 'Série',
            $turma->nome ?: 'Turma',
            $turma->turno ?: 'Turno'
        );
    }

    public function getAlunoSelecionadoProperty(): ?Aluno
    {
        return $this->alunoSelecionadoId
            ? $this->buscarAlunoNoEscopo((int) $this->alunoSelecionadoId)
            : null;
    }

    public function getTurmaSelecionadaProperty(): ?Turma
    {
        return $this->turmaSelecionadaId
            ? $this->buscarTurmaNoEscopo((int) $this->turmaSelecionadaId)
            : null;
    }

    public function getAvaliacoesAlunoSelecionadoProperty(): Collection
    {
        if (! $this->alunoSelecionado?->turma) {
            return collect();
        }

        return $this->avaliacoesDaTurma((int) $this->alunoSelecionado->turma->id);
    }

    public function getAvaliacoesTurmaSelecionadaProperty(): Collection
    {
        if (! $this->turmaSelecionada) {
            return collect();
        }

        return $this->avaliacoesDaTurma((int) $this->turmaSelecionada->id);
    }

    public function getAlunosDaTurmaSelecionadaProperty(): Collection
    {
        if (! $this->turmaSelecionada) {
            return collect();
        }

        return Aluno::query()
            ->where('id_turma', (int) $this->turmaSelecionada->id)
            ->where('status', '!=', Aluno::STATUS_PENDENTE)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cgm', 'id_turma', 'status', 'tipo_vinculo']);
    }

    public function getAvaliacaoParaAlunoDaTurmaProperty(): ?Avaliacao
    {
        return $this->avaliacaoParaAlunoDaTurmaId
            ? $this->avaliacoesTurmaSelecionada->firstWhere('id', (int) $this->avaliacaoParaAlunoDaTurmaId)
            : null;
    }

    public function alunoExportPdfUrl(int $avaliacaoId, int $alunoId): string
    {
        return route('avaliacoes.documento.pdf', [
            'avaliacao_id' => $avaliacaoId,
            'escopo' => 'aluno',
            'aluno_id' => $alunoId,
        ]);
    }

    public function alunoExportCsvUrl(int $avaliacaoId, int $alunoId): string
    {
        return route('avaliacoes.documento.csv', [
            'avaliacao_id' => $avaliacaoId,
            'escopo' => 'aluno',
            'aluno_id' => $alunoId,
        ]);
    }

    public function turmaExportPdfUrl(int $avaliacaoId, int $turmaId): string
    {
        return route('avaliacoes.documento.pdf', [
            'avaliacao_id' => $avaliacaoId,
            'escopo' => 'turma',
            'turma_id' => $turmaId,
            'async' => 1,
        ]);
    }

    public function turmaExportCsvUrl(int $avaliacaoId, int $turmaId): string
    {
        return route('avaliacoes.documento.csv', [
            'avaliacao_id' => $avaliacaoId,
            'escopo' => 'turma',
            'turma_id' => $turmaId,
            'async' => 1,
        ]);
    }

    private function buscarAlunoNoEscopo(int $alunoId): ?Aluno
    {
        return Aluno::query()
            ->with(['turma:id,nome,turno,id_escola,id_serie', 'turma.escola:id,nome', 'turma.serie:id,nome'])
            ->whereKey($alunoId)
            ->whereHas('turma', fn (Builder $turmas): Builder => $this->aplicarEscopoTurmas($turmas))
            ->whereHas('turma.avaliacoes')
            ->where('status', '!=', Aluno::STATUS_PENDENTE)
            ->first();
    }

    private function buscarTurmaNoEscopo(int $turmaId): ?Turma
    {
        $query = Turma::query()
            ->with(['escola:id,nome', 'serie:id,nome'])
            ->withCount([
                'alunos' => fn (Builder $alunos): Builder => $alunos->where('status', Aluno::STATUS_MATRICULADO),
            ])
            ->whereKey($turmaId)
            ->whereHas('avaliacoes');

        $this->aplicarEscopoTurmas($query);

        return $query->first();
    }

    private function avaliacoesDaTurma(int $turmaId): Collection
    {
        return Avaliacao::query()
            ->whereHas('turmas', function (Builder $turmas) use ($turmaId): Builder {
                return $this->aplicarEscopoTurmas($turmas->whereKey($turmaId));
            })
            ->with(['tipo:id,nome', 'periodo:id,nome'])
            ->orderBy('data_inicio')
            ->orderBy('data_fim')
            ->orderBy('id')
            ->get(['id', 'nome', 'data_inicio', 'data_fim', 'tipo_avaliacao_id', 'periodo_avaliacao_id']);
    }

    private function aplicarEscopoTurmas(Builder $query): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $query;
        }

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $escolasIds = $scope->escolaIdsDosVinculos($user);

        if ($escolasIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id_escola', $escolasIds);
    }

    private function aplicarBuscaAluno(Builder $query): void
    {
        $busca = trim($this->busca);

        if ($busca === '') {
            return;
        }

        $query->where(function (Builder $alunos) use ($busca): void {
            $alunos
                ->where('nome', 'like', '%'.$busca.'%')
                ->orWhere('cgm', 'like', '%'.$busca.'%')
                ->orWhereHas('turma.escola', fn (Builder $escolas): Builder => $escolas->where('nome', 'like', '%'.$busca.'%'))
                ->orWhereHas('turma.serie', fn (Builder $series): Builder => $series->where('nome', 'like', '%'.$busca.'%'));
        });
    }

    private function aplicarBuscaTurma(Builder $query): void
    {
        $busca = trim($this->busca);

        if ($busca === '') {
            return;
        }

        $query->where(function (Builder $turmas) use ($busca): void {
            $turmas
                ->where('nome', 'like', '%'.$busca.'%')
                ->orWhereHas('escola', fn (Builder $escolas): Builder => $escolas->where('nome', 'like', '%'.$busca.'%'))
                ->orWhereHas('serie', fn (Builder $series): Builder => $series->where('nome', 'like', '%'.$busca.'%'))
                ->orWhereHas('avaliacoes', fn (Builder $avaliacoes): Builder => $avaliacoes->where('nome', 'like', '%'.$busca.'%'));
        });
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
