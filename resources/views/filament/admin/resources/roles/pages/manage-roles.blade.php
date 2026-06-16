<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    @php($cards = collect($this->getOverviewCards())->take(3)->values())
    @php($roles = $this->getDesktopRoles())
    @php($canCreateRoles = $this->canCreateRoles())

    <div class="am-page">
        <section class="am-panel">
            <div class="am-panel__header">
                <div class="am-desktop-header">
                    <div>
                        <p class="am-panel__eyebrow">Biblioteca de níveis</p>
                        <h3 class="am-panel__title">Níveis de acesso</h3>
                        <p class="am-panel__subtitle">Gerencie os grupos de permissões com uma visualização mais clara para desktop.</p>
                    </div>

                    <div class="am-toolbar">
                        <label class="am-search">
                            <x-filament::icon
                                icon="heroicon-o-magnifying-glass"
                                class="am-search__icon"
                            />

                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Buscar nível de acesso ou permissão..."
                            />
                        </label>

                        @if ($canCreateRoles)
                            <x-filament::button
                                color="primary"
                                wire:click="mountAction('create')"
                            >
                                Novo nível
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="am-summary">
                @foreach ($cards as $card)
                    <article class="am-summary-card am-summary-card--{{ $card['tone'] }}">
                        <div class="am-summary-card__top">
                            <div class="am-summary-card__icon">
                                <x-filament::icon :icon="$card['icon']" />
                            </div>
                            <div>
                                <p class="am-summary-card__label">{{ $card['label'] }}</p>
                                <p class="am-summary-card__value">{{ $card['value'] }}</p>
                            </div>
                        </div>

                        <p class="am-summary-card__description">{{ $card['description'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="am-panel__body">
                @if ($roles->isEmpty())
                    <div class="am-empty-state">
                        <div class="am-empty-state__icon">
                            <x-filament::icon icon="heroicon-o-shield-check" />
                        </div>

                        <div>
                            <h4>Nenhum nível encontrado</h4>
                            <p>Refine a busca ou crie um novo grupo de permissões para comecar a organizar os acessos.</p>
                        </div>
                    </div>
                @else
                    <div class="am-roles-grid">
                        @foreach ($roles as $role)
                            <article class="am-role-card am-role-card--{{ $role['palette']['accent'] }} {{ $role['expanded'] ? 'is-expanded' : 'is-collapsed' }}">
                                <div class="am-role-card__header">
                                    <div class="am-role-card__identity">
                                        <div class="am-role-card__icon">
                                            <x-filament::icon :icon="$role['palette']['icon']" />
                                        </div>

                                        <div>
                                            <h4>{{ $role['name'] }}</h4>
                                            <p>
                                                {{ $role['permission_count'] }} permissões ativas
                                                @if (count($role['summary']))
                                                    - {{ implode(' - ', $role['summary']->all()) }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="am-role-card__actions">
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            wire:click="toggleRoleExpansion('{{ $role['id'] }}')"
                                        >
                                            {{ $role['expanded'] ? 'Fechar' : 'Abrir' }}
                                        </x-filament::button>

                                        @if ($role['actions']['edit'])
                                            <x-filament::button
                                                size="sm"
                                                color="gray"
                                                wire:click="mountTableAction('editar', '{{ $role['id'] }}')"
                                            >
                                                Editar
                                            </x-filament::button>
                                        @endif

                                        @if ($role['actions']['delete'])
                                            <x-filament::icon-button
                                                color="danger"
                                                icon="heroicon-o-trash"
                                                size="sm"
                                                wire:click="mountTableAction('delete', '{{ $role['id'] }}')"
                                            />
                                        @endif
                                    </div>
                                </div>

                                @if ($role['expanded'])
                                    <div class="am-role-card__permissions">
                                        @foreach ($role['grouped_permissions'] as $permissionGroup)
                                            <section class="am-permission-group">
                                                <div class="am-permission-group__header">
                                                    <div>
                                                        <h5>{{ $permissionGroup['name'] }}</h5>
                                                        <p>{{ $permissionGroup['count'] }} permissões</p>
                                                    </div>
                                                </div>

                                                <div class="am-permission-group__items">
                                                    @foreach ($permissionGroup['items'] as $permission)
                                                        <div class="am-permission-chip">
                                                            <div class="am-permission-chip__label">
                                                                <x-filament::icon :icon="$permission['icon']" />
                                                                <span>{{ $permission['label'] }}</span>
                                                            </div>

                                                            <span class="am-permission-chip__status">
                                                                <x-filament::icon icon="heroicon-o-check" />
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
