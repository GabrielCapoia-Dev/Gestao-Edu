<div class="profile-preview-topbar">
    @if ($active && $targetUser)
        <form method="POST" action="{{ route('profile-preview.stop') }}" class="profile-preview-active">
            @csrf
            <span class="profile-preview-active-label">
                Visualizando: <strong>{{ $targetUser->name }}</strong>
            </span>
            <button type="submit" class="profile-preview-stop-button">
                Voltar a normalidade
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('profile-preview.start') }}" class="profile-preview-form">
            @csrf
            <label for="profile-preview-target" class="sr-only">Visualizar como usuario</label>
            <select
                id="profile-preview-target"
                name="target_user_id"
                class="profile-preview-select"
                required
                onchange="if (this.value) this.form.submit()"
            >
                <option value="">Visualizar perfil...</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">
                        {{ $user->name }} - {{ $user->email }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif
</div>
