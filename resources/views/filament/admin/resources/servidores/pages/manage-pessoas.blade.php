<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')
    @include('filament.pages.partials.pessoas-modal-styles')

    <div class="am-page pe-pessoas-page">
        {{ $this->content }}
    </div>
</x-filament-panels::page>
