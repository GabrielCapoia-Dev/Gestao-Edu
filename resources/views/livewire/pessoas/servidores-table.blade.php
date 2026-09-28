<div class="servidores-lw" wire:key="servidores-livewire-table" x-data x-on:click="const cell = $event.target.closest('[data-copy]'); if (cell) navigator.clipboard.writeText(cell.dataset.copy)">
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
        <div class="servidores-lw__filter-grid">
            <label>Cargo
                <select wire:model="cargo" multiple>
                    @foreach ($opcoesCargos as $valor => $label)
                        <option value="{{ $valor }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Quantidade de matrículas
                <select wire:model="quantidadeMatriculas">
                    <option value="">Todas</option>
                    <option value="uma">Uma matrícula</option>
                    <option value="duas">Duas matrículas</option>
                    <option value="tres_ou_mais">Três ou mais</option>
                    <option value="sem">Sem matrícula</option>
                </select>
            </label>
            <label>Turno da matrícula
                <select wire:model="turnoMatricula" multiple>
                    @foreach ($opcoesTurnos as $valor => $label)
                        <option value="{{ $valor }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select wire:model="status" multiple>
                    @foreach ($opcoesStatus as $valor => $label)
                        <option value="{{ $valor }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Escola
                <select wire:model="escola" multiple>
                    @foreach ($opcoesEscolas as $valor => $label)
                        <option value="{{ $valor }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if (Gate::allows('viewAny', \App\Models\User::class))
                <label>Nível de acesso
                    <select wire:model="nivelAcesso" multiple>
                        @foreach ($opcoesNiveis as $valor => $label)
                            <option value="{{ $valor }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>Arquivados
                <select wire:model="arquivados">
                    <option value="sem">Sem arquivados</option>
                    <option value="com">Com arquivados</option>
                    <option value="somente">Somente arquivados</option>
                </select>
            </label>
            @if (Gate::allows('viewAny', \App\Models\ProfessorComponenteSolicitacao::class))
                <label>Solicitações de vínculo
                    <select wire:model="solicitacoesPendentes">
                        <option value="">Todas</option>
                        <option value="sim">Com solicitações</option>
                        <option value="nao">Sem solicitações</option>
                    </select>
                </label>
            @endif
            <label class="servidores-lw__checkbox-label">
                <input type="checkbox" wire:model="emailDuplicado" /> E-mail duplicado
            </label>
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
                <option value="exportar_selecionados">Exportar selecionados</option>
                <option value="alterar_status_em_massa">Alterar status</option>
                <option value="criar_acessos_em_massa">Criar acessos</option>
                <option value="verificacao_acesso_em_massa">Verificar acessos</option>
                <option value="redefinir_senha_em_massa">Redefinir senhas</option>
                <option value="niveis_em_massa">Alterar níveis de acesso</option>
                <option value="permissoes_em_massa">Alterar permissões</option>
                <option value="excluir_acessos_em_massa">Excluir acessos</option>
                <option value="delete">Arquivar selecionados</option>
                <option value="restore">Restaurar selecionados</option>
            </select>
            <button type="button" class="servidores-lw__button servidores-lw__button--primary" wire:click="executarAcaoEmMassa">Executar</button>
        </div>
    @endif

    <div class="servidores-lw__table-wrap">
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
                            <td data-copy="{{ $servidor->nome }}&#10;{{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}" title="Clique para copiar nome e CPF"><strong>{{ $servidor->nome }}</strong><small>{{ $servidor->cpf ? \App\Filament\Admin\Resources\Servidores\ServidorResource::formatarCpf($servidor->cpf) : 'CPF não informado' }}</small></td>
                        @endif
                        @if ($colunasVisiveis['cargo']) <td data-copy="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::cargoLabel($servidor) }}" title="Clique para copiar"><span class="servidores-lw__badge servidores-lw__badge--blue">{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::cargoLabel($servidor) }}</span></td> @endif
                        @if ($colunasVisiveis['escola']) <td data-copy="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::exibeEscolaNaListagem($servidor) ? \App\Filament\Admin\Resources\Servidores\ServidorResource::escolasLabel($servidor) : '—' }}" title="Clique para copiar">{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::exibeEscolaNaListagem($servidor) ? \App\Filament\Admin\Resources\Servidores\ServidorResource::escolasLabel($servidor) : '—' }}</td> @endif
                        @if ($colunasVisiveis['matricula']) <td data-copy="{{ $this->matriculasLabel($servidor) }}" title="Clique para copiar">{{ $this->matriculasLabel($servidor) }}</td> @endif
                        @if ($colunasVisiveis['email']) <td class="servidores-lw__email" data-copy="{{ $servidor->user?->email ?: $servidor->email ?: '—' }}" title="Clique para copiar">{{ $servidor->user?->email ?: $servidor->email ?: '—' }}</td> @endif
                        @if ($colunasVisiveis['status']) <td data-copy="{{ $this->statusLabel($servidor) }}" title="Clique para copiar"><span class="servidores-lw__badge servidores-lw__badge--{{ $this->statusColor($servidor) }}">{{ $this->statusLabel($servidor) }}</span></td> @endif
                        @if ($colunasVisiveis['acesso'] && Gate::allows('viewAny', \App\Models\User::class)) <td data-copy="{{ $servidor->user?->roles?->pluck('name')->join(', ') ?: '—' }}" title="Clique para copiar">{{ $servidor->user?->roles?->pluck('name')->join(', ') ?: '—' }}</td> @endif
                        @if ($colunasVisiveis['atualizado']) <td data-copy="{{ optional($servidor->updated_at)->format('d/m/Y H:i') }}" title="Clique para copiar">{{ optional($servidor->updated_at)->format('d/m/Y H:i') }}</td> @endif
                        <td class="servidores-lw__actions-col"><button type="button" class="servidores-lw__action" wire:click="abrirAcao('view', {{ $servidor->id }})" title="Visualizar"><x-filament::icon icon="heroicon-o-eye" /></button><button type="button" class="servidores-lw__action" wire:click="abrirAcao('edit', {{ $servidor->id }})" title="Editar"><x-filament::icon icon="heroicon-o-pencil-square" /></button><details class="servidores-lw__row-menu"><summary class="servidores-lw__action" title="Mais ações"><x-filament::icon icon="heroicon-m-ellipsis-vertical" /></summary><div class="servidores-lw__row-menu-panel"><button type="button" wire:click="abrirAcao('alterar_status', {{ $servidor->id }})">Alterar status</button><button type="button" wire:click="abrirAcao('criar_acesso', {{ $servidor->id }})">Criar acesso</button><button type="button" wire:click="abrirAcao('gerenciar_acesso', {{ $servidor->id }})">Gerenciar acesso</button><button type="button" wire:click="abrirAcao('redefinir_senha', {{ $servidor->id }})">Redefinir senha</button><button type="button" wire:click="abrirAcao('excluir_acesso', {{ $servidor->id }})">Excluir acesso</button><button type="button" wire:click="abrirAcao('analisar_solicitacoes_professor', {{ $servidor->id }})">Solicitações</button><button type="button" wire:click="abrirAcao('delete', {{ $servidor->id }})">Arquivar</button><button type="button" wire:click="abrirAcao('restore', {{ $servidor->id }})">Restaurar</button></div></details></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="servidores-lw__empty">Nenhum servidor encontrado com os filtros informados.</td></tr>
                @endforelse
            </tbody>
        </table>
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
