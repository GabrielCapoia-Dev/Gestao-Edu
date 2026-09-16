<?php

namespace App\Services;

use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerfilEquipeGestoraService
{
    /** @return Collection<int, ServidorFuncaoAdministrativa> */
    public function vinculosDoPerfil(User $user): Collection
    {
        $servidor = Servidor::query()->where('user_id', $user->id)->first();

        if (! $servidor) {
            return new Collection();
        }

        return ServidorFuncaoAdministrativa::query()
            ->with(['funcaoAdministrativa', 'escola', 'vinculosTurmaAtivos.turma.serie'])
            ->where('servidor_id', $servidor->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', fn ($query) => $query->where(function ($query): void {
                $query->where('direcao_escolar', true)->orWhere('coordenacao_pedagogica', true);
            }))
            ->orderBy('id_escola')
            ->get();
    }

    public function atualizarPortaria(User $user, string $portaria): void
    {
        $vinculos = $this->vinculosDoPerfil($user);

        if ($vinculos->isEmpty()) {
            throw ValidationException::withMessages(['data.portaria' => 'Apenas Diretor e Coordenador podem editar a portaria.']);
        }

        $portaria = trim((string) preg_replace('/\s+/', ' ', $portaria));

        if ($portaria === '' || mb_strlen($portaria) > 255 || ! preg_match('/^[\pL\pN][\pL\pN .\/_-]*$/u', $portaria)) {
            throw ValidationException::withMessages(['data.portaria' => 'Informe uma portaria válida.']);
        }

        DB::transaction(function () use ($vinculos, $portaria): void {
            ServidorFuncaoAdministrativa::query()
                ->whereKey($vinculos->pluck('id')->all())
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->update(['portaria' => $portaria]);
        });
    }

    public function alternarTurma(User $user, int $turmaId, bool $vincular): void
    {
        DB::transaction(function () use ($user, $turmaId, $vincular): void {
            $turma = Turma::query()->lockForUpdate()->find($turmaId);
            $escolaIds = app(PessoaScopeService::class)->escolaIdsDosVinculos($user);

            if (! $turma || ! in_array((int) $turma->id_escola, $escolaIds, true)) {
                throw ValidationException::withMessages(['turma' => 'Esta turma não pertence a uma escola autorizada.']);
            }

            $servidorId = Servidor::query()->where('user_id', $user->id)->value('id');
            $coordenador = ServidorFuncaoAdministrativa::query()
                ->with('funcaoAdministrativa')
                ->where('servidor_id', $servidorId)
                ->where('id_escola', $turma->id_escola)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->whereHas('funcaoAdministrativa', fn ($query) => $query->where('coordenacao_pedagogica', true))
                ->lockForUpdate()
                ->first();

            if (! $coordenador) {
                throw ValidationException::withMessages(['turma' => 'O usuário não possui uma Coordenação ativa nesta escola.']);
            }

            $atual = ServidorFuncaoTurma::query()
                ->where('servidor_funcao_administrativa_id', $coordenador->id)
                ->where('turma_id', $turma->id)
                ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
                ->first();

            if (! $vincular) {
                $atual?->update([
                    'status' => ServidorFuncaoTurma::STATUS_INATIVO,
                    'principal' => false,
                    'data_fim' => now()->toDateString(),
                ]);

                return;
            }

            $outros = ServidorFuncaoTurma::query()
                ->where('turma_id', $turma->id)
                ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
                ->whereHas('servidorFuncaoAdministrativa', fn ($query) => $query
                    ->where('servidor_funcao_administrativa.id', '!=', $coordenador->id)
                    ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                    ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->where('coordenacao_pedagogica', true)))
                ->lockForUpdate()
                ->get();

            foreach ($outros as $outro) {
                $outro->update([
                    'status' => ServidorFuncaoTurma::STATUS_INATIVO,
                    'principal' => false,
                    'data_fim' => now()->toDateString(),
                ]);
            }

            ($atual ?? new ServidorFuncaoTurma([
                'servidor_funcao_administrativa_id' => $coordenador->id,
                'turma_id' => $turma->id,
            ]))->fill([
                'status' => ServidorFuncaoTurma::STATUS_ATIVO,
                'principal' => false,
                'data_inicio' => now()->toDateString(),
                'data_fim' => null,
            ])->save();
        });
    }
}
