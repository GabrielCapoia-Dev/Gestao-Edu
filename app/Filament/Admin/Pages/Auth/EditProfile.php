<?php

namespace App\Filament\Admin\Pages\Auth;

use Caresome\FilamentAuthDesigner\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Pessoa;
use App\Models\Professor;

class EditProfile extends BaseEditProfile
{
    protected string $view = 'filament.admin.pages.auth.edit-profile';

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
        $data['profile_photo'] = $this->publicDiskPathFromAvatar($data['avatar_url'] ?? null);
        $data['cpf'] = $this->getPessoa()?->cpf;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $profilePhoto = Arr::first(Arr::wrap($data['profile_photo'] ?? null));

        unset($data['profile_photo']);
        unset($data['email']);

        if (filled($profilePhoto)) {
            $this->deletePreviousLocalAvatar((string) $profilePhoto);
            $data['avatar_url'] = $profilePhoto;
        }

        $this->cpfInformado = Pessoa::normalizarCpf($data['cpf'] ?? null);
        unset($data['cpf']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        $pessoa = $this->getPessoa();

        if ($pessoa && blank($pessoa->cpf) && filled($this->cpfInformado)) {
            $pessoa->update(['cpf' => $this->cpfInformado]);
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da conta')
                    ->description('Atualize sua foto e o nome exibido. O e-mail e o código são protegidos.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 12,
                        ])->schema([
                            $this->getProfilePhotoFormComponent()
                                ->columnSpan([
                                    'default' => 1,
                                    'lg' => 3,
                                ]),

                            Grid::make([
                                'default' => 1,
                                'md' => 2,
                            ])->schema([
                                $this->getNameFormComponent(),
                                $this->getEmailFormComponent(),
                            ])->columnSpan([
                                'default' => 1,
                                'lg' => 9,
                            ]),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'edu-profile-account-section']),

                Section::make('Dados funcionais')
                    ->description('Informações vinculadas ao cadastro da pessoa. Esses dados não podem ser alterados nesta tela.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])->schema([
                            $this->getCpfFormComponent(),
                            $this->getReadOnlyPlaceholder('cargo', 'Cargo', Heroicon::Briefcase, fn (): string => $this->getCargoLabel()),
                            $this->getReadOnlyPlaceholder('escola', 'Escola', Heroicon::BuildingOffice, fn (): string => $this->getEscolaLabel()),
                            $this->getReadOnlyPlaceholder('matricula', 'Matrícula', Heroicon::Identification, fn (): string => $this->getMatriculaLabel()),
                            $this->getReadOnlyPlaceholder('turno', 'Turno', Heroicon::Clock, fn (): string => $this->getTurnoLabel()),
                            $this->getReadOnlyPlaceholder('status_funcional', 'Status', Heroicon::CheckCircle, fn (): string => $this->getStatusLabel()),
                            $this->getReadOnlyPlaceholder('setor', 'Setor', Heroicon::BuildingOffice2, fn (): string => $this->getSetorLabel()),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'edu-profile-functional-section']),

                Section::make('Segurança da conta')
                    ->description('Preencha somente se quiser alterar sua senha.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                            $this->getCurrentPasswordFormComponent()->columnSpanFull(),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'edu-profile-password-section']),
            ]);
    }

    protected function getCpfFormComponent(): Component
    {
        return TextInput::make('cpf')
            ->label('CPF')
            ->prefixIcon('heroicon-o-identification')
            ->placeholder('Informe seu CPF')
            ->helperText('Depois de preenchido, o CPF só poderá ser corrigido pela equipe autorizada.')
            ->length(11)
            ->rule('digits:11')
            ->rule(fn (): Rule => Rule::unique('servidores', 'cpf')->ignore($this->getPessoa()?->getKey()))
            ->visible(fn (): bool => $this->hasCpfPending())
            ->dehydrated(fn (): bool => $this->hasCpfPending());
    }

    protected function getProfilePhotoFormComponent(): Component
    {
        return FileUpload::make('profile_photo')
            ->label('Foto de perfil')
            ->avatar()
            ->imageEditor()
            ->circleCropper()
            ->disk('public')
            ->directory('profile-photos')
            ->visibility('public')
            ->maxSize(2048)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->helperText('JPG, PNG ou WebP, até 2 MB.');
    }

    protected function getReadOnlyPlaceholder(string $name, string $label, Heroicon $icon, \Closure $content): Component
    {
        return Placeholder::make($name)
            ->label($label)
            ->icon($icon)
            ->iconColor('primary')
            ->content($content);
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
        return blank($this->getPessoa()?->cpf);
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
