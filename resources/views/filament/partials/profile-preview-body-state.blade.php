@php($preview = app(\App\Services\ProfilePreviewService::class))

@if ($preview->isActive() && ($targetUser = $preview->targetUser()))
    <script>
        document.documentElement.classList.add('profile-preview-mode');
        document.body?.classList.add('profile-preview-mode');
    </script>
    <div class="profile-preview-banner">
        <span>Modo visualizacao</span>
        <strong>{{ $targetUser->name }}</strong>
        <form method="POST" action="{{ route('profile-preview.stop') }}">
            @csrf
            <button type="submit">Sair</button>
        </form>
    </div>
@endif
