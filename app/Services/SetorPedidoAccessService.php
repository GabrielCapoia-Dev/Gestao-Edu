<?php

namespace App\Services;

use App\Models\Enums\SetorAccessCapability;
use App\Models\Setor;
use App\Models\SetorAcesso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SetorPedidoAccessService
{
    public function __construct(
        private readonly UserSetorAccessService $userSetorAccess,
        private readonly SetorHierarchyService $hierarchy,
    ) {
    }

    public function can(?User $user, SetorAccessCapability $capability, ?int $setorAlvoId): bool
    {
        if (! $user || blank($setorAlvoId)) {
            return false;
        }

        $setorOrigemId = $this->userSetorAccess->primarySetorId($user);

        if ($setorOrigemId && (int) $setorOrigemId === (int) $setorAlvoId) {
            return $capability->permiteProprioSetor();
        }

        if ($this->userSetorAccess->hasGlobalAccess($user)) {
            return true;
        }

        if (! $setorOrigemId) {
            return false;
        }

        return SetorAcesso::query()
            ->where('setor_origem_id', $setorOrigemId)
            ->where('setor_alvo_id', $setorAlvoId)
            ->where($capability->value, true)
            ->exists();
    }

    public function allowedSetorIds(?User $user, SetorAccessCapability $capability): array
    {
        if (! $user) {
            return [];
        }

        if ($this->userSetorAccess->hasGlobalAccess($user)) {
            $ids = Setor::query()
                ->where('ativo', true)
                ->orderBy('path')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            $setorOrigemId = $this->userSetorAccess->primarySetorId($user);

            if ($capability === SetorAccessCapability::ENCAMINHAR && $setorOrigemId) {
                $ids = $ids->reject(fn (int $id): bool => $id === (int) $setorOrigemId);
            }

            return $ids->values()->all();
        }

        $setorOrigemId = $this->userSetorAccess->primarySetorId($user);

        if (! $setorOrigemId) {
            return [];
        }

        $ids = SetorAcesso::query()
            ->where('setor_origem_id', $setorOrigemId)
            ->where($capability->value, true)
            ->whereHas('setorAlvo', fn (Builder $query): Builder => $query->where('ativo', true))
            ->pluck('setor_alvo_id')
            ->map(fn ($id): int => (int) $id);

        if ($capability->permiteProprioSetor()) {
            $ids->prepend((int) $setorOrigemId);
        }

        return $ids->unique()->values()->all();
    }

    public function optionsForCapability(?User $user, SetorAccessCapability $capability): array
    {
        return $this->hierarchy->labelsForOptions($this->allowedSetorIds($user, $capability));
    }

    public function assertCan(?User $user, SetorAccessCapability $capability, ?int $setorAlvoId): void
    {
        if ($this->can($user, $capability, $setorAlvoId)) {
            return;
        }

        throw ValidationException::withMessages([
            'setor_id' => 'Seu setor não possui autorização para executar esta ação no setor informado.',
        ]);
    }

    public function configuredSetorIds(Setor $setor, SetorAccessCapability $capability): array
    {
        return SetorAcesso::query()
            ->where('setor_origem_id', $setor->id)
            ->where($capability->value, true)
            ->orderBy('setor_alvo_id')
            ->pluck('setor_alvo_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function syncCapability(Setor $setor, SetorAccessCapability $capability, array $setorAlvoIds): void
    {
        $ids = collect($setorAlvoIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === (int) $setor->id)
            ->unique()
            ->values();

        $validIds = Setor::query()
            ->where('ativo', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($ids->diff($validIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                $capability->value => 'Um ou mais setores selecionados sao invalidos ou estão inativos.',
            ]);
        }

        SetorAcesso::query()
            ->where('setor_origem_id', $setor->id)
            ->where($capability->value, true)
            ->whereNotIn('setor_alvo_id', $validIds)
            ->get()
            ->each(function (SetorAcesso $acesso) use ($capability): void {
                $acesso->forceFill([$capability->value => false]);

                if (! $acesso->pode_listar && ! $acesso->pode_editar && ! $acesso->pode_cancelar && ! $acesso->pode_encaminhar) {
                    $acesso->delete();

                    return;
                }

                $acesso->save();
            });

        foreach ($validIds as $setorAlvoId) {
            SetorAcesso::query()->updateOrCreate(
                [
                    'setor_origem_id' => $setor->id,
                    'setor_alvo_id' => $setorAlvoId,
                ],
                [$capability->value => true],
            );
        }

        if ($capability === SetorAccessCapability::ENCAMINHAR) {
            $setor->forceFill([
                'encaminha_pedido_para_setor_ids' => $validIds->all(),
            ])->saveQuietly();
        }
    }
}
