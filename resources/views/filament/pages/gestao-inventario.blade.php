<x-filament-panels::page>
    @php($inventarioAtual = $this->inventarioAtual)

    <div class="gi-page">

        @if ($inventarioAtual)
            <section class="gi-cards">
                @foreach ($this->cards as $card)
                    <article class="gi-card">
                        <span>{{ $card['titulo'] }}</span>
                        <strong>{{ $card['valor'] }}</strong>
                        <small>{{ $card['descricao'] }}</small>
                    </article>
                @endforeach
            </section>

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div class="gi-toolbar-left">
                        @if ($this->inventariosDisponiveis->count() > 1)
                            <label class="gi-field gi-field--select">
                                <span>Inventário</span>
                                <select wire:model.live="inventario">
                                    @foreach ($this->inventariosDisponiveis as $inventarioId => $inventarioNome)
                                        <option value="{{ $inventarioId }}">{{ $inventarioNome }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <label class="gi-field">
                            <span>Buscar item</span>
                            <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Ex.: arroz, feijao, leite" />
                        </label>
                    </div>

                    <div class="gi-toolbar-right">
                        <label class="gi-field gi-field--small">
                            <span>Por página</span>
                            <select wire:model.live="porPagina">
                                <option value="8">8</option>
                                <option value="12">12</option>
                                <option value="20">20</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="gi-tabs">
                    @foreach ($this->abas as $aba)
                        <button type="button" wire:click="mudarAba('{{ $aba['value'] }}')" class="{{ $aba['value'] === $abaAtiva ? 'is-active' : '' }}">
                            {{ $aba['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="gi-table-wrap">
                    <table class="gi-table">
                        <thead>
                            <tr>
                                <th><button type="button" wire:click="sortBy('nome')">Item</button></th>
                                <th><button type="button" wire:click="sortBy('tipo_item')">Categoria</button></th>
                                <th><button type="button" wire:click="sortBy('quantidade')">Quantidade</button></th>
                                <th><button type="button" wire:click="sortBy('valor_total')">Valor estimado</button></th>
                                <th><button type="button" wire:click="sortBy('atualizado')">Atualizado</button></th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->itensFiltrados as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item['nome'] }}</strong>
                                        <small>{{ $item['descricao'] ?: 'Sem descrição' }}</small>
                                    </td>
                                    <td>{{ $item['tipo_label'] }}</td>
                                    <td>
                                        <strong>{{ number_format((float) $item['quantidade'], 3, ',', '.') }} {{ $item['unidade'] }}</strong>
                                        <small class="status status-{{ $item['status'] }}">{{ ucfirst($item['status']) }}</small>
                                    </td>
                                    <td>
                                        <strong>R$ {{ number_format((float) $item['valor_total'], 2, ',', '.') }}</strong>
                                        <small>R$ {{ number_format((float) $item['valor_unitario_referencia'], 2, ',', '.') }} por unidade</small>
                                    </td>
                                    <td>{{ $item['atualizado'] ?: 'N/A' }}</td>
                                    <td class="text-right">
                                        <div class="gi-row-actions">
                                            <button type="button" wire:click="abrirSlideOver({{ $item['inventario_estoque_id'] }})">Movimentações</button>
                                            <button type="button" wire:click="abrirModalBaixa({{ $item['inventario_estoque_id'] }})" @disabled((float) $item['quantidade'] <= 0)>Baixa</button>
                                            @if ($this->podeExportar)
                                                <a href="{{ route('gestao-inventario.item-relatorio.pdf', ['estoque' => $item['inventario_estoque_id'], 'async' => 1]) }}" target="_blank">Relatório</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="gi-empty">Nenhum item encontrado no inventário.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php($paginacao = $this->paginacao)
                <div class="gi-pagination">
                    <span>Mostrando {{ $paginacao['de'] }}-{{ $paginacao['ate'] }} de {{ $paginacao['total'] }}</span>
                    <div>
                        <button type="button" wire:click="mudarPagina({{ max(1, $paginacao['paginaAtual'] - 1) }})" @disabled($paginacao['paginaAtual'] === 1)>Anterior</button>
                        <button type="button" wire:click="mudarPagina({{ min($paginacao['totalPaginas'], $paginacao['paginaAtual'] + 1) }})" @disabled($paginacao['paginaAtual'] === $paginacao['totalPaginas'])>Proxima</button>
                    </div>
                </div>
            </section>
        @endif
    </div>

    @if ($slideOverAberto)
        <div class="gi-overlay" wire:click="fecharSlideOver"></div>
        <aside class="gi-slideover">
            <header>
                <div>
                    <p class="gi-eyebrow">Histórico do item</p>
                    <h3>{{ $itemSelecionadoNome }}</h3>
                    <small>{{ $totalMovimentacoesItem }} movimentações registradas</small>
                </div>
                <button type="button" wire:click="fecharSlideOver">Fechar</button>
            </header>

            <div class="gi-slideover-body">
                @forelse ($movimentacoes as $mov)
                    <article class="gi-mov">
                        <div>
                            <strong>{{ $mov['tipo_label'] }}</strong>
                            <small>{{ $mov['categoria'] }}</small>
                        </div>
                        <div class="gi-mov-meta">
                            <strong>{{ number_format((float) $mov['quantidade'], 3, ',', '.') }} {{ $itemSelecionadoUnidade }}</strong>
                            <small>{{ $mov['data'] }}</small>
                            <small>{{ $mov['registrado_por'] }}</small>
                        </div>
                    </article>
                @empty
                    <p class="gi-empty">Nenhuma movimentacao registrada.</p>
                @endforelse
            </div>
        </aside>
    @endif

    @if ($modalBaixaAberto)
        <div class="gi-overlay"></div>
        <section class="gi-modal">
            <header>
                <div>
                    <p class="gi-eyebrow">Registrar baixa</p>
                    <h3>{{ $baixaItemNome }}</h3>
                </div>
                <button type="button" wire:click="fecharModalBaixa">Fechar</button>
            </header>

            <div class="gi-modal-body">
                <label class="gi-field">
                    <span>Quantidade</span>
                    <input type="number" step="0.001" min="0.001" wire:model.defer="baixaQuantidade" />
                    @error('baixaQuantidade') <small class="error">{{ $message }}</small> @enderror
                </label>

                <label class="gi-field">
                    <span>Motivo</span>
                    <select wire:model.defer="baixaMotivo">
                        @foreach (\App\Models\Enums\MotivoBaixa::cases() as $motivo)
                            <option value="{{ $motivo->value }}">{{ $motivo->label() }}</option>
                        @endforeach
                    </select>
                    @error('baixaMotivo') <small class="error">{{ $message }}</small> @enderror
                </label>

                <label class="gi-field">
                    <span>Descrição</span>
                    <textarea rows="4" wire:model.defer="baixaDescricao"></textarea>
                    @error('baixaDescricao') <small class="error">{{ $message }}</small> @enderror
                </label>
            </div>

            <footer>
                <button type="button" wire:click="fecharModalBaixa" class="gi-action gi-action--ghost">Cancelar</button>
                <button type="button" wire:click="registrarBaixa" class="gi-action gi-action--primary">Confirmar baixa</button>
            </footer>
        </section>
    @endif

    @include('filament.pages.partials.inventory-page-styles')
</x-filament-panels::page>
