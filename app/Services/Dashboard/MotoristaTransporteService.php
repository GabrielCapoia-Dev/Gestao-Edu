<?php

namespace App\Services\Dashboard;

use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\ServidorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MotoristaTransporteService
{
    public function __construct(private readonly ServidorService $servidores) {}

    public function query(User $ator, ?string $search = null): Builder
    {
        Gate::forUser($ator)->authorize('viewAnyMotoristas', ServidorFuncaoAdministrativa::class);

        $query = Servidor::query()
            ->whereHas('servidorFuncoes', fn (Builder $vinculos): Builder => $vinculos
                ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->motorista()))
            ->withExists([
                'servidorFuncoes as motorista_ativo' => fn (Builder $vinculos): Builder => $vinculos
                    ->ativos()
                    ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->motorista()),
            ]);

        $search = trim((string) $search);

        if ($search !== '') {
            $cpf = Pessoa::normalizarCpf($search);
            $query->where(function (Builder $filtro) use ($search, $cpf): void {
                $filtro
                    ->where('nome', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%");

                if ($cpf !== null) {
                    $filtro->orWhere('cpf', 'like', "%{$cpf}%");
                }
            });
        }

        return $query->orderBy('nome')->orderBy('id');
    }

    public function criar(User $ator, array $dados): Servidor
    {
        Gate::forUser($ator)->authorize('createMotorista', ServidorFuncaoAdministrativa::class);
        $dados = $this->validarDados($dados, permitirPessoaExistente: true);

        return DB::transaction(function () use ($ator, $dados): Servidor {
            $motorista = Servidor::query()
                ->where('cpf', $dados['cpf'])
                ->lockForUpdate()
                ->first();

            if ($motorista) {
                $this->validarReutilizacao($motorista, $dados);

                if ($motorista->status !== Pessoa::STATUS_ATIVO) {
                    throw ValidationException::withMessages([
                        'cpf' => 'A pessoa localizada por este CPF está inativa e não pode receber um novo vínculo de motorista.',
                    ]);
                }

                if (blank($motorista->telefone) && filled($dados['telefone'])) {
                    $motorista->forceFill(['telefone' => $dados['telefone']])->save();
                }

                if ($this->vinculoMotoristaQuery($motorista, somenteAtivo: true)->exists()) {
                    throw ValidationException::withMessages([
                        'cpf' => 'Esta pessoa já possui um vínculo ativo de motorista.',
                    ]);
                }
            } else {
                $motorista = Servidor::query()->create([
                    ...$dados,
                    'status' => Pessoa::STATUS_ATIVO,
                ]);
                $motorista = Servidor::query()->lockForUpdate()->findOrFail($motorista->getKey());
            }

            $funcao = FuncaoAdministrativa::motoristaPadrao();
            $vinculo = $this->servidores->vincularFuncao($motorista, $funcao, ['origem' => 'transporte']);
            Gate::forUser($ator)->authorize('activateMotorista', $vinculo);

            return $this->carregar($motorista);
        });
    }

    public function atualizar(User $ator, Servidor $motorista, array $dados): Servidor
    {
        $dados = $this->validarDados($dados, $motorista->getKey());

        return DB::transaction(function () use ($ator, $motorista, $dados): Servidor {
            $motorista = Servidor::query()->lockForUpdate()->findOrFail($motorista->getKey());
            $vinculo = $this->vinculoMotorista($motorista, somenteAtivo: false, bloquear: true);
            Gate::forUser($ator)->authorize('updateMotorista', $vinculo);

            $motorista->forceFill($dados)->save();

            return $this->carregar($motorista);
        });
    }

    public function ativar(User $ator, Servidor $motorista): Servidor
    {
        return DB::transaction(function () use ($ator, $motorista): Servidor {
            $motorista = Servidor::query()->lockForUpdate()->findOrFail($motorista->getKey());
            $vinculo = $this->vinculoMotorista($motorista, somenteAtivo: false, bloquear: true);
            Gate::forUser($ator)->authorize('activateMotorista', $vinculo);

            if ($motorista->status !== Pessoa::STATUS_ATIVO) {
                throw ValidationException::withMessages([
                    'motorista' => 'A pessoa está inativa e não pode ter o vínculo de motorista reativado.',
                ]);
            }

            $vinculo = $this->servidores->vincularFuncao(
                $motorista,
                $vinculo->funcaoAdministrativa,
                ['origem' => 'transporte'],
            );
            Gate::forUser($ator)->authorize('activateMotorista', $vinculo);

            return $this->carregar($motorista);
        });
    }

    public function desativar(User $ator, Servidor $motorista): Servidor
    {
        return DB::transaction(function () use ($ator, $motorista): Servidor {
            $motorista = Servidor::query()->lockForUpdate()->findOrFail($motorista->getKey());
            $vinculo = $this->vinculoMotorista($motorista, somenteAtivo: false, bloquear: true);
            Gate::forUser($ator)->authorize('deactivateMotorista', $vinculo);

            $this->servidores->removerFuncao($motorista, $vinculo->funcaoAdministrativa);

            return $this->carregar($motorista);
        });
    }

    /** @return array{nome: string, cpf: string, telefone: ?string} */
    private function validarDados(
        array $dados,
        ?int $ignorarPessoaId = null,
        bool $permitirPessoaExistente = false,
    ): array
    {
        $normalizados = [
            'nome' => Str::of((string) ($dados['nome'] ?? ''))->squish()->toString(),
            'cpf' => Pessoa::normalizarCpf($dados['cpf'] ?? null),
            'telefone' => filled($dados['telefone'] ?? null)
                ? Str::of((string) $dados['telefone'])->squish()->toString()
                : null,
        ];

        $cpfUnico = Rule::unique('servidores', 'cpf');
        if ($ignorarPessoaId !== null) {
            $cpfUnico->ignore($ignorarPessoaId);
        }

        $regrasCpf = ['required', 'digits:11'];

        if (! $permitirPessoaExistente) {
            $regrasCpf[] = $cpfUnico;
        }

        $validator = validator($normalizados, [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => $regrasCpf,
            'telefone' => ['nullable', 'string', 'max:255'],
        ], [
            'cpf.digits' => 'O CPF deve conter exatamente 11 dígitos.',
            'cpf.unique' => 'Este CPF já está cadastrado para outra pessoa.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $normalizados;
    }

    private function validarReutilizacao(Servidor $motorista, array $dados): void
    {
        $nomeAtual = Str::of((string) $motorista->nome)->squish()->lower()->toString();
        $nomeInformado = Str::of((string) $dados['nome'])->squish()->lower()->toString();

        if ($nomeAtual !== $nomeInformado) {
            throw ValidationException::withMessages([
                'nome' => 'O CPF informado já pertence a uma pessoa com nome diferente. Revise os dados antes de continuar.',
            ]);
        }

        if (
            filled($motorista->telefone)
            && filled($dados['telefone'])
            && $this->normalizarTelefone($motorista->telefone) !== $this->normalizarTelefone($dados['telefone'])
        ) {
            throw ValidationException::withMessages([
                'telefone' => 'O CPF informado já pertence a uma pessoa com telefone diferente. Revise os dados antes de continuar.',
            ]);
        }
    }

    private function normalizarTelefone(?string $telefone): string
    {
        return preg_replace('/\D+/', '', (string) $telefone) ?? '';
    }

    private function vinculoMotorista(
        Servidor $motorista,
        bool $somenteAtivo,
        bool $bloquear,
    ): ServidorFuncaoAdministrativa {
        $query = $this->vinculoMotoristaQuery($motorista, $somenteAtivo)
            ->with('funcaoAdministrativa')
            ->latest('id');

        if ($bloquear) {
            $query->lockForUpdate();
        }

        $vinculo = $query->first();

        if (! $vinculo) {
            throw ValidationException::withMessages([
                'motorista' => 'A pessoa informada não possui vínculo de motorista.',
            ]);
        }

        return $vinculo;
    }

    private function vinculoMotoristaQuery(Servidor $motorista, bool $somenteAtivo): Builder
    {
        $query = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $motorista->getKey())
            ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->motorista());

        if ($somenteAtivo) {
            $query->ativos();
        }

        return $query;
    }

    private function carregar(Servidor $motorista): Servidor
    {
        return $motorista->fresh([
            'servidorFuncoes' => fn (Builder $vinculos): Builder => $vinculos
                ->whereHas('funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->motorista())
                ->latest('id'),
            'servidorFuncoes.funcaoAdministrativa',
        ]);
    }
}
