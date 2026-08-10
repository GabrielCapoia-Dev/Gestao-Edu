@php
    $user = $this->getUser();
    $isApproved = $user->canAuthenticate();
@endphp

<div class="edu-profile-shell">
    <header class="edu-profile-heading" aria-label="Resumo do perfil">
        <div class="edu-profile-heading-main">
            <div class="edu-profile-avatar-wrap">
                <img
                    src="{{ $this->getAvatarPreviewUrl() }}"
                    alt="Foto de {{ $user->name }}"
                    class="edu-profile-avatar"
                >
                <span class="edu-profile-avatar-initials">{{ $this->getProfileInitials() }}</span>
                <span
                    class="edu-profile-avatar-status {{ $isApproved ? 'is-approved' : 'is-pending' }}"
                    aria-label="Status: {{ $isApproved ? 'ativo' : 'inativo' }}"
                ></span>
            </div>

            <div>
                <span class="edu-profile-heading-kicker">Conta</span>
                <h1>Meu Perfil</h1>
                <p>{{ $user->name }} - {{ $user->email }}</p>
            </div>
        </div>

        <div class="edu-profile-heading-meta">
            <span class="edu-profile-status-pill {{ $isApproved ? 'is-approved' : 'is-pending' }}">
                {{ $isApproved ? 'Ativo' : 'Inativo' }}
            </span>
            <span>{{ $user->codigo ?: 'Sem codigo' }}</span>
        </div>
    </header>

    <section class="edu-profile-form-panel" aria-label="Formulario do perfil">
        <div class="edu-profile-form-heading">
            <span class="edu-profile-heading-kicker">Conta</span>
            <h2>Editar perfil</h2>
        </div>

        {{ $this->content }}
    </section>

    <x-filament-actions::modals />
</div>
