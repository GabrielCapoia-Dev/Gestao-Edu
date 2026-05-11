@php
    $user = $this->getUser();
@endphp

<div class="edu-profile-page">
    <aside class="edu-profile-summary" aria-label="Resumo do perfil">
        <div class="edu-profile-summary-pattern"></div>

        <div class="edu-profile-avatar-wrap">
            <img
                src="{{ $this->getAvatarPreviewUrl() }}"
                alt="Foto de {{ $user->name }}"
                class="edu-profile-avatar"
            >
            <span class="edu-profile-avatar-initials">{{ $this->getProfileInitials() }}</span>
        </div>

        <div class="edu-profile-summary-text">
            <span class="edu-profile-kicker">Perfil</span>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }}</p>
        </div>

        <dl class="edu-profile-meta">
            <div>
                <dt>Status</dt>
                <dd>{{ $user->email_approved ? 'Aprovado' : 'Pendente' }}</dd>
            </div>
            <div>
                <dt>Codigo</dt>
                <dd>{{ $user->codigo ?: 'Sem codigo' }}</dd>
            </div>
        </dl>
    </aside>

    <section class="edu-profile-form-panel" aria-label="Formulario do perfil">
        <div class="edu-profile-form-heading">
            <span>Conta</span>
            <h2>Editar perfil</h2>
        </div>

        {{ $this->content }}
    </section>
</div>

<x-filament-actions::modals />
