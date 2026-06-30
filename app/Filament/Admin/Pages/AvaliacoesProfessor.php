<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AvaliacoesProfessor extends Page
{
    protected string $view = 'filament.pages.avaliacoes-professor';

    protected static ?string $title = 'Avaliações';

    protected static ?string $navigationLabel = 'Minhas Avaliações';

    protected static ?string $slug = 'avaliacoes-professor';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public ?int $initialAvaliacaoId = null;

    public ?int $initialTurmaId = null;

    public ?int $initialEscolaId = null;

    public ?int $initialSerieId = null;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Pedagógico',
            'title' => 'Minhas Avaliações',
            'description' => 'Preencha suas avaliações, gerencie as respostas e mantenha um registro atualizado das informações.',
        ]);
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasPermissionLike('listar avaliacoes')
            || $user->hasPermissionLike('responder avaliacoes');
    }

    public function podeResponder(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionLike('responder avaliacoes') ?? false;
    }

    public function mount(): void
    {
        $this->initialAvaliacaoId = $this->normalizarQueryId(request()->query('avaliacao'));
        $this->initialTurmaId = $this->normalizarQueryId(request()->query('turma'));
        $this->initialEscolaId = $this->normalizarQueryId(request()->query('escola'));
        $this->initialSerieId = $this->normalizarQueryId(request()->query('serie'));
    }

    private function normalizarQueryId(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
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
