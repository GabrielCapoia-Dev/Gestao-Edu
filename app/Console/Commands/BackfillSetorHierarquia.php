<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\PedidoMerenda;
use App\Models\Setor;
use App\Models\User;
use App\Services\SetorHierarchyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillSetorHierarquia extends Command
{
    protected $signature = 'setores:backfill-hierarquia
        {--dry-run : Apenas mostra contagens, sem gravar}
        {--execute : Aplica as correcoes}
        {--root-name= : Nome do setor raiz configuravel}';

    protected $description = 'Preenche a nova hierarquia de setores e vinculos operacionais sem depender de nomes fixos.';

    private bool $execute = false;

    public function handle(SetorHierarchyService $hierarchy): int
    {
        if (! $this->schemaPronto()) {
            $this->error('Execute as migrations antes do backfill de setores.');

            return self::FAILURE;
        }

        $this->execute = (bool) $this->option('execute');

        if (! $this->execute || $this->option('dry-run')) {
            $this->execute = false;
            $this->warn('Dry-run: nenhuma alteracao sera gravada. Use --execute para aplicar.');
        }

        DB::transaction(function () use ($hierarchy): void {
            $root = $this->resolverRoot();

            $this->vincularSetoresSoltos($root);
            $this->vincularEscolas($root);
            $this->vincularUsuarios();
            $this->vincularEmpresas($root);
            $this->vincularContratos($root);
            $this->vincularInventarios();
            $this->vincularPedidos();
            $this->vincularPedidosMerenda($root);

            if ($this->execute) {
                $hierarchy->rebuildAll();
            }
        });

        $this->info($this->execute ? 'Backfill concluido.' : 'Dry-run concluido.');

        return self::SUCCESS;
    }

    private function schemaPronto(): bool
    {
        return Schema::hasColumn('setor', 'parent_id')
            && Schema::hasColumn('setor', 'path')
            && Schema::hasColumn('setor', 'is_default_root')
            && Schema::hasColumn('escolas', 'setor_id')
            && Schema::hasColumn('contratos', 'setor_id')
            && Schema::hasColumn('pedidos', 'setor_origem_id')
            && Schema::hasColumn('pedidos_merenda', 'setor_id')
            && Schema::hasColumn('inventarios', 'setor_id');
    }

    private function resolverRoot(): Setor
    {
        $rootName = (string) ($this->option('root-name') ?: config('app.default_setor_root_name', env('SETOR_DEFAULT_ROOT_NAME', 'Geral')));

        $root = Setor::query()
            ->where('is_default_root', true)
            ->orderBy('id')
            ->first()
            ?? Setor::query()->where('nome', $rootName)->orderBy('id')->first();

        if (! $root) {
            $this->line("Raiz '{$rootName}' sera criada.");

            if (! $this->execute) {
                return new Setor([
                    'nome' => $rootName,
                    'status' => 'Ativo',
                    'ativo' => true,
                    'is_default_root' => true,
                    'recebe_pedidos_iniciais' => true,
                ]);
            }

            return Setor::query()->create([
                'nome' => $rootName,
                'status' => 'Ativo',
                'ativo' => true,
                'is_default_root' => true,
                'recebe_pedidos_iniciais' => true,
                'encaminha_pedido_para_setor_ids' => [],
                'alterado_por' => 'Backfill',
            ]);
        }

        $this->line("Raiz configurada: {$root->nome} (#{$root->id}).");

        if ($this->execute && (! $root->is_default_root || ! $root->recebe_pedidos_iniciais)) {
            $root->forceFill([
                'parent_id' => null,
                'is_default_root' => true,
                'recebe_pedidos_iniciais' => true,
                'ativo' => true,
            ])->saveQuietly();
        }

        return $root;
    }

    private function vincularSetoresSoltos(Setor $root): void
    {
        if (! $root->exists) {
            $this->line('Setores sem pai seriam vinculados abaixo da raiz apos criacao.');

            return;
        }

        $query = Setor::query()
            ->whereNull('parent_id')
            ->whereKeyNot($root->id);

        $this->line('Setores sem pai para vincular abaixo da raiz: '.$query->count());

        if ($this->execute) {
            $query->update(['parent_id' => $root->id]);
        }
    }

    private function vincularEscolas(Setor $root): void
    {
        $escolas = Escola::query()
            ->whereNull('setor_id')
            ->orderBy('id')
            ->get();

        $this->line('Escolas sem setor: '.$escolas->count());

        if (! $this->execute || ! $root->exists) {
            return;
        }

        foreach ($escolas as $escola) {
            $setor = Setor::query()->firstOrCreate(
                [
                    'parent_id' => $root->id,
                    'nome' => $escola->nome,
                ],
                [
                    'status' => 'Ativo',
                    'ativo' => true,
                    'recebe_pedidos_iniciais' => false,
                    'encaminha_pedido_para_setor_ids' => [],
                    'alterado_por' => 'Backfill',
                ]
            );

            $escola->forceFill(['setor_id' => $setor->id])->saveQuietly();
        }
    }

    private function vincularUsuarios(): void
    {
        $usuarios = User::query()
            ->whereNull('setor_id')
            ->orderBy('id')
            ->get();

        $this->line('Usuarios sem setor principal: '.$usuarios->count());

        if (! $this->execute) {
            return;
        }

        foreach ($usuarios as $user) {
            $setorId = filled($user->id_escola)
                ? Escola::query()->whereKey($user->id_escola)->value('setor_id')
                : null;

            if (! $setorId) {
                $roleSetores = $user->roles()
                    ->whereNotNull('roles.setor_id')
                    ->pluck('roles.setor_id')
                    ->unique()
                    ->values();

                $setorId = $roleSetores->count() === 1 ? $roleSetores->first() : null;
            }

            if ($setorId) {
                $user->forceFill(['setor_id' => (int) $setorId])->saveQuietly();
            }
        }
    }

    private function vincularEmpresas(Setor $root): void
    {
        $query = EmpresaContratada::query()->whereNull('setor_id');

        $this->line('Empresas contratadas sem setor: '.$query->count());

        if ($this->execute && $root->exists) {
            $query->update(['setor_id' => $root->id]);
        }
    }

    private function vincularContratos(Setor $root): void
    {
        $contratos = Contrato::query()
            ->with('empresaContratada')
            ->whereNull('setor_id')
            ->orderBy('id')
            ->get();

        $this->line('Contratos sem setor: '.$contratos->count());

        if (! $this->execute) {
            return;
        }

        foreach ($contratos as $contrato) {
            $setorId = $contrato->empresaContratada?->setor_id ?: ($root->exists ? $root->id : null);

            if ($setorId) {
                $contrato->forceFill(['setor_id' => $setorId])->saveQuietly();
            }
        }
    }

    private function vincularInventarios(): void
    {
        $inventarios = Inventario::query()
            ->with('escola')
            ->whereNull('setor_id')
            ->orderBy('id')
            ->get();

        $this->line('Inventarios sem setor: '.$inventarios->count());

        if (! $this->execute) {
            return;
        }

        foreach ($inventarios as $inventario) {
            if ($inventario->escola?->setor_id) {
                $inventario->forceFill(['setor_id' => $inventario->escola->setor_id])->saveQuietly();
            }
        }
    }

    private function vincularPedidos(): void
    {
        $pedidos = Pedido::query()
            ->with(['escola', 'solicitante'])
            ->whereNull('setor_origem_id')
            ->orderBy('id')
            ->get();

        $this->line('Pedidos sem setor de origem: '.$pedidos->count());

        if ($this->execute) {
            foreach ($pedidos as $pedido) {
                $setorOrigemId = $pedido->escola?->setor_id ?: $pedido->solicitante?->setor_id;

                if ($setorOrigemId) {
                    $pedido->forceFill(['setor_origem_id' => $setorOrigemId])->saveQuietly();
                }
            }

            Pedido::query()
                ->whereNull('setor_id')
                ->whereNotNull('setor_origem_id')
                ->update(['setor_id' => DB::raw('setor_origem_id')]);
        }
    }

    private function vincularPedidosMerenda(Setor $root): void
    {
        $pedidos = PedidoMerenda::query()
            ->with('itens.contratoItem.contrato.empresaContratada')
            ->whereNull('setor_id')
            ->orderBy('id')
            ->get();

        $this->line('Pedidos de merenda sem setor: '.$pedidos->count());

        if (! $this->execute) {
            return;
        }

        foreach ($pedidos as $pedido) {
            $setores = $pedido->itens
                ->map(fn ($item): ?int => $item->contratoItem?->contrato?->setor_id ?: $item->contratoItem?->contrato?->empresaContratada?->setor_id)
                ->filter()
                ->unique()
                ->values();

            $setorId = $setores->count() === 1
                ? $setores->first()
                : ($root->exists ? $root->id : null);

            if ($setorId) {
                $pedido->forceFill(['setor_id' => $setorId])->saveQuietly();
            }
        }
    }
}
