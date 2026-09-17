<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\ProfessorComponenteSolicitacao;
use App\Models\TurmaComponenteProfessor;
use App\Models\Turma;
use App\Services\ProfessorComponenteSolicitacaoService;
use App\Services\PerfilEquipeGestoraService;
use Caresome\FilamentAuthDesigner\Pages\Auth\EditProfile as BaseEditProfile;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EditProfile extends BaseEditProfile
{
    use WithFileUploads;

    protected string $view = 'filament.admin.pages.auth.edit-profile';

    public mixed $profilePhoto = null;

    public array $componentesFuncionaisState = ['componentes' => []];

    private ?string $cpfInformado = null;

    private ?Pessoa $pessoaCache = null;

    public function getTitle(): string
    {
        return 'Perfil';
    }

    public function getAvatarPreviewUrl(): string
    {
        return Filament::getUserAvatarUrl($this->getUser());
    }

    public function getPhotoPreviewUrl(): string
    {
        if ($this->profilePhoto instanceof TemporaryUploadedFile) {
            return $this->profilePhoto->temporaryUrl();
        }

        return $this->getAvatarPreviewUrl();
    }

    public function getProfileInitials(): string
    {
        return str($this->getUser()->name)
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->filter()
            ->take(2)
            ->join('');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cpf'] = $this->getPessoa()?->cpf;
        $data['portaria'] = app(PerfilEquipeGestoraService::class)
            ->vinculosDoPerfil($this->getUser())
            ->pluck('portaria')
            ->filter()
            ->first();
        $this->componentesFuncionaisState['componentes'] = $this->getProfessorFunctionalComponentIds();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['email']);

        if ($this->profilePhoto instanceof TemporaryUploadedFile) {
            $this->validate([
                'profilePhoto' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ], [
                'profilePhoto.image' => 'Selecione uma imagem válida.',
                'profilePhoto.mimes' => 'A foto deve ser JPG, PNG ou WebP.',
                'profilePhoto.max' => 'A foto deve ter no máximo 2 MB.',
            ]);

            $profilePhotoPath = $this->profilePhoto->storePublicly('profile-photos', 'public');
            $this->deletePreviousLocalAvatar($profilePhotoPath);
            $data['avatar_url'] = $profilePhotoPath;
            $this->profilePhoto = null;
        }

        $this->cpfInformado = $this->getUser()->hasRole('Admin') ? null : Pessoa::normalizarCpf($data['cpf'] ?? null);
        unset($data['cpf']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        $pessoa = $this->getPessoa();

        if (array_key_exists('portaria', $data) && filled($data['portaria'])) {
            app(PerfilEquipeGestoraService::class)->atualizarPortaria($this->getUser(), (string) $data['portaria']);
        }

        if ($pessoa && blank($pessoa->cpf) && filled($this->cpfInformado)) {
            $pessoa->update(['cpf' => $this->cpfInformado]);
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getCpfFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                TextInput::make('portaria')->hidden(),
            ]);
    }

    public function componentesFuncionaisForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('componentes')
                    ->label('Vínculo funcional')
                    ->options(fn (): array => $this->getProfessorComponentOptions()->pluck('nome', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live(false)
                    ->placeholder('Selecione os componentes que você leciona')
                    ->default(fn (): array => $this->getProfessorFunctionalComponentIds())
                    ->helperText('Os componentes selecionados serão usados para filtrar as turmas abaixo.'),
            ])
            ->statePath('componentesFuncionaisState');
    }

    protected function getCpfFormComponent(): Component
    {
        return TextInput::make('cpf')
            ->label('CPF')
            ->prefixIcon('heroicon-o-identification')
            ->placeholder('000.000.000-00')
            ->helperText('Depois de preenchido, o CPF só poderá ser corrigido pela equipe autorizada.')
            ->nullable()
            ->rule('regex:/^(?:\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2})$/')
            ->validationMessages(['regex' => 'Use o formato 000.000.000-00.'])
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $cpf = Pessoa::normalizarCpf((string) $value);

                if (! Pessoa::cpfValido($cpf)) {
                    $fail('Informe um CPF válido.');

                    return;
                }

                if (Pessoa::withTrashed()->where('cpf', $cpf)->whereKeyNot($this->getPessoa()?->getKey())->exists()) {
                    $fail('Este CPF já está vinculado a outra pessoa.');
                }
            })
            ->visible(fn (): bool => $this->hasCpfPending())
            ->dehydrated(fn (): bool => $this->hasCpfPending());
    }

    public function getPessoa(): ?Pessoa
    {
        if ($this->pessoaCache) {
            return $this->pessoaCache;
        }

        return $this->pessoaCache = $this->getUser()->servidores()
            ->with(['escola', 'setor', 'professores.escola', 'professores.professorMatricula', 'vinculosAtivos.funcaoAdministrativa'])
            ->first();
    }

    public function hasCpfPending(): bool
    {
        return ! $this->getUser()->hasRole('Admin') && ($pessoa = $this->getPessoa()) && blank($pessoa->cpf);
    }

    public function getCargoLabel(): string
    {
        $pessoa = $this->getPessoa();
        $cargos = $pessoa?->vinculosAtivos->map(fn ($vinculo) => $vinculo->funcaoAdministrativa?->nome)->filter()->values() ?? collect();
        if ($pessoa?->professores->contains(fn ($professor): bool => (bool) $professor->ativo)) {
            $cargos->prepend('Professor');
        }

        return $cargos->unique()->implode(', ') ?: 'Não informado';
    }

    public function getEscolaLabel(): string
    {
        $pessoa = $this->getPessoa();

        return collect([$pessoa?->escola?->nome])->merge($pessoa?->professores->map(fn ($professor) => $professor->escola?->nome) ?? [])->filter()->unique()->implode(', ') ?: 'Não informada';
    }

    public function getMatriculaLabel(): string
    {
        $pessoa = $this->getPessoa();

        return collect([$pessoa?->matricula])->merge($pessoa?->professores->map(fn ($professor) => $professor->matricula) ?? [])->filter()->unique()->implode(', ') ?: 'Não informada';
    }

    public function getTurnoLabel(): string
    {
        $turnos = $this->getPessoa()?->professores->map(fn ($professor) => $professor->turnoEfetivo())->filter()->map(fn (string $turno) => Professor::TURNOS[$turno] ?? $turno)->unique() ?? collect();

        return $turnos->implode(', ') ?: 'Não informado';
    }

    public function getStatusLabel(): string
    {
        return $this->getPessoa()?->status === Pessoa::STATUS_ATIVO ? 'Ativo' : 'Inativo';
    }

    public function getSetorLabel(): string
    {
        return $this->getPessoa()?->setor?->nome ?: 'Não informado';
    }

    public function hasProfessorProfile(): bool
    {
        return $this->getUser()->professores()->where('ativo', true)->exists();
    }

    public function getGestaoVinculos(): Collection
    {
        return app(PerfilEquipeGestoraService::class)->vinculosDoPerfil($this->getUser());
    }

    public function hasCoordinatorProfile(): bool
    {
        return $this->getGestaoVinculos()->contains(
            fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->coordenacao_pedagogica,
        );
    }

    public function getCoordinatorTurmas(int $vinculoId): Collection
    {
        $vinculo = $this->getGestaoVinculos()->firstWhere('id', $vinculoId);

        return $vinculo?->escola
            ? Turma::query()->with('serie')->where('id_escola', $vinculo->id_escola)->orderBy('nome')->get()
            : new Collection();
    }

    public function toggleCoordinatorTurma(int $turmaId, bool $vincular): void
    {
        try {
            app(PerfilEquipeGestoraService::class)->alternarTurma($this->getUser(), $turmaId, $vincular);
            $this->pessoaCache = null;

            Notification::make()
                ->title($vincular ? 'Turma vinculada' : 'Turma desvinculada')
                ->body($vincular ? 'Você assumiu a coordenação desta turma.' : 'Você foi removido desta turma.')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyProfessorLinkError($exception);
        }
    }

    /** @return Collection<int, TurmaComponenteProfessor> */
    public function getProfessorCurrentLinks(): Collection
    {
        return app(ProfessorComponenteSolicitacaoService::class)->vinculosAtuais($this->getUser());
    }

    /** @return Collection<int, \App\Models\ComponenteCurricular> */
    public function getProfessorComponentOptions(): Collection
    {
        return \App\Models\ComponenteCurricular::query()->orderBy('nome')->get();
    }

    /** @return array<int, int> */
    public function getProfessorFunctionalComponentIds(): array
    {
        return app(ProfessorComponenteSolicitacaoService::class)->componentesFuncionais($this->getUser())
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function confirmProfessorFunctionalComponents(): void
    {
        try {
            app(ProfessorComponenteSolicitacaoService::class)->salvarComponentesFuncionais(
                $this->getUser(), $this->componentesFuncionaisState['componentes'] ?? [],
            );

            Notification::make()
                ->title('Vínculo funcional atualizado')
                ->body('A lista de turmas foi atualizada conforme os componentes selecionados.')
                ->success()->send();
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyProfessorLinkError($exception);
        }
    }


    /** @return Collection<int, TurmaComponenteProfessor> */
    public function getProfessorAvailableLinks(): Collection
    {
        return app(ProfessorComponenteSolicitacaoService::class)->opcoesDisponiveis($this->getUser());
    }

    /** @return Collection<int, ProfessorComponenteSolicitacao> */
    public function getProfessorRequests(): Collection
    {
        return app(ProfessorComponenteSolicitacaoService::class)->solicitacoesDoProfessor($this->getUser());
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    public function getProfessorContexts(): SupportCollection
    {
        return app(ProfessorComponenteSolicitacaoService::class)->contextosDoProfessor($this->getUser());
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    public function getProfessorLinkedContexts(SupportCollection $contexts): SupportCollection
    {
        return $contexts->map(function (array $context): array {
            $escolas = collect($context['escolas'])->map(function (array $escola): array {
                $series = collect($escola['series'])->map(function (array $serie): array {
                    $turmas = collect($serie['turmas'])->map(function (array $turma): ?array {
                        $componentes = collect($turma['componentes'])
                            ->filter(fn (array $opcao): bool => $opcao['estado'] === 'meu')
                            ->values();

                        return $componentes->isEmpty()
                            ? null
                            : [...$turma, 'componentes' => $componentes];
                    })->filter()->values();

                    return $turmas->isEmpty() ? null : [...$serie, 'turmas' => $turmas];
                })->filter()->values();

                return $series->isEmpty() ? null : [...$escola, 'series' => $series];
            })->filter()->values();

            return [...$context, 'escolas' => $escolas];
        })->filter(fn (array $context): bool => $context['escolas']->isNotEmpty())->values();
    }

    public function canReviewProfessorRequests(): bool
    {
        return app(ProfessorComponenteSolicitacaoService::class)->podeAnalisar($this->getUser());
    }

    /** @return Collection<int, ProfessorComponenteSolicitacao> */
    public function getProfessorRequestsForReview(): Collection
    {
        return app(ProfessorComponenteSolicitacaoService::class)->solicitacoesParaAnalise($this->getUser());
    }

    public function requestProfessorLink(int $vinculoId): void
    {
        try {
            app(ProfessorComponenteSolicitacaoService::class)->solicitar($this->getUser(), $vinculoId);

            Notification::make()
                ->title('Solicitação enviada')
                ->body('O vínculo ficará pendente até a aprovação da administração ou da equipe gestora.')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyProfessorLinkError($exception);
        }
    }

    public function requestProfessorComponent(int $professorId, int $turmaId, int $componenteId): void
    {
        try {
            app(ProfessorComponenteSolicitacaoService::class)->solicitarComponente(
                $this->getUser(), $professorId, $turmaId, $componenteId,
            );

            Notification::make()
                ->title('Solicitação enviada')
                ->body('O vínculo ficará pendente até a aprovação da administração ou da equipe gestora.')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyProfessorLinkError($exception);
        }
    }

    public function approveProfessorLink(int $solicitacaoId): void
    {
        try {
            app(ProfessorComponenteSolicitacaoService::class)->aprovar($this->getUser(), $solicitacaoId);

            Notification::make()
                ->title('Vínculo aprovado')
                ->body('O professor já possui acesso válido à turma e ao componente.')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyProfessorLinkError($exception);
        }
    }

    public function rejectProfessorLink(int $solicitacaoId): void
    {
        try {
            app(ProfessorComponenteSolicitacaoService::class)->rejeitar($this->getUser(), $solicitacaoId);

            Notification::make()
                ->title('Solicitação recusada')
                ->warning()
                ->send();
        } catch (AuthorizationException|ValidationException $exception) {
            $this->notifyProfessorLinkError($exception);
        }
    }

    private function notifyProfessorLinkError(AuthorizationException|ValidationException $exception): void
    {
        $message = $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->first()
            : $exception->getMessage();

        Notification::make()
            ->title('Não foi possível concluir a ação')
            ->body($message ?: 'Verifique os dados e tente novamente.')
            ->danger()
            ->send();
    }

    protected function deletePreviousLocalAvatar(string $newAvatarPath): void
    {
        $previousAvatarPath = $this->publicDiskPathFromAvatar($this->getUser()->avatar_url);

        if (filled($previousAvatarPath) && $previousAvatarPath !== $newAvatarPath) {
            Storage::disk('public')->delete($previousAvatarPath);
        }
    }

    protected function publicDiskPathFromAvatar(?string $avatarUrl): ?string
    {
        if (blank($avatarUrl)) {
            return null;
        }

        $avatarUrl = trim($avatarUrl);

        if (Str::startsWith($avatarUrl, ['http://', 'https://', 'data:image/'])) {
            return null;
        }

        if (Str::startsWith($avatarUrl, ['/storage/', 'storage/'])) {
            return Str::after($avatarUrl, 'storage/');
        }

        return Str::startsWith($avatarUrl, '/') ? null : $avatarUrl;
    }

    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()
            ->label('Nome')
            ->prefixIcon('heroicon-o-user');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('E-mail')
            ->prefixIcon('heroicon-o-envelope')
            ->disabled()
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Nova senha')
            ->prefixIcon('heroicon-o-lock-closed');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirmar senha')
            ->prefixIcon('heroicon-o-lock-closed');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->label('Senha atual')
            ->prefixIcon('heroicon-o-key')
            ->visible(fn (Get $get): bool => filled($get('password')));
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Salvar perfil')
            ->icon('heroicon-o-check');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('back')
            ->label('Voltar ao Início')
            ->icon('heroicon-o-home')
            ->color('gray')
            ->action(fn () => $this->redirect(Filament::getUrl()));
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }
}
