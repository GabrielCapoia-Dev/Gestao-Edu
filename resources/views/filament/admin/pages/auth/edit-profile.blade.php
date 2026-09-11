@php
    $user = $this->getUser();
    $isApproved = $user->canAuthenticate();
    $cpfPendente = $this->hasCpfPending();
    $pessoa = $this->getPessoa();
@endphp

<div class="profile-page">
    <form wire:submit="save" class="profile-page__form">
        <header class="profile-hero">
            <div class="profile-hero__identity">
                <label class="profile-photo" title="Alterar foto de perfil">
                    @if ($this->profilePhoto || filled($user->avatar_url))
                        <img src="{{ $this->getPhotoPreviewUrl() }}" alt="Foto de {{ $user->name }}">
                    @else
                        <span>{{ $this->getProfileInitials() }}</span>
                    @endif
                    <input wire:model="profilePhoto" type="file" accept="image/jpeg,image/png,image/webp">
                    <span class="profile-photo__edit" aria-hidden="true"><x-filament::icon icon="heroicon-o-camera" /></span>
                    @if ($cpfPendente)<span class="profile-photo__notice" title="CPF pendente">!</span>@endif
                </label>

                <div class="profile-hero__copy">
                    <span class="profile-eyebrow">Meu perfil</span>
                    <h1>{{ $user->name }}</h1>
                    <p>{{ $this->getCargoLabel() }}</p>
                    <div class="profile-hero__badges">
                        <span class="profile-badge profile-badge--{{ $isApproved ? 'success' : 'warning' }}"><span></span>{{ $isApproved ? 'Conta ativa' : 'Conta inativa' }}</span>
                        <span class="profile-badge">{{ $user->email }}</span>
                    </div>
                </div>
            </div>

            <div class="profile-hero__account"><span>Identificador da conta</span><strong>{{ $user->codigo ?: 'Não informado' }}</strong></div>
        </header>

        @error('profilePhoto')<p class="profile-field-error">{{ $message }}</p>@enderror

        @if ($cpfPendente)
            <aside class="profile-completion" role="status">
                <span class="profile-completion__icon">!</span>
                <div><strong>Seu cadastro está quase completo</strong><p>Informe o CPF. Após o primeiro preenchimento, apenas uma equipe autorizada poderá alterá-lo.</p></div>
            </aside>
        @endif

        <div class="profile-layout">
            <section class="profile-card">
                <div class="profile-card__heading">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-user-circle" /></span>
                    <div><h2>Dados da conta</h2><p>Informações que você pode atualizar.</p></div>
                </div>

                <div class="profile-fields">
                    <label class="profile-field">
                        <span>Nome</span>
                        <span class="profile-input"><x-filament::icon icon="heroicon-o-user" /><input wire:model="data.name" type="text" autocomplete="name" maxlength="255" required></span>
                        @error('data.name')<small class="profile-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="profile-field">
                        <span>E-mail</span>
                        <span class="profile-input profile-input--readonly"><x-filament::icon icon="heroicon-o-envelope" /><input value="{{ $user->email }}" type="email" disabled><em>Protegido</em></span>
                    </label>
                    @if ($cpfPendente)
                        <label class="profile-field profile-field--full">
                            <span>CPF</span>
                            <span class="profile-input"><x-filament::icon icon="heroicon-o-identification" /><input wire:model="data.cpf" type="text" inputmode="numeric" maxlength="11" placeholder="Somente números"></span>
                            @error('data.cpf')<small class="profile-field-error">{{ $message }}</small>@enderror
                        </label>
                    @else
                        <div class="profile-readonly profile-field--full">
                            <span class="profile-readonly__icon"><x-filament::icon icon="heroicon-o-identification" /></span>
                            <span><small>CPF</small><strong>{{ \App\Models\Pessoa::formatarCpf($pessoa?->cpf) }}</strong></span><em>Somente leitura</em>
                        </div>
                    @endif
                </div>
            </section>

            <section class="profile-card">
                <div class="profile-card__heading">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-briefcase" /></span>
                    <div><h2>Ficha funcional</h2><p>Dados definidos pela administração.</p></div>
                </div>

                <div class="profile-facts">
                    @foreach ([
                        ['icon' => 'heroicon-o-briefcase', 'label' => 'Cargo', 'value' => $this->getCargoLabel()],
                        ['icon' => 'heroicon-o-building-office', 'label' => 'Escola', 'value' => $this->getEscolaLabel()],
                        ['icon' => 'heroicon-o-identification', 'label' => 'Matrícula', 'value' => $this->getMatriculaLabel()],
                        ['icon' => 'heroicon-o-clock', 'label' => 'Turno', 'value' => $this->getTurnoLabel()],
                        ['icon' => 'heroicon-o-building-office-2', 'label' => 'Setor', 'value' => $this->getSetorLabel()],
                        ['icon' => 'heroicon-o-check-circle', 'label' => 'Status', 'value' => $this->getStatusLabel()],
                    ] as $fact)
                        <div class="profile-fact"><span><x-filament::icon :icon="$fact['icon']" /></span><div><small>{{ $fact['label'] }}</small><strong>{{ $fact['value'] }}</strong></div></div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="profile-card">
            <div class="profile-card__heading">
                <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-shield-check" /></span>
                <div><h2>Segurança da conta</h2><p>Preencha apenas se desejar trocar a senha.</p></div>
            </div>
            <div class="profile-fields profile-fields--security">
                <label class="profile-field"><span>Nova senha</span><span class="profile-input"><x-filament::icon icon="heroicon-o-lock-closed" /><input wire:model.live.debounce.500ms="data.password" type="password" autocomplete="new-password"></span>@error('data.password')<small class="profile-field-error">{{ $message }}</small>@enderror</label>
                <label class="profile-field"><span>Confirmar nova senha</span><span class="profile-input"><x-filament::icon icon="heroicon-o-lock-closed" /><input wire:model="data.passwordConfirmation" type="password" autocomplete="new-password"></span></label>
                <label class="profile-field"><span>Senha atual</span><span class="profile-input"><x-filament::icon icon="heroicon-o-key" /><input wire:model="data.currentPassword" type="password" autocomplete="current-password"></span>@error('data.currentPassword')<small class="profile-field-error">{{ $message }}</small>@enderror</label>
            </div>
        </section>

        <footer class="profile-actions">
            <a href="{{ filament()->getUrl() }}" class="profile-button profile-button--secondary"><x-filament::icon icon="heroicon-o-arrow-left" /> Voltar ao início</a>
            <button type="submit" class="profile-button profile-button--primary" wire:loading.attr="disabled" wire:target="save,profilePhoto"><x-filament::icon icon="heroicon-o-check" /><span wire:loading.remove wire:target="save">Salvar alterações</span><span wire:loading wire:target="save">Salvando...</span></button>
        </footer>
    </form>

    <x-filament-actions::modals />
</div>
