<!-- resources/views/livewire/topbar.blade.php -->
<div
    x-data="{ collapsed: $store.sidebar?.isCollapsed ?? false }"
    class="flex items-center gap-3 px-5 border-b"
    style="height: 56px; background: #ffffff; border-color: #d4d8e6;">

    {{-- Botão de colapsar sidebar --}}
    <button
        x-on:click="$store.sidebar.toggle()"
        class="inline-flex items-center justify-center rounded-md transition-colors duration-150"
        style="
            width: 32px; height: 32px;
            border: 0.5px solid #d4d8e6;
            background: transparent;
            color: #2d3756;
            cursor: pointer;
        "
        title="Alternar menu">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <rect y="2" width="16" height="1.5" rx="0.75" fill="currentColor" />
            <rect y="7.25" width="16" height="1.5" rx="0.75" fill="currentColor" />
            <rect y="12.5" width="16" height="1.5" rx="0.75" fill="currentColor" />
        </svg>
    </button>

    {{-- Espaço flexível --}}
    <div class="flex-1"></div>

    {{-- Notificações --}}
    <div>
        @livewire('topbar-notifications')
    </div>

</div>