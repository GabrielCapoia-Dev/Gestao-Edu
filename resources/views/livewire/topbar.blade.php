<!-- resources/views/livewire/topbar.blade.php -->
<div class="flex items-center justify-between px-6 h-16 bg-white border-b">

    <button x-on:click="$store.sidebar.toggle()">
        ☰
    </button>

    <div>
        @livewire('topbar-notifications')
    </div>

</div>