<?php

namespace App\Filament\Admin\Pages;

use App\Models\ImportacaoEventoCalendario;
use App\Models\User;
use App\Services\Dashboard\Imports\EventoCalendarioImportService;
use App\Services\ProfilePreviewService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ImportarEventosCalendario extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'eventos-calendario/importar';

    protected static ?string $title = 'Importar eventos';

    protected string $view = 'filament.admin.pages.importar-eventos-calendario';

    public $arquivo = null;

    public ?int $importacaoId = null;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Eventos da agenda',
            'title' => 'Importar eventos',
            'description' => 'Valide a planilha, confira cada linha e confirme a gravação somente depois da pré-visualização.',
        ]);
    }

    public static function canAccess(): bool
    {
        $user = static::usuarioEfetivo();

        return $user && Gate::forUser($user)->allows('create', ImportacaoEventoCalendario::class);
    }

    public function preVisualizar(): void
    {
        $this->validate([
            'arquivo' => [
                'required',
                'file',
                'max:'.(int) config('dashboard.imports.max_file_size_kb', 5120),
                'extensions:xlsx,csv',
                'mimes:xlsx,csv,txt',
            ],
        ], [
            'arquivo.required' => 'Selecione uma planilha.',
            'arquivo.max' => 'A planilha deve possuir no máximo 5 MB.',
            'arquivo.mimes' => 'Envie um arquivo XLSX ou CSV.',
        ]);

        $user = static::usuarioEfetivo();
        abort_unless($user && $this->arquivo instanceof TemporaryUploadedFile, 403);

        $importacao = app(EventoCalendarioImportService::class)->preview($this->arquivo, $user);
        $this->importacaoId = (int) $importacao->getKey();
        $this->arquivo = null;

        Notification::make()
            ->title($importacao->total_invalidas > 0 ? 'Planilha possui linhas inválidas' : 'Pré-visualização pronta')
            ->body("{$importacao->total_validas} linha(s) válida(s) e {$importacao->total_invalidas} inválida(s).")
            ->color($importacao->total_invalidas > 0 ? 'warning' : 'success')
            ->send();
    }

    public function confirmar(): void
    {
        $importacao = $this->importacaoAutorizada();
        $user = static::usuarioEfetivo();
        abort_unless($user, 403);

        app(EventoCalendarioImportService::class)->confirm($importacao, $user);

        Notification::make()->title('Importação concluída')->success()->send();
    }

    public function cancelar(): void
    {
        $importacao = $this->importacaoAutorizada();
        $user = static::usuarioEfetivo();
        abort_unless($user, 403);

        app(EventoCalendarioImportService::class)->cancel($importacao, $user);
        $this->importacaoId = null;
        Notification::make()->title('Importação cancelada')->success()->send();
    }

    public function baixarModelo()
    {
        $user = static::usuarioEfetivo();
        abort_unless($user, 403);

        return app(EventoCalendarioImportService::class)->template($user);
    }

    public function abrirImportacao(int $id): void
    {
        $user = static::usuarioEfetivo();
        $importacao = ImportacaoEventoCalendario::query()->find($id);
        abort_unless($user && $importacao && Gate::forUser($user)->allows('view', $importacao), 404);

        $this->importacaoId = $id;
    }

    public function novaImportacao(): void
    {
        $this->importacaoId = null;
        $this->arquivo = null;
        $this->resetValidation();
    }

    public function podeBaixarModelo(): bool
    {
        $user = static::usuarioEfetivo();

        return $user && Gate::forUser($user)->allows('exportTemplate', ImportacaoEventoCalendario::class);
    }

    public function getHistoricoProperty(): Collection
    {
        $user = static::usuarioEfetivo();

        if (! $user || ! Gate::forUser($user)->allows('viewAny', ImportacaoEventoCalendario::class)) {
            return collect();
        }

        $query = ImportacaoEventoCalendario::query()
            ->with('usuario:id,name')
            ->latest();

        if (! app(\App\Services\PessoaScopeService::class)->hasGlobalAccess($user)) {
            $query->where('usuario_id', $user->getKey());
        }

        return $query->limit(25)->get();
    }

    public function getImportacaoProperty(): ?ImportacaoEventoCalendario
    {
        if (! $this->importacaoId) {
            return null;
        }

        $user = static::usuarioEfetivo();

        if (! $user) {
            return null;
        }

        $importacao = ImportacaoEventoCalendario::query()
            ->with('linhas')
            ->find($this->importacaoId);

        return $importacao && Gate::forUser($user)->allows('view', $importacao)
            ? $importacao
            : null;
    }

    private function importacaoAutorizada(): ImportacaoEventoCalendario
    {
        $importacao = $this->importacao;
        $user = static::usuarioEfetivo();
        abort_unless($user && $importacao && Gate::forUser($user)->allows('view', $importacao), 404);

        return $importacao;
    }

    private static function usuarioEfetivo(): ?User
    {
        return app(ProfilePreviewService::class)->effectiveUser();
    }
}
