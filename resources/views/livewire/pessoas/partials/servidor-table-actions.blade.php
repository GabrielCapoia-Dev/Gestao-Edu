<div class="servidores-lw__action-group">
    @if (Gate::allows('view', $servidor))
        <button type="button" class="servidores-lw__action" wire:click="abrirAcao('view', {{ $servidor->id }})" title="Visualizar"><x-filament::icon icon="heroicon-o-eye" /></button>
    @endif
    @if (Gate::allows('update', $servidor))
        <button type="button" class="servidores-lw__action" wire:click="abrirAcao('edit', {{ $servidor->id }})" title="Editar"><x-filament::icon icon="heroicon-o-pencil-square" /></button>
    @endif
    <details class="servidores-lw__row-menu">
        <summary class="servidores-lw__action" title="Mais ações"><x-filament::icon icon="heroicon-m-ellipsis-vertical" /></summary>
        <div class="servidores-lw__row-menu-panel">
            @if (\App\Filament\Admin\Resources\Servidores\ServidorResource::usuarioPodeGerenciarEstrutura($servidor))
                <button type="button" wire:click="abrirAcao('alterar_status', {{ $servidor->id }})">Alterar status</button>
            @endif
            @if ($servidor->user && Gate::allows('viewAny', \App\Models\User::class) && Gate::allows('applyPermissions', $servidor->user))
                <button type="button" wire:click="abrirAcao('gerenciar_acesso', {{ $servidor->id }})">Gerenciar acesso</button>
            @endif
            @if ($servidor->user && Gate::allows('resetPassword', $servidor->user))
                <button type="button" wire:click="abrirAcao('redefinir_senha', {{ $servidor->id }})">Redefinir senha</button>
            @endif
            @if (Gate::allows('delete', $servidor))
                <button type="button" wire:click="abrirAcao('delete', {{ $servidor->id }})">Arquivar</button>
            @endif
            @if ($servidor->trashed() && Gate::allows('restore', $servidor))
                <button type="button" wire:click="abrirAcao('restore', {{ $servidor->id }})">Restaurar</button>
            @endif
            @if ($servidor->solicitacoes_pendentes_count > 0)
                <button type="button" wire:click="abrirAcao('analisar_solicitacoes_professor', {{ $servidor->id }})">Solicitações</button>
            @endif
        </div>
    </details>
</div>
