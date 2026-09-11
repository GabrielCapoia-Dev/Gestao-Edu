@php
    $user = $this->getUser();
    $isApproved = $user->canAuthenticate();
    $cpfPendente = $this->hasCpfPending();
@endphp

<div class="edu-profile-shell">
    <header class="edu-profile-hero" aria-label="Resumo do perfil">
        <div class="edu-profile-hero-main">
            <div class="edu-profile-avatar-wrap">
                @if (filled($user->avatar_url))
                    <img src="{{ $this->getAvatarPreviewUrl() }}" alt="Foto de {{ $user->name }}" class="edu-profile-avatar">
                @else
                    <span class="edu-profile-avatar-initials">{{ $this->getProfileInitials() }}</span>
                @endif
                <span
                    class="edu-profile-avatar-status {{ $cpfPendente ? 'is-warning' : ($isApproved ? 'is-approved' : 'is-pending') }}"
                    aria-label="{{ $cpfPendente ? 'CPF pendente' : 'Status: '.($isApproved ? 'ativo' : 'inativo') }}"
                ></span>
            </div>

            <div class="edu-profile-hero-copy">
                <div class="edu-profile-kicker-row">
                    <span class="edu-profile-heading-kicker">Minha conta</span>
                    @if ($cpfPendente)
                        <span class="edu-profile-cpf-alert" title="CPF não informado"><span aria-hidden="true">!</span> CPF pendente</span>
                    @endif
                </div>
                <h1>{{ $user->name }}</h1>
                <p>{{ $this->getCargoLabel() }} <span aria-hidden="true">•</span> {{ $user->email }}</p>
            </div>
        </div>

        <div class="edu-profile-hero-meta">
            <span class="edu-profile-status-pill {{ $isApproved ? 'is-approved' : 'is-pending' }}">
                {{ $isApproved ? 'Ativo' : 'Inativo' }}
            </span>
            <span class="edu-profile-code">ID {{ $user->codigo ?: 'não informado' }}</span>
        </div>
    </header>

    @if ($cpfPendente)
        <div class="edu-profile-alert" role="status">
            <span class="edu-profile-alert-icon">!</span>
            <div>
                <strong>Complete seu cadastro</strong>
                <p>Seu CPF ainda não foi informado. Preencha-o abaixo para manter seus dados atualizados.</p>
            </div>
        </div>
    @endif

    <section class="edu-profile-form-panel" aria-label="Formulario do perfil">
        <div class="edu-profile-form-heading">
            <div>
                <span class="edu-profile-heading-kicker">Configurações</span>
                <h2>Seu perfil</h2>
            </div>
            <p>Atualize os dados permitidos e consulte suas informações funcionais.</p>
        </div>

        {{ $this->content }}
    </section>

    <x-filament-actions::modals />
</div>
