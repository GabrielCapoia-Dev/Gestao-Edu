<x-filament-panels::page>
    <div class="ge-import">
        <section class="ge-import__panel">
            <div class="ge-import__intro">
                <div>
                    <h2 class="ge-import__title">Importação auditável</h2>
                    <p class="ge-import__description">
                        Baixe o modelo, preencha até 1.000 linhas e confira a pré-visualização. Nenhum evento é gravado antes da confirmação.
                    </p>
                </div>
                @if ($this->podeBaixarModelo())
                    <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="baixarModelo">
                        Baixar modelo XLSX
                    </x-filament::button>
                @endif
            </div>

            @if (! $this->importacao)
                <form wire:submit="preVisualizar" class="ge-import__form">
                    <label class="ge-import__file-field">
                        <span>Planilha XLSX ou CSV</span>
                        <input type="file" wire:model="arquivo" accept=".xlsx,.csv" class="ge-import__file-input">
                    </label>
                    @error('arquivo') <p class="ge-import__error">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="arquivo" class="ge-import__loading">Preparando arquivo...</div>
                    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled">
                        Validar e pré-visualizar
                    </x-filament::button>
                </form>
            @endif
        </section>

        @if ($importacao = $this->importacao)
            <section class="ge-import__panel">
                <div class="ge-import__stats">
                    <div class="ge-import__stat"><span>Linhas</span><strong>{{ $importacao->total_linhas }}</strong></div>
                    <div class="ge-import__stat ge-import__stat--success"><span>Válidas</span><strong>{{ $importacao->total_validas }}</strong></div>
                    <div class="ge-import__stat ge-import__stat--danger"><span>Inválidas</span><strong>{{ $importacao->total_invalidas }}</strong></div>
                    <div class="ge-import__stat"><span>Criadas</span><strong>{{ $importacao->total_criadas }}</strong></div>
                    <div class="ge-import__stat"><span>Atualizadas</span><strong>{{ $importacao->total_atualizadas }}</strong></div>
                </div>

                <div class="ge-import__table-wrap">
                    <table class="ge-import__table">
                        <thead>
                            <tr><th>Linha</th><th>Identificador</th><th>Título</th><th>Ação</th><th>Validação</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($importacao->linhas->take(100) as $linha)
                                <tr>
                                    <td>{{ $linha->numero_linha }}</td>
                                    <td>{{ $linha->dados_originais['fonte_externa'] ?? '-' }} / {{ $linha->dados_originais['identificador_externo'] ?? '-' }}</td>
                                    <td>{{ $linha->dados_originais['titulo'] ?? '-' }}</td>
                                    <td>{{ ucfirst($linha->acao->value) }}</td>
                                    <td>
                                        @if ($linha->erros)
                                            <ul class="ge-import__error-list">
                                                @foreach (collect($linha->erros)->flatten() as $erro)<li>{{ $erro }}</li>@endforeach
                                            </ul>
                                        @else
                                            <span class="ge-import__valid">Linha válida</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($importacao->linhas->count() > 100)
                    <p class="ge-import__hint">Exibindo as primeiras 100 linhas. O relatório final preserva todas as linhas.</p>
                @endif

                <div class="ge-import__actions">
                    @if (in_array($importacao->status->value, ['em_pre_visualizacao', 'pronta'], true))
                        <x-filament::button color="gray" wire:click="cancelar" wire:confirm="Cancelar esta importação?">Cancelar</x-filament::button>
                    @endif
                    @if ($importacao->status->value === 'pronta')
                        <x-filament::button color="success" icon="heroicon-o-check" wire:click="confirmar" wire:confirm="Confirmar a gravação de todos os eventos válidos?">
                            Confirmar importação
                        </x-filament::button>
                    @elseif ($importacao->status->value === 'concluida')
                        <x-filament::button tag="a" :href="\App\Filament\Admin\Pages\GerenciarEventos::getUrl()">Ver eventos</x-filament::button>
                    @else
                        <p class="ge-import__error">A confirmação só é liberada quando todas as linhas são válidas.</p>
                    @endif
                    <x-filament::button color="gray" wire:click="novaImportacao">Nova importação</x-filament::button>
                </div>
            </section>
        @endif

        @if ($this->historico->isNotEmpty())
            <section class="ge-import__panel">
                <h2 class="ge-import__title">Histórico de importações</h2>
                <p class="ge-import__description">Últimos 25 lotes que você possui autorização para consultar.</p>
                <div class="ge-import__table-wrap ge-import__table-wrap--history">
                    <table class="ge-import__table">
                        <thead><tr><th>Data</th><th>Arquivo</th><th>Responsável</th><th>Status</th><th>Resultado</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($this->historico as $lote)
                                <tr>
                                    <td>{{ $lote->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $lote->nome_arquivo }}</td>
                                    <td>{{ $lote->usuario?->name ?? '-' }}</td>
                                    <td>{{ str($lote->status->value)->replace('_', ' ')->title() }}</td>
                                    <td>{{ $lote->total_criadas }} criada(s), {{ $lote->total_atualizadas }} atualizada(s), {{ $lote->total_invalidas }} inválida(s)</td>
                                    <td><button type="button" class="ge-import__report-link" wire:click="abrirImportacao({{ $lote->id }})">Abrir relatório</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
