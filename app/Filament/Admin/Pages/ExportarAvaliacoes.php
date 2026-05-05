<?php

namespace App\Filament\Admin\Pages;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\Escola;
use App\Models\Turma;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ExportarAvaliacoes extends Page
{
    protected string $view = 'filament.pages.exportar-avaliacoes';

    protected static ?string $title = 'Exportar Avaliações';

    protected static ?string $navigationLabel = 'Exportar Avaliações';

    protected static ?string $slug = 'avaliacoes-exportar';

    protected static ?int $navigationSort = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public ?int $avaliacao = null;

    public string $escopo = 'aluno';

    public ?int $escola = null;

    public ?int $turma = null;

    public ?int $aluno = null;

    public string $buscaAluno = '';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionLike('exportar avaliacoes') ?? false;
    }

    public function updatedEscopo(): void
    {
        if (! in_array($this->escopo, ['aluno', 'turma', 'escola'], true)) {
            $this->escopo = 'aluno';
        }

        $this->escola = null;
        $this->turma = null;
        $this->aluno = null;
        $this->avaliacao = null;
    }

    public function updatedAluno(): void
    {
        $this->avaliacao = null;
        $this->turma = $this->alunoAtual?->id_turma ? (int) $this->alunoAtual->id_turma : null;
        $this->escola = $this->alunoAtual?->turma?->id_escola ? (int) $this->alunoAtual->turma->id_escola : null;
    }

    public function updatedAvaliacao(): void
    {
        if ($this->escopo === 'aluno') {
            return;
        }

        $this->escola = null;
        $this->turma = null;
    }

    public function updatedEscola(): void
    {
        if ($this->escopo === 'turma') {
            $this->turma = null;
        }
    }

    public function getAvaliacoesDisponiveisProperty(): Collection
    {
        $query = Avaliacao::query()
            ->with(['tipo:id,nome', 'periodo:id,nome', 'turmas:id,nome,id_escola,id_serie'])
            ->whereHas('turmas', fn (Builder $turmas): Builder => $this->aplicarEscopoTurmas($turmas));

        if ($this->escopo === 'aluno' && $this->alunoAtual) {
            $query->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $this->alunoAtual->id_turma));
        }

        return $query
            ->orderBy('data_inicio')
            ->orderBy('data_fim')
            ->orderBy('id')
            ->get();
    }

    public function getEscolasDisponiveisProperty(): Collection
    {
        if (! $this->avaliacaoAtual) {
            return collect();
        }

        $escolasIds = $this->turmasDaAvaliacao()
            ->pluck('id_escola')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($escolasIds === []) {
            return collect();
        }

        return Escola::query()
            ->whereIn('id', $escolasIds)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    public function getTurmasDisponiveisProperty(): Collection
    {
        if (! $this->avaliacaoAtual) {
            return collect();
        }

        return $this->turmasDaAvaliacao()
            ->when($this->escola, fn (Collection $turmas): Collection => $turmas
                ->filter(fn (Turma $turma): bool => (int) $turma->id_escola === (int) $this->escola)
                ->values())
            ->sortBy(fn (Turma $turma): string => mb_strtolower(implode('|', [
                (string) ($turma->escola?->nome ?? ''),
                (string) ($turma->serie?->nome ?? ''),
                (string) $turma->nome,
            ])))
            ->values();
    }

    public function getAlunosDisponiveisProperty(): Collection
    {
        $query = Aluno::query()
            ->with(['turma:id,nome,id_escola,id_serie', 'turma.escola:id,nome', 'turma.serie:id,nome'])
            ->whereHas('turma', fn (Builder $turmas): Builder => $this->aplicarEscopoTurmas($turmas));

        $busca = trim($this->buscaAluno);

        if ($busca !== '') {
            $query->where(function (Builder $alunos) use ($busca): void {
                $alunos
                    ->where('nome', 'like', '%'.$busca.'%')
                    ->orWhere('cgm', 'like', '%'.$busca.'%');
            });
        }

        return $query
            ->orderBy('nome')
            ->limit(50)
            ->get(['id', 'nome', 'cgm', 'id_turma']);
    }

    public function getAvaliacaoAtualProperty(): ?Avaliacao
    {
        return $this->avaliacoesDisponiveis->firstWhere('id', (int) $this->avaliacao);
    }

    public function getAlunoAtualProperty(): ?Aluno
    {
        if (! $this->aluno) {
            return null;
        }

        return Aluno::query()
            ->with(['turma:id,nome,id_escola,id_serie', 'turma.escola:id,nome', 'turma.serie:id,nome'])
            ->whereKey((int) $this->aluno)
            ->whereHas('turma', fn (Builder $turmas): Builder => $this->aplicarEscopoTurmas($turmas))
            ->first();
    }

    public function getPodeExportarSelecaoProperty(): bool
    {
        if (! $this->avaliacaoAtual || ! in_array($this->escopo, ['aluno', 'turma', 'escola'], true)) {
            return false;
        }

        return match ($this->escopo) {
            'aluno' => $this->alunoAtual !== null,
            'turma' => $this->turmasDisponiveis->contains('id', (int) $this->turma),
            'escola' => $this->escolasDisponiveis->contains('id', (int) $this->escola),
            default => false,
        };
    }

    public function getPdfUrlProperty(): ?string
    {
        return $this->podeExportarSelecao
            ? route('avaliacoes.documento.pdf', $this->parametrosExportacao())
            : null;
    }

    public function getCsvUrlProperty(): ?string
    {
        return $this->podeExportarSelecao
            ? route('avaliacoes.documento.csv', $this->parametrosExportacao())
            : null;
    }

    private function turmasDaAvaliacao(): Collection
    {
        if (! $this->avaliacaoAtual) {
            return collect();
        }

        return $this->avaliacaoAtual->turmas
            ->filter(fn (Turma $turma): bool => $this->turmaEstaNoEscopoDoUsuario($turma))
            ->values();
    }

    private function aplicarEscopoTurmas(Builder $query): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || $user->hasPermissionLike('listar avaliacoes')) {
            return $query;
        }

        $escolasIds = $user->idsEscolasVinculadas();

        if ($escolasIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id_escola', $escolasIds);
    }

    private function turmaEstaNoEscopoDoUsuario(Turma $turma): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || $user->hasPermissionLike('listar avaliacoes')) {
            return true;
        }

        return in_array((int) $turma->id_escola, $user->idsEscolasVinculadas(), true);
    }

    /**
     * @return array<string, int|string>
     */
    private function parametrosExportacao(): array
    {
        $params = [
            'avaliacao_id' => (int) $this->avaliacao,
            'escopo' => $this->escopo,
        ];

        if ($this->escopo === 'aluno') {
            $params['aluno_id'] = (int) $this->aluno;
        } elseif ($this->escopo === 'turma') {
            $params['turma_id'] = (int) $this->turma;
        } elseif ($this->escopo === 'escola') {
            $params['escola_id'] = (int) $this->escola;
        }

        return $params;
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
