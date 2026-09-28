<div class="servidores-lw" wire:key="servidores-livewire-table" x-data="{
    copyTimer: null,
    copyFeedback: '',
    copyFeedbackType: 'success',
    async copyCell(element) {
        const text = element.dataset.copy ?? '';
        try {
            if (window.isSecureContext && navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text);
                } catch (error) {
                    await this.copyFallback(text);
                }
            } else {
                await this.copyFallback(text);
            }

            window.clearTimeout(this.copyTimer);
            this.copyFeedbackType = 'success';
            this.copyFeedback = 'Copiado!';
            this.copyTimer = window.setTimeout(() => {
                this.copyFeedback = '';
            }, 2500);
        } catch (error) {
            window.clearTimeout(this.copyTimer);
            this.copyFeedbackType = 'error';
            this.copyFeedback = 'Não foi possível copiar';
            this.copyTimer = window.setTimeout(() => {
                this.copyFeedback = '';
            }, 2500);
        }
    },
    async copyFallback(text) {
        const input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.focus();
        input.select();
        const copied = document.execCommand('copy');
        input.remove();
        if (!copied) throw new Error('clipboard_unavailable');
    }
}">
    <div class="servidores-lw__copy-feedback" x-cloak x-show="copyFeedback" :class="`servidores-lw__copy-feedback--${copyFeedbackType}`" role="status" aria-live="polite">
        <template x-if="copyFeedbackType === 'success'"><x-filament::icon icon="heroicon-o-check-circle" /></template>
        <template x-if="copyFeedbackType === 'error'"><x-filament::icon icon="heroicon-o-exclamation-circle" /></template>
        <span x-text="copyFeedback"></span>
    </div>
    <div class="servidores-lw__toolbar">
        <div class="servidores-lw__search">
            <label for="servidores-busca">Buscar servidores</label>
            <div class="servidores-lw__search-control">
                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                <input id="servidores-busca" type="search" wire:model.live.debounce.350ms="search" placeholder="Nome, CPF, e-mail ou matrícula" />
            </div>
        </div>

        <div class="servidores-lw__toolbar-actions">
            <button type="button" class="servidores-lw__button servidores-lw__button--ghost" wire:click="exportarFiltrados">
                <x-filament::icon icon="heroicon-o-document-arrow-down" />
                Exportar filtrados
            </button>
            <label class="servidores-lw__page-size">
                <span>Por página</span>
                <select wire:model.live="perPage">
                    @foreach ([5, 10, 25, 50, 100] as $quantidade)
                        <option value="{{ $quantidade }}">{{ $quantidade }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <details class="servidores-lw__filters" @if($cargo || $quantidadeMatriculas || $turnoMatricula || $status || $escola || $nivelAcesso || $arquivados !== 'sem' || $emailDuplicado) open @endif>
        <summary>
            <span><x-filament::icon icon="heroicon-o-adjustments-horizontal" /> Filtros avançados</span>
            <span class="servidores-lw__filter-count">{{ collect([$cargo, $quantidadeMatriculas, $turnoMatricula, $status, $escola, $nivelAcesso, $emailDuplicado])->flatten()->filter()->count() }}</span>
        </summary>
        <div class="servidores-lw__filter-grid servidores-lw__filter-grid--filament">
            {{ $this->filtrosForm }}
        </div>
        <div class="servidores-lw__filter-actions">
            <button type="button" class="servidores-lw__button servidores-lw__button--primary" wire:click="aplicarFiltros">Aplicar filtros</button>
            <button type="button" class="servidores-lw__button servidores-lw__button--link" wire:click="limparFiltros">Limpar filtros</button>
        </div>
    </details>

    <div class="servidores-lw__table-header">
        <div class="servidores-lw__selection-summary">
            <label class="servidores-lw__select-page">
                <input type="checkbox" wire:click="selecionarPagina(@js($servidores->pluck('id')->all()))" @checked($servidores->isNotEmpty() && $servidores->pluck('id')->every(fn ($id) => in_array($id, $selecionados, true))) />
                Selecionar página
            </label>
            @if (count($selecionados))
                <span>{{ count($selecionados) }} selecionado(s)</span>
                <button type="button" class="servidores-lw__button servidores-lw__button--link" wire:click="limparSelecao">Limpar seleção</button>
            @endif
        </div>
        <details class="servidores-lw__columns">
            <summary><x-filament::icon icon="heroicon-o-view-columns" /> Colunas</summary>
            <div class="servidores-lw__columns-menu">
                @foreach ($colunas as $chave => $label)
                    @if ($chave !== 'acesso' || Gate::allows('viewAny', \App\Models\User::class))
                    <label><input type="checkbox" wire:model.live="colunasVisiveis.{{ $chave }}" /> {{ $label }}</label>
                    @endif
                @endforeach
            </div>
        </details>
    </div>

    @if (count($selecionados))
        <div class="servidores-lw__bulkbar">
            <select wire:model="acaoEmMassa">
                <option value="">Ações em massa</option>
                @foreach ($this->acoesEmMassaDisponiveis() as $acao => $label)
                    <option value="{{ $acao }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="servidores-lw__button servidores-lw__button--primary" wire:click="executarAcaoEmMassa">Executar</button>
        </div>
    @endif

    <div class="servidores-lw__table-wrap servidores-lw__desktop-table">
        <table class="servidores-lw__table">
            <thead>
                <tr>
                    <th class="servidores-lw__check-col"></th>
                    @if ($colunasVisiveis['identidade']) <th><button type="button" wire:click="ordenar('nome')">Pessoa <x-filament::icon icon="heroicon-m-arrows-up-down" /></button></th> @endif
                    @if ($colunasVisiveis['cargo']) <th>Cargo</th> @endif
                    @if ($colunasVisiveis['escola']) <th>Escola</th> @endif
                    @if ($colunasVisiveis['matricula']) <th>Matrícula</th> @endif
                    @if ($colunasVisiveis['email']) <th><button type="button" wire:click="ordenar('email')">E-mail <x-filament::icon icon="heroicon-m-arrows-up-down" /></button></th> @endif
                    @if ($colunasVisiveis['status']) <th><button type="button" wire:click="ordenar('status')">Status <x-filament::icon icon="heroicon-m-arrows-up-down" /></button></th> @endif
                    @if ($colunasVisiveis['acesso'] && Gate::allows('viewAny', \App\Models\User::class)) <th>Acesso</th> @endif
                    @if ($colunasVisiveis['atualizado']) <th><button type="button" wire:click="ordenar('updated_at')">Atualizado <x-filament::icon icon="heroicon-m-arrows-up-down" /></button></th> @endif
                    <th class="servidores-lw__actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($servidores as $servidor)
                    <tr wire:key="servidor-row-{{ $servidor->id }}">
                        <td class="servidores-lw__check-col"><input type="checkbox" wire:click="alternarSelecionado({{ $servidor->id }})" @disabled(! \App\Filament\Admin\Resources\Servidores\ServidorResource::pessoaPodeSerSelecionada($servidor)) @checked(in_array($servidor->id, $selecionados, true)) /></td>
                        @if ($colunasVisiveis['identidade'])
                            <td data-label="Pessoa / CPF">
                                <button type="button" class="servidores-lw__copy servidores-lw__copy--identity" data-copy="{{ $servidor->nome }}" title="Clique para copiar o nome" aria-label="Copiar nome: {{ $servidor->nome }}" x-on:click.stop="copyCell($el)"><strong>{{ $servidor->nome }}</strong></button>
                                <button type="button" class="servidores-lw__copy servidores-lw__copy--identity" data-copy="{{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}" title="Clique para copiar o CPF" aria-label="Copiar CPF: {{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}" x-on:click.stop="copyCell($el)"><small>{{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}</small></button>
                            </td>
                        @endif
                        @if ($colunasVisiveis['cargo']) <td data-label="Cargo"><button type="button" class="servidores-lw__copy" data-copy="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::cargoLabel($servidor) }}" title="Clique para copiar" x-on:click.stop="copyCell($el)"><span class="servidores-lw__badge servidores-lw__badge--blue">{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::cargoLabel($servidor) }}</span></button></td> @endif
                        @if ($colunasVisiveis['escola']) <td data-label="Escola"><button type="button" class="servidores-lw__copy" data-copy="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::exibeEscolaNaListagem($servidor) ? \App\Filament\Admin\Resources\Servidores\ServidorResource::escolasLabel($servidor) : '—' }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::exibeEscolaNaListagem($servidor) ? \App\Filament\Admin\Resources\Servidores\ServidorResource::escolasLabel($servidor) : '—' }}</button></td> @endif
                        @if ($colunasVisiveis['matricula']) <td data-label="Matrícula"><button type="button" class="servidores-lw__copy" data-copy="{{ $this->matriculasLabel($servidor) }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ $this->matriculasLabel($servidor) }}</button></td> @endif
                        @if ($colunasVisiveis['email']) <td class="servidores-lw__email" data-label="E-mail"><button type="button" class="servidores-lw__copy" data-copy="{{ $servidor->user?->email ?: $servidor->email ?: '—' }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ $servidor->user?->email ?: $servidor->email ?: '—' }}</button></td> @endif
                        @if ($colunasVisiveis['status']) <td data-label="Status"><button type="button" class="servidores-lw__copy" data-copy="{{ $this->statusLabel($servidor) }}" title="Clique para copiar" x-on:click.stop="copyCell($el)"><span class="servidores-lw__badge servidores-lw__badge--{{ $this->statusColor($servidor) }}">{{ $this->statusLabel($servidor) }}</span></button></td> @endif
                        @if ($colunasVisiveis['acesso'] && Gate::allows('viewAny', \App\Models\User::class)) <td data-label="Acesso"><button type="button" class="servidores-lw__copy" data-copy="{{ $servidor->user?->roles?->pluck('name')->join(', ') ?: '—' }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ $servidor->user?->roles?->pluck('name')->join(', ') ?: '—' }}</button></td> @endif
                        @if ($colunasVisiveis['atualizado']) <td data-label="Atualizado em"><button type="button" class="servidores-lw__copy" data-copy="{{ optional($servidor->updated_at)->format('d/m/Y H:i') }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ optional($servidor->updated_at)->format('d/m/Y H:i') }}</button></td> @endif
                        <td class="servidores-lw__actions-col" data-label="Ações">
                            @include('livewire.pessoas.partials.servidor-table-actions', ['servidor' => $servidor])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="servidores-lw__empty">Nenhum servidor encontrado com os filtros informados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="servidores-lw__mobile-cards" role="list" aria-label="Servidores">
        @forelse ($servidores as $servidor)
            <article class="servidores-lw__mobile-card" role="listitem" wire:key="servidor-mobile-card-{{ $servidor->id }}">
                <div class="servidores-lw__mobile-select">
                    <input type="checkbox" wire:click="alternarSelecionado({{ $servidor->id }})" @disabled(! \App\Filament\Admin\Resources\Servidores\ServidorResource::pessoaPodeSerSelecionada($servidor)) @checked(in_array($servidor->id, $selecionados, true)) aria-label="Selecionar {{ $servidor->nome }}" />
                </div>
                <div class="servidores-lw__mobile-fields">
                    @if ($colunasVisiveis['identidade'])
                        <div class="servidores-lw__mobile-field">
                            <span class="servidores-lw__mobile-label">Pessoa / CPF</span>
                            <button type="button" class="servidores-lw__copy servidores-lw__copy--identity" data-copy="{{ $servidor->nome }}" title="Clique para copiar o nome" aria-label="Copiar nome: {{ $servidor->nome }}" x-on:click.stop="copyCell($el)"><strong>{{ $servidor->nome }}</strong></button>
                            <button type="button" class="servidores-lw__copy servidores-lw__copy--identity" data-copy="{{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}" title="Clique para copiar o CPF" aria-label="Copiar CPF: {{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}" x-on:click.stop="copyCell($el)"><small>{{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}</small></button>
                        </div>
                    @endif
                    @if ($colunasVisiveis['cargo'])
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">Cargo</span><button type="button" class="servidores-lw__copy" data-copy="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::cargoLabel($servidor) }}" title="Clique para copiar" x-on:click.stop="copyCell($el)"><span class="servidores-lw__badge servidores-lw__badge--blue">{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::cargoLabel($servidor) }}</span></button></div>
                    @endif
                    @if ($colunasVisiveis['escola'])
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">Escola</span><button type="button" class="servidores-lw__copy" data-copy="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::exibeEscolaNaListagem($servidor) ? \App\Filament\Admin\Resources\Servidores\ServidorResource::escolasLabel($servidor) : '—' }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::exibeEscolaNaListagem($servidor) ? \App\Filament\Admin\Resources\Servidores\ServidorResource::escolasLabel($servidor) : '—' }}</button></div>
                    @endif
                    @if ($colunasVisiveis['matricula'])
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">Matrícula</span><button type="button" class="servidores-lw__copy" data-copy="{{ $this->matriculasLabel($servidor) }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ $this->matriculasLabel($servidor) }}</button></div>
                    @endif
                    @if ($colunasVisiveis['email'])
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">E-mail</span><button type="button" class="servidores-lw__copy servidores-lw__email" data-copy="{{ $servidor->user?->email ?: $servidor->email ?: '—' }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ $servidor->user?->email ?: $servidor->email ?: '—' }}</button></div>
                    @endif
                    @if ($colunasVisiveis['status'])
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">Status</span><button type="button" class="servidores-lw__copy" data-copy="{{ $this->statusLabel($servidor) }}" title="Clique para copiar" x-on:click.stop="copyCell($el)"><span class="servidores-lw__badge servidores-lw__badge--{{ $this->statusColor($servidor) }}">{{ $this->statusLabel($servidor) }}</span></button></div>
                    @endif
                    @if ($colunasVisiveis['acesso'] && Gate::allows('viewAny', \App\Models\User::class))
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">Acesso</span><button type="button" class="servidores-lw__copy" data-copy="{{ $servidor->user?->roles?->pluck('name')->join(', ') ?: '—' }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ $servidor->user?->roles?->pluck('name')->join(', ') ?: '—' }}</button></div>
                    @endif
                    @if ($colunasVisiveis['atualizado'])
                        <div class="servidores-lw__mobile-field"><span class="servidores-lw__mobile-label">Atualizado em</span><button type="button" class="servidores-lw__copy" data-copy="{{ optional($servidor->updated_at)->format('d/m/Y H:i') }}" title="Clique para copiar" x-on:click.stop="copyCell($el)">{{ optional($servidor->updated_at)->format('d/m/Y H:i') }}</button></div>
                    @endif
                </div>
                <div class="servidores-lw__mobile-actions">
                    <span class="servidores-lw__mobile-label">Ações</span>
                    @include('livewire.pessoas.partials.servidor-table-actions', ['servidor' => $servidor])
                </div>
            </article>
        @empty
            <div class="servidores-lw__empty">Nenhum servidor encontrado com os filtros informados.</div>
        @endforelse
    </div>

    <div class="servidores-lw__footer">
        <span>Exibindo {{ $servidores->firstItem() ?: 0 }}–{{ $servidores->lastItem() ?: 0 }} de {{ $servidores->total() }} servidores</span>
        @if ($servidores->hasPages())
            <nav class="servidores-lw__pagination" aria-label="Paginação de servidores">
                <button type="button" wire:click="previousPage" @disabled($servidores->onFirstPage()) aria-label="Página anterior">Anterior</button>
                @foreach ($servidores->getUrlRange(max(1, $servidores->currentPage() - 2), min($servidores->lastPage(), $servidores->currentPage() + 2)) as $pagina => $url)
                    <button type="button" wire:click="gotoPage({{ $pagina }})" @if ($pagina === $servidores->currentPage()) aria-current="page" @endif aria-label="Página {{ $pagina }}">{{ $pagina }}</button>
                @endforeach
                <button type="button" wire:click="nextPage" @disabled(! $servidores->hasMorePages()) aria-label="Próxima página">Próxima</button>
            </nav>
        @endif
    </div>
</div>
