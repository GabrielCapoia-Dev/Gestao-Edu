@php
    $user = $this->getUser();
    $isApproved = (bool) $user->email_approved;
@endphp

<div class="edu-profile-shell">
    <header class="edu-profile-heading">
        <div>
            <span class="edu-profile-heading-kicker">Conta</span>
            <h1>Meu Perfil</h1>
            <p>Atualize sua foto, seus dados de acesso e as informacoes da conta.</p>
        </div>
    </header>

    <div class="edu-profile-page">
        <aside class="edu-profile-summary" aria-label="Resumo do perfil">
            <div class="edu-profile-hero">
                <div class="edu-profile-summary-pattern"></div>

                <span class="edu-profile-kicker">Perfil</span>

                <div class="edu-profile-avatar-wrap">
                    <img
                        src="{{ $this->getAvatarPreviewUrl() }}"
                        alt="Foto de {{ $user->name }}"
                        class="edu-profile-avatar"
                    >
                    <span class="edu-profile-avatar-initials">{{ $this->getProfileInitials() }}</span>
                    <span
                        class="edu-profile-avatar-status {{ $isApproved ? 'is-approved' : 'is-pending' }}"
                        aria-label="Status: {{ $isApproved ? 'aprovado' : 'pendente' }}"
                    ></span>
                </div>

                <div class="edu-profile-summary-text">
                    <h2>{{ $user->name }}</h2>
                    <p>{{ $user->email }}</p>
                </div>
            </div>

            <dl class="edu-profile-meta">
                <div>
                    <dt>Status</dt>
                    <dd>
                        <span class="edu-profile-status-pill {{ $isApproved ? 'is-approved' : 'is-pending' }}">
                            {{ $isApproved ? 'Aprovado' : 'Pendente' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Codigo</dt>
                    <dd>{{ $user->codigo ?: 'Sem codigo' }}</dd>
                </div>
                <div>
                    <dt>Avatar</dt>
                    <dd>{{ filled($user->avatar_url) ? 'Personalizado' : 'Padrao' }}</dd>
                </div>
            </dl>
        </aside>

        <section class="edu-profile-form-panel" aria-label="Formulario do perfil">
            <div class="edu-profile-form-heading">
                <span>Configuracoes</span>
                <h2>Editar perfil</h2>
            </div>

            {{ $this->content }}
        </section>
    </div>

    <x-filament-actions::modals />
</div>
