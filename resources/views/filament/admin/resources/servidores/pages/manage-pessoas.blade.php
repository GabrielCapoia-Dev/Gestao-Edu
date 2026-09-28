<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')
    @include('filament.pages.partials.pessoas-modal-styles')
    @include('filament.pages.partials.pessoa-view-styles')
    @include('filament.pages.partials.pessoas-responsive-table-styles')

    <div class="am-page pe-pessoas-page">
        {{ $this->content }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
