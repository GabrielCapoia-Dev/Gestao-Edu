<?php

namespace App\Livewire\Pessoas;

use App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\DominioEmailService;
use App\Services\PessoaEdicaoEscopadaService;
use App\Services\PessoaProfessorFormService;
use App\Services\PessoaScopeService;
use App\Services\ServidorService;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class PessoaForm extends Component
{
    #[Locked]
    public ?int $pessoaId = null;

    #[Locked]
    public bool $gerenciaEstrutura = false;

    #[Locked]
    public bool $podeGerenciarEquipeGestora = false;

    #[Locked]
    public bool $podeEditarDados = false;

    #[Locked]
    public bool $podeEditarTurmas = false;

    #[Locked]
    public bool $podeSalvar = false;

    /** @var list<int> */
    #[Locked]
    public array $matriculaIdsPermitidos = [];

    /** @var list<int> */
    #[Locked]
    public array $lotacaoIdsPermitidos = [];

    /** @var list<string> */
    #[Locked]
    public array $cargosGestoresAnteriores = [];

    /** @var list<string> */
    #[Locked]
    public array $matriculaKeysRemovidas = [];

    /** @var list<int> */
    #[Locked]
    public array $matriculaIdsRemovidos = [];

    public string $nome = '';

    public ?string $cpf = null;

    public ?string $email = null;

    public ?string $telefone = null;

    public string $status = Pessoa::STATUS_ATIVO;

    public ?string $observacoes = null;

    public string $cargo = ServidorResource::CARGO_PROFESSOR;

    /** @var array<string, array<string, mixed>> */
    public array $matriculas = [];

    public ?string $matriculaAtiva = null;

    public ?string $matriculaMotorista = null;

    public ?string $matriculaOperacional = null;

    /** @var array<string, string|null> */
    public array $lotacoesAtivas = [];

    public ?int $idEscolaGestora = null;

    public ?int $setorManutencaoId = null;

    public ?int $setorObrasId = null;

    /** @var list<string> */
    public array $cargosGestores = [];

    public ?string $portaria = null;

    /** @var list<int|string> */
    public array $turmaIds = [];

    public function mount(?int $pessoaId = null): void
    {
        $this->pessoaId = $pessoaId;

        if ($this->modoCriacao()) {
            Gate::authorize('create', Servidor::class);
            $this->atualizarCapacidades(null);
            $this->inicializarCriacao();

            return;
        }

        $pessoa = $this->pessoaAutorizada();
        $this->atualizarCapacidades($pessoa);
        $this->carregarEdicao($pessoa);
    }

    public function render(): View
    {
        $escolasOptions = $this->escolasOptionsSeguras();
        $turmasOptions = [];
        $componentesOptions = [];
        $matriculaLabels = [];
        $lotacaoLabels = [];
        $turnosOptions = PessoaMatricula::turnosOptions();
        $turnosOptionsPorMatricula = [];

        foreach ($this->matriculas as $matriculaKey => $matricula) {
            if (! is_array($matricula)) {
                continue;
            }

            $matriculaLabels[$matriculaKey] = $this->matriculaLabel($matricula, $turnosOptions);
            $turnosOptionsPorMatricula[$matriculaKey] = PessoaMatricula::turnosDisponiveisParaItem(
                PessoaMatricula::turnosDosIrmaos($this->matriculas, $matriculaKey),
                filled($matricula['turno'] ?? null) ? (string) $matricula['turno'] : null,
            );

            $lotacoes = is_array($matricula['escolas'] ?? null) ? $matricula['escolas'] : [];
            foreach ($lotacoes as $lotacaoKey => $lotacao) {
                if (! is_array($lotacao)) {
                    continue;
                }

                $escolaId = filled($lotacao['id_escola'] ?? null) ? (int) $lotacao['id_escola'] : null;
                $lotacaoLabels[$matriculaKey][$lotacaoKey] = $escolaId && isset($escolasOptions[$escolaId])
                    ? $escolasOptions[$escolaId]
                    : ($escolaId ? 'Escola indisponível' : 'Nova escola');
                $vinculos = is_array($lotacao['vinculos_turma_componente'] ?? null)
                    ? $lotacao['vinculos_turma_componente']
                    : [];
                $selecionadas = collect($vinculos)
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->pluck('turma_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $opcoesTurma = $this->turmasOptionsSeguras(
                    $escolaId,
                    (string) ($matricula['turno'] ?? ''),
                    $selecionadas,
                    $escolasOptions,
                );
                $turmasOptions[$matriculaKey][$lotacaoKey] = $opcoesTurma;

                foreach ($vinculos as $vinculoKey => $vinculo) {
                    if (! is_array($vinculo)) {
                        continue;
                    }

                    $turmaId = filled($vinculo['turma_id'] ?? null) ? (int) $vinculo['turma_id'] : null;
                    $componentesOptions[$matriculaKey][$lotacaoKey][$vinculoKey] = $this->componentesOptionsSeguras(
                        $turmaId,
                        array_keys($opcoesTurma),
                    );
                }
            }
        }

        $turmasGestaoOptions = $this->turmasGestaoOptions($escolasOptions);
        $turmasGestaoSelecionadas = collect($this->turmaIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();
        $idsTurmasGestao = collect(array_keys($turmasGestaoOptions))->map(fn (mixed $id): int => (int) $id);

        return view('livewire.pessoas.pessoa-form', [
            'escolasOptions' => $escolasOptions,
            'setoresManutencaoOptions' => $this->setoresManutencaoOptions(),
            'setoresObrasOptions' => $this->setoresManutencaoOptions(),
            'turmasOptions' => $turmasOptions,
            'componentesOptions' => $componentesOptions,
            'turmasGestaoOptions' => $turmasGestaoOptions,
            'statusOptions' => Pessoa::statusOptions(),
            'turnosOptions' => $turnosOptions,
            'turnosOptionsPorMatricula' => $turnosOptionsPorMatricula,
            'matriculaLabels' => $matriculaLabels,
            'lotacaoLabels' => $lotacaoLabels,
            'temDiretor' => in_array(ServidorEquipeGestoraForm::CARGO_DIRETOR, $this->cargosGestores, true),
            'temCoordenador' => in_array(ServidorEquipeGestoraForm::CARGO_COORDENADOR, $this->cargosGestores, true),
            'todasTurmasGestaoSelecionadas' => $idsTurmasGestao->isNotEmpty()
                && $idsTurmasGestao->diff($turmasGestaoSelecionadas)->isEmpty(),
            'podeSalvar' => $this->podeSalvar,
            'modoCriacao' => $this->modoCriacao(),
        ]);
    }

    public function adicionarMatricula(): void
    {
        $this->autorizarEstruturaProfessor();
        $this->resetErrorBag('matriculas');

        if (! PessoaMatricula::podeAdicionarMatricula($this->matriculas)) {
            $this->addError('matriculas', 'Remova ou ajuste uma matrícula antes de adicionar outra.');

            return;
        }

        $turnosDisponiveis = PessoaMatricula::turnosDisponiveisParaItem(
            PessoaMatricula::turnosDosIrmaos($this->matriculas),
        );
        $key = $this->novaChave('m');
        $this->matriculas[$key] = [
            'id' => null,
            'matricula' => '',
            'turno' => count($turnosDisponiveis) === 1 ? (string) array_key_first($turnosDisponiveis) : '',
            'escolas' => [],
        ];
        $this->matriculaAtiva = $key;
        $this->lotacoesAtivas[$key] = null;
    }

    public function removerMatricula(string $matriculaKey): void
    {
        $this->autorizarEstruturaProfessor();
        $this->resetErrorBag('matriculas');

        if (! array_key_exists($matriculaKey, $this->matriculas)) {
            return;
        }

        if (count($this->matriculas) <= 1) {
            $this->addError('matriculas', 'A pessoa deve permanecer com ao menos uma matrícula.');

            return;
        }

        $matriculaId = filled($this->matriculas[$matriculaKey]['id'] ?? null)
            ? (int) $this->matriculas[$matriculaKey]['id']
            : null;
        $this->matriculaKeysRemovidas = collect($this->matriculaKeysRemovidas)
            ->push($matriculaKey)
            ->unique()
            ->values()
            ->all();
        if ($matriculaId !== null) {
            $this->matriculaIdsRemovidos = collect($this->matriculaIdsRemovidos)
                ->push($matriculaId)
                ->unique()
                ->values()
                ->all();
        }

        unset($this->matriculas[$matriculaKey], $this->lotacoesAtivas[$matriculaKey]);
        $this->completarTurnoLegadoAposRemocao();
        $this->matriculaAtiva = array_key_first($this->matriculas);
    }

    public function selecionarMatricula(string $matriculaKey): void
    {
        if (array_key_exists($matriculaKey, $this->matriculas)) {
            $this->matriculaAtiva = $matriculaKey;
        }
    }

    public function adicionarLotacao(string $matriculaKey): void
    {
        $this->autorizarEstruturaProfessor();

        if (! isset($this->matriculas[$matriculaKey]) || ! is_array($this->matriculas[$matriculaKey])) {
            return;
        }

        $key = $this->novaChave('l');
        $this->matriculas[$matriculaKey]['escolas'][$key] = [
            'id' => null,
            'id_escola' => null,
            'vinculos_turma_componente' => [],
        ];
        $this->lotacoesAtivas[$matriculaKey] = $key;
    }

    public function removerLotacao(string $matriculaKey, string $lotacaoKey): void
    {
        $this->autorizarEstruturaProfessor();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            return;
        }

        unset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]);
        $this->lotacoesAtivas[$matriculaKey] = array_key_first($this->matriculas[$matriculaKey]['escolas'] ?? []);
    }

    public function selecionarLotacao(string $matriculaKey, string $lotacaoKey): void
    {
        if (isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            $this->lotacoesAtivas[$matriculaKey] = $lotacaoKey;
        }
    }

    public function adicionarVinculo(string $matriculaKey, string $lotacaoKey): void
    {
        $this->autorizarEdicaoTurmas();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            return;
        }

        if (! filled($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['id_escola'] ?? null)) {
            $this->addError(
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.id_escola",
                'Selecione a escola antes de adicionar uma turma.',
            );

            return;
        }

        $key = $this->novaChave('v');
        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$key] = [
            'turma_id' => null,
            'componente_curricular_id' => null,
        ];
    }

    public function removerVinculo(string $matriculaKey, string $lotacaoKey, string $vinculoKey): void
    {
        $this->autorizarEdicaoTurmas();
        unset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey]);
    }

    public function escolaAlterada(string $matriculaKey, string $lotacaoKey, mixed $escolaId): void
    {
        $this->autorizarEstruturaProfessor();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey])) {
            return;
        }

        $novoId = filled($escolaId) ? (int) $escolaId : null;
        if ($novoId !== null && ! array_key_exists($novoId, $this->escolasOptionsSeguras())) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.id_escola" => 'A escola selecionada não pertence ao seu escopo.',
            ]);
        }

        $duplicada = collect($this->matriculas[$matriculaKey]['escolas'] ?? [])
            ->except($lotacaoKey)
            ->contains(fn (mixed $lotacao): bool => is_array($lotacao)
                && filled($lotacao['id_escola'] ?? null)
                && (int) $lotacao['id_escola'] === $novoId);
        if ($novoId !== null && $duplicada) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.id_escola" => 'Esta escola já está vinculada à matrícula.',
            ]);
        }

        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['id_escola'] = $novoId;
        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'] = [];
    }

    public function turnoAlterado(string $matriculaKey, mixed $turno): void
    {
        $this->autorizarEstruturaProfessor();

        if (! isset($this->matriculas[$matriculaKey])) {
            return;
        }

        $turno = (string) $turno;
        $permitidos = PessoaMatricula::turnosDisponiveisParaItem(
            PessoaMatricula::turnosDosIrmaos($this->matriculas, $matriculaKey),
            (string) ($this->matriculas[$matriculaKey]['turno'] ?? ''),
        );
        if ($turno !== '' && ! array_key_exists($turno, $permitidos)) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.turno" => 'O turno é incompatível com as demais matrículas.',
            ]);
        }

        $anterior = (string) ($this->matriculas[$matriculaKey]['turno'] ?? '');
        $this->matriculas[$matriculaKey]['turno'] = $turno;
        if ($anterior === $turno) {
            return;
        }

        foreach (array_keys($this->matriculas[$matriculaKey]['escolas'] ?? []) as $lotacaoKey) {
            $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'] = [];
        }
    }

    public function turmaAlterada(
        string $matriculaKey,
        string $lotacaoKey,
        string $vinculoKey,
        mixed $turmaId,
    ): void {
        $this->autorizarEdicaoTurmas();

        if (! isset($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey])) {
            return;
        }

        $turmaId = filled($turmaId) ? (int) $turmaId : null;
        $escolaId = filled($this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['id_escola'] ?? null)
            ? (int) $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['id_escola']
            : null;
        $permitidas = $this->turmasOptionsSeguras(
            $escolaId,
            (string) ($this->matriculas[$matriculaKey]['turno'] ?? ''),
            [],
            $this->escolasOptionsSeguras(),
        );

        if ($turmaId !== null && ! array_key_exists($turmaId, $permitidas)) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente.{$vinculoKey}.turma_id" => 'A turma selecionada não pertence à escola e ao turno desta matrícula.',
            ]);
        }

        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey]['turma_id'] = $turmaId;
        $this->matriculas[$matriculaKey]['escolas'][$lotacaoKey]['vinculos_turma_componente'][$vinculoKey]['componente_curricular_id'] = null;
    }

    public function escolaGestoraAlterada(mixed $escolaId): void
    {
        $this->autorizarEquipeGestora();
        $novoId = filled($escolaId) ? (int) $escolaId : null;

        if ($novoId !== null && ! array_key_exists($novoId, $this->escolasOptionsSeguras())) {
            throw ValidationException::withMessages([
                'idEscolaGestora' => 'A escola selecionada não pertence ao seu escopo.',
            ]);
        }

        if ($this->idEscolaGestora === $novoId) {
            return;
        }

        $this->idEscolaGestora = $novoId;
        $this->turmaIds = [];
        $this->portaria = null;
    }

    public function cargoAlterado(mixed $cargo): void
    {
        $this->aplicarCargo((string) $cargo);
    }

    public function updatedCargo(mixed $cargo): void
    {
        $this->aplicarCargo((string) $cargo);
    }

    public function updatedCargosGestores(mixed $value = null, mixed $key = null): void
    {
        $this->autorizarEquipeGestora();
        $validos = [
            ServidorEquipeGestoraForm::CARGO_DIRETOR,
            ServidorEquipeGestoraForm::CARGO_COORDENADOR,
            ServidorEquipeGestoraForm::CARGO_SECRETARIO,
        ];
        $atuais = collect($this->cargosGestores)
            ->filter(fn (mixed $cargo): bool => is_string($cargo) && in_array($cargo, $validos, true))
            ->unique()
            ->values();
        $adicionados = $atuais->diff($this->cargosGestoresAnteriores);

        if ($atuais->contains(ServidorEquipeGestoraForm::CARGO_SECRETARIO) && $atuais->count() > 1) {
            $atuais = $adicionados->contains(ServidorEquipeGestoraForm::CARGO_SECRETARIO)
                ? collect([ServidorEquipeGestoraForm::CARGO_SECRETARIO])
                : $atuais->reject(fn (string $cargo): bool => $cargo === ServidorEquipeGestoraForm::CARGO_SECRETARIO)->values();
        }

        $this->cargosGestores = $atuais->all();
        $this->cargosGestoresAnteriores = $this->cargosGestores;

        if (! $atuais->intersect([
            ServidorEquipeGestoraForm::CARGO_DIRETOR,
            ServidorEquipeGestoraForm::CARGO_COORDENADOR,
        ])->isNotEmpty()) {
            $this->portaria = null;
        }

        if (! $atuais->contains(ServidorEquipeGestoraForm::CARGO_COORDENADOR)) {
            $this->turmaIds = [];
        }
    }

    public function cargosGestoresAlterados(): void
    {
        $this->updatedCargosGestores();
    }

    public function alternarTodasTurmasGestao(): void
    {
        $this->autorizarEquipeGestora();
        $opcoes = $this->turmasGestaoOptions($this->escolasOptionsSeguras());
        $ids = collect(array_keys($opcoes))->map(fn (mixed $id): int => (int) $id)->values();
        $selecionadas = collect($this->turmaIds)->map(fn (mixed $id): int => (int) $id)->unique();

        $this->turmaIds = $ids->isNotEmpty() && $ids->diff($selecionadas)->isEmpty()
            ? []
            : $ids->all();
    }

    public function selecionarTodasTurmasGestao(): void
    {
        $this->autorizarEquipeGestora();
        $this->turmaIds = array_map('intval', array_keys(
            $this->turmasGestaoOptions($this->escolasOptionsSeguras()),
        ));
    }

    public function desmarcarTodasTurmasGestao(): void
    {
        $this->autorizarEquipeGestora();
        $this->turmaIds = [];
    }

    public function salvar(): void
    {
        $this->resetErrorBag();
        $this->aplicarRemocoesPendentesDeMatriculas();
        $user = $this->usuarioAutenticado();
        $pessoa = null;

        if ($this->modoCriacao()) {
            Gate::forUser($user)->authorize('create', Servidor::class);
        } else {
            $pessoa = $this->pessoaAutorizada();
        }

        $this->atualizarCapacidades($pessoa);
        if (! $this->podeSalvar) {
            abort(403);
        }

        try {
            if ($this->modoCriacao() || $this->gerenciaEstrutura) {
                $this->validarPermissaoDoCargo();
                $this->validarDadosBasicos(true);
                $this->validarEstrutura();
                [$dados, $vinculos] = ServidorResource::prepararDadosPersistencia(
                    $this->payloadEstrutural(),
                    $pessoa,
                );

                if ($pessoa) {
                    app(ServidorService::class)->atualizarServidorComFuncoes($pessoa, $dados, $vinculos);
                } else {
                    app(ServidorService::class)->criarServidorComFuncoes($dados, $vinculos);
                }
            } else {
                $this->validarDadosBasicos($this->podeEditarDados);
                $this->validarEstadoSomenteLeitura($pessoa);
                $this->validarAtribuicoesEscopadas($pessoa, $this->podeEditarTurmas);

                $payload = [];
                if ($this->podeEditarDados) {
                    $payload = [
                        'cpf' => $this->cpf,
                        'email' => $this->email,
                        'telefone' => $this->telefone,
                    ];
                }
                if ($this->podeEditarTurmas) {
                    $payload['matriculas_professor'] = $this->payloadMatriculas();
                }

                app(PessoaEdicaoEscopadaService::class)->atualizar($pessoa, $user, $payload);
            }

            Notification::make()
                ->title($this->modoCriacao() ? 'Pessoa criada' : 'Pessoa atualizada')
                ->success()
                ->send();

            $this->dispatch('pessoa-form-salvo');
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Não foi possível salvar')
                ->body(collect($exception->errors())->flatten()->take(5)->implode(' '))
                ->danger()
                ->persistent()
                ->send();

            throw $exception;
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('formulario', 'Não foi possível salvar a pessoa. Revise os dados e tente novamente.');

            Notification::make()
                ->title('Erro ao salvar pessoa')
                ->body('A alteração não foi aplicada. Tente novamente.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function cancelar(): void
    {
        $this->dispatch('pessoa-form-cancelado');
    }

    private function inicializarCriacao(): void
    {
        $this->nome = '';
        $this->cpf = null;
        $this->email = null;
        $this->telefone = null;
        $this->status = Pessoa::STATUS_ATIVO;
        $this->observacoes = null;
        $this->cargo = ServidorResource::CARGO_PROFESSOR;
        $this->matriculaMotorista = null;
        $this->matriculaOperacional = null;
        $key = $this->novaChave('m');
        $this->matriculas = [
            $key => [
                'id' => null,
                'matricula' => '',
                'turno' => '',
                'escolas' => [],
            ],
        ];
        $this->matriculaAtiva = $key;
        $this->lotacoesAtivas = [$key => null];
    }

    private function carregarEdicao(Servidor $pessoa): void
    {
        $user = $this->usuarioAutenticado();
        $dados = $this->gerenciaEstrutura
            ? app(PessoaProfessorFormService::class)->dadosParaFormulario($pessoa)
            : app(PessoaProfessorFormService::class)->dadosEscopadosParaFormulario($pessoa, $user);

        $this->nome = (string) ($dados['nome'] ?? $pessoa->nome);
        $this->cpf = $dados['cpf'] ?? Pessoa::formatarCpf($pessoa->cpf);
        $this->email = $dados['email'] ?? $pessoa->email;
        $this->telefone = $dados['telefone'] ?? $pessoa->telefone;
        $this->status = (string) ($dados['status'] ?? $pessoa->status ?? Pessoa::STATUS_ATIVO);
        $this->observacoes = $this->gerenciaEstrutura ? ($dados['observacoes'] ?? $pessoa->observacoes) : null;
        $this->cargo = (string) ($dados['cargo'] ?? ServidorResource::CARGO_PROFESSOR);
        $this->matriculaMotorista = filled($dados['matricula_motorista'] ?? null)
            ? (string) $dados['matricula_motorista']
            : null;
        $this->matriculaOperacional = filled($dados['matricula_operacional'] ?? null)
            ? (string) $dados['matricula_operacional']
            : null;

        $matriculas = is_array($dados['matriculas_professor'] ?? null)
            ? $dados['matriculas_professor']
            : [];
        if (! $this->gerenciaEstrutura) {
            $matriculas = array_values(array_filter(
                $matriculas,
                fn (mixed $item): bool => is_array($item) && ! empty($item['escolas']),
            ));
        }

        $this->matriculas = $this->normalizarEstadoMatriculas($matriculas);
        $this->matriculaAtiva = array_key_first($this->matriculas);
        foreach ($this->matriculas as $matriculaKey => $matricula) {
            $this->lotacoesAtivas[$matriculaKey] = array_key_first($matricula['escolas'] ?? []);
        }
        $this->capturarIdsPermitidos();

        if ($this->gerenciaEstrutura) {
            $this->idEscolaGestora = filled($dados['id_escola'] ?? null) ? (int) $dados['id_escola'] : null;
            $setorOperacionalId = filled($dados['setor_id'] ?? null) ? (int) $dados['setor_id'] : null;
            $this->setorManutencaoId = $this->cargo === ServidorResource::CARGO_MANUTENCAO
                ? $setorOperacionalId
                : null;
            $this->setorObrasId = $this->cargo === ServidorResource::CARGO_OBRAS
                ? $setorOperacionalId
                : null;
            $this->cargosGestores = array_values($dados['cargos_gestores'] ?? []);
            $this->cargosGestoresAnteriores = $this->cargosGestores;
            $this->portaria = $dados['portaria'] ?? null;
            $this->turmaIds = collect($dados['turma_ids'] ?? [])
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }
    }

    private function pessoaAutorizada(): Servidor
    {
        abort_if($this->pessoaId === null, 404);
        $pessoa = Servidor::query()->findOrFail($this->pessoaId);
        Gate::authorize('update', $pessoa);

        return $pessoa;
    }

    private function atualizarCapacidades(?Servidor $pessoa): void
    {
        $user = $this->usuarioAutenticado();

        if ($pessoa === null) {
            Gate::forUser($user)->authorize('create', Servidor::class);
            $this->gerenciaEstrutura = Gate::forUser($user)->allows('manageStructure', Servidor::class);
            $this->podeGerenciarEquipeGestora = $this->gerenciaEstrutura;
            $this->podeEditarDados = true;
            $this->podeEditarTurmas = true;
            $this->podeSalvar = true;

            return;
        }

        Gate::forUser($user)->authorize('update', $pessoa);
        $this->gerenciaEstrutura = Gate::forUser($user)->allows('manageStructure', $pessoa);
        $this->podeGerenciarEquipeGestora = $this->gerenciaEstrutura;
        $this->podeEditarDados = $this->gerenciaEstrutura
            || Gate::forUser($user)->allows('editBasicData', $pessoa);
        $this->podeEditarTurmas = $this->gerenciaEstrutura
            || Gate::forUser($user)->allows('editTeachingAssignments', $pessoa);
        $this->podeSalvar = $this->gerenciaEstrutura || $this->podeEditarDados || $this->podeEditarTurmas;
    }

    private function autorizarEstruturaProfessor(): void
    {
        if ($this->modoCriacao()) {
            Gate::authorize('create', Servidor::class);

            return;
        }

        Gate::authorize('manageStructure', $this->pessoaAutorizada());
    }

    private function autorizarEquipeGestora(): void
    {
        if ($this->modoCriacao()) {
            Gate::authorize('create', Servidor::class);
            Gate::authorize('manageStructure', Servidor::class);

            return;
        }

        Gate::authorize('manageStructure', $this->pessoaAutorizada());
    }

    private function autorizarEdicaoTurmas(): void
    {
        if ($this->modoCriacao()) {
            Gate::authorize('create', Servidor::class);

            return;
        }

        $pessoa = $this->pessoaAutorizada();
        if (! Gate::allows('manageStructure', $pessoa) && ! Gate::allows('editTeachingAssignments', $pessoa)) {
            abort(403);
        }
    }

    private function aplicarCargo(string $cargo): void
    {
        if (! in_array($cargo, [
            ServidorResource::CARGO_PROFESSOR,
            ServidorResource::CARGO_EQUIPE_GESTORA,
            ServidorResource::CARGO_MANUTENCAO,
            ServidorResource::CARGO_OBRAS,
            ServidorResource::CARGO_MOTORISTA,
            ServidorResource::CARGO_TRANSPORTE,
            ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
        ], true)) {
            $this->cargo = ServidorResource::CARGO_PROFESSOR;
            $this->addError('cargo', 'O cargo informado é inválido.');

            return;
        }

        if (in_array($cargo, [
            ServidorResource::CARGO_EQUIPE_GESTORA,
            ServidorResource::CARGO_MANUTENCAO,
            ServidorResource::CARGO_OBRAS,
            ServidorResource::CARGO_MOTORISTA,
            ServidorResource::CARGO_TRANSPORTE,
            ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
        ], true)) {
            $this->autorizarEquipeGestora();
        } else {
            $this->autorizarEstruturaProfessor();
        }

        $this->cargo = $cargo;
        $this->resetErrorBag('cargo');
    }

    private function validarPermissaoDoCargo(): void
    {
        if (! in_array($this->cargo, [
            ServidorResource::CARGO_EQUIPE_GESTORA,
            ServidorResource::CARGO_MANUTENCAO,
            ServidorResource::CARGO_OBRAS,
            ServidorResource::CARGO_MOTORISTA,
            ServidorResource::CARGO_TRANSPORTE,
            ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
        ], true)) {
            return;
        }

        $this->autorizarEquipeGestora();
    }

    private function validarDadosBasicos(bool $editavel): void
    {
        if (! $editavel) {
            return;
        }

        $rules = [
            'cpf' => ['nullable', 'string', 'max:14'],
            'email' => $this->cargo === ServidorResource::CARGO_MOTORISTA
                ? ['nullable', 'email', 'max:255']
                : ['required', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:255'],
        ];
        if ($this->modoCriacao() || $this->gerenciaEstrutura) {
            $rules += [
                'nome' => ['required', 'string', 'max:255'],
                'status' => ['required', Rule::in(array_keys(Pessoa::statusOptions()))],
                'observacoes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        $this->validate($rules);

        if (filled($this->email) && ! app(DominioEmailService::class)->isEmailAutorizado($this->email)) {
            throw ValidationException::withMessages([
                'email' => 'O domínio deste e-mail não está autorizado para novos cadastros. Verifique os domínios permitidos.',
            ]);
        }

        $cpf = Pessoa::normalizarCpf($this->cpf);
        if ($cpf !== null && Servidor::query()
            ->where('cpf', $cpf)
            ->when($this->pessoaId !== null, fn ($query) => $query->whereKeyNot($this->pessoaId))
            ->exists()) {
            throw ValidationException::withMessages([
                'cpf' => 'Este CPF já está vinculado a outra pessoa.',
            ]);
        }
    }

    private function validarEstrutura(): void
    {
        $rules = [
            'cargo' => ['required', Rule::in([
                ServidorResource::CARGO_PROFESSOR,
                ServidorResource::CARGO_EQUIPE_GESTORA,
                ServidorResource::CARGO_MANUTENCAO,
                ServidorResource::CARGO_OBRAS,
                ServidorResource::CARGO_MOTORISTA,
                ServidorResource::CARGO_TRANSPORTE,
                ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
            ])],
        ];

        if ($this->cargo === ServidorResource::CARGO_MOTORISTA
            || $this->cargo === ServidorResource::CARGO_TRANSPORTE
            || $this->cargo === ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA) {
            $rules += [
                'matriculaOperacional' => ['nullable', 'string', 'max:255'],
            ];
        } else {
            $rules += [
                'matriculas' => ['required', 'array', 'min:1', 'max:'.PessoaMatricula::MAX_POR_PESSOA],
                'matriculas.*' => ['array'],
                'matriculas.*.id' => ['nullable', 'integer'],
                'matriculas.*.matricula' => ['required', 'string', 'max:255'],
                'matriculas.*.turno' => ['required', Rule::in(array_keys(PessoaMatricula::turnosOptions()))],
                'matriculas.*.escolas' => ['array'],
            ];
        }

        if ($this->cargo === ServidorResource::CARGO_PROFESSOR) {
            $rules += [
                'matriculas.*.escolas.*' => ['array'],
                'matriculas.*.escolas.*.id' => ['nullable', 'integer'],
                'matriculas.*.escolas.*.id_escola' => ['required', 'integer'],
                'matriculas.*.escolas.*.vinculos_turma_componente' => ['array'],
                'matriculas.*.escolas.*.vinculos_turma_componente.*' => ['array'],
                'matriculas.*.escolas.*.vinculos_turma_componente.*.turma_id' => ['required', 'integer'],
                'matriculas.*.escolas.*.vinculos_turma_componente.*.componente_curricular_id' => ['required', 'integer'],
            ];
        } elseif ($this->cargo === ServidorResource::CARGO_EQUIPE_GESTORA) {
            $rules += [
                'idEscolaGestora' => ['required', 'integer'],
                'cargosGestores' => ['required', 'array', 'min:1', 'max:2'],
                'cargosGestores.*' => [Rule::in([
                    ServidorEquipeGestoraForm::CARGO_DIRETOR,
                    ServidorEquipeGestoraForm::CARGO_COORDENADOR,
                    ServidorEquipeGestoraForm::CARGO_SECRETARIO,
                ])],
                'portaria' => ['nullable', 'string', 'max:255'],
                'turmaIds' => ['array'],
                'turmaIds.*' => ['integer'],
            ];
        } elseif ($this->cargo === ServidorResource::CARGO_MANUTENCAO) {
            $rules += [
                'setorManutencaoId' => ['required', 'integer'],
            ];
        } elseif ($this->cargo === ServidorResource::CARGO_OBRAS) {
            $rules += [
                'setorObrasId' => ['required', 'integer'],
            ];
        }

        $this->validate($rules, attributes: [
            'idEscolaGestora' => 'escola',
            'cargosGestores' => 'cargos gestores',
            'turmaIds' => 'turmas da coordenação',
            'setorManutencaoId' => 'setor da Manutenção',
            'setorObrasId' => 'setor de Obras',
            'matriculaMotorista' => 'matrícula',
            'matriculaOperacional' => 'matrícula',
        ]);

        $cargosSemMatriculas = [
            ServidorResource::CARGO_MOTORISTA,
            ServidorResource::CARGO_TRANSPORTE,
            ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
        ];

        if (! in_array($this->cargo, $cargosSemMatriculas, true)) {
            PessoaMatricula::assertConjuntoTurnosValido(
                collect($this->matriculas)->pluck('turno')->map(fn (mixed $turno): string => (string) $turno)->all(),
            );
            $this->validarMatriculasDuplicadas();
            $this->validarIdsDasMatriculas();
        }

        if ($this->cargo === ServidorResource::CARGO_PROFESSOR) {
            $this->validarEstruturaProfessor();
        } elseif ($this->cargo === ServidorResource::CARGO_EQUIPE_GESTORA) {
            $this->validarEstruturaEquipeGestora();
        } elseif ($this->cargo === ServidorResource::CARGO_MANUTENCAO) {
            $this->validarEstruturaManutencao();
        } elseif ($this->cargo === ServidorResource::CARGO_OBRAS) {
            $this->validarEstruturaObras();
        }
    }

    private function validarEstruturaProfessor(): void
    {
        $escolasOptions = $this->escolasOptionsSeguras();
        $lotacaoIds = collect($this->matriculas)
            ->flatMap(fn (array $matricula): array => $matricula['escolas'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);
        if ($lotacaoIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Uma mesma lotação não pode aparecer mais de uma vez.',
            ]);
        }

        foreach ($this->matriculas as $matriculaKey => $matricula) {
            $escolaIds = collect($matricula['escolas'] ?? [])
                ->pluck('id_escola')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id);
            if ($escolaIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "matriculas.{$matriculaKey}.escolas" => 'Uma escola não pode aparecer duas vezes na mesma matrícula.',
                ]);
            }

            foreach (($matricula['escolas'] ?? []) as $lotacaoKey => $lotacao) {
                $escolaId = (int) ($lotacao['id_escola'] ?? 0);
                if (! array_key_exists($escolaId, $escolasOptions)) {
                    throw ValidationException::withMessages([
                        "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.id_escola" => 'A escola não pertence ao seu escopo.',
                    ]);
                }

                $this->validarIdLotacao($lotacao['id'] ?? null);
                $duplicados = collect($lotacao['vinculos_turma_componente'] ?? [])
                    ->map(fn (array $vinculo): string => (int) $vinculo['turma_id'].'-'.(int) $vinculo['componente_curricular_id'])
                    ->duplicates();
                if ($duplicados->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente" => 'Uma turma e componente não podem ser vinculados duas vezes.',
                    ]);
                }

                foreach (($lotacao['vinculos_turma_componente'] ?? []) as $vinculoKey => $vinculo) {
                    $this->validarVinculoPedagogico(
                        $matriculaKey,
                        $lotacaoKey,
                        $vinculoKey,
                        $matricula,
                        $lotacao,
                        $vinculo,
                    );
                }
            }
        }
    }

    private function validarEstruturaEquipeGestora(): void
    {
        $this->autorizarEquipeGestora();
        $escolasOptions = $this->escolasOptionsSeguras();
        if (! $this->idEscolaGestora || ! array_key_exists($this->idEscolaGestora, $escolasOptions)) {
            throw ValidationException::withMessages([
                'idEscolaGestora' => 'A escola não pertence ao seu escopo.',
            ]);
        }

        $cargos = collect($this->cargosGestores)->unique()->values();
        if ($cargos->contains(ServidorEquipeGestoraForm::CARGO_SECRETARIO) && $cargos->count() > 1) {
            throw ValidationException::withMessages([
                'cargosGestores' => 'Secretário é exclusivo e não pode ser combinado com outro cargo gestor.',
            ]);
        }

        $exigePortaria = $cargos->intersect([
            ServidorEquipeGestoraForm::CARGO_DIRETOR,
            ServidorEquipeGestoraForm::CARGO_COORDENADOR,
        ])->isNotEmpty();
        if ($exigePortaria && blank($this->portaria)) {
            throw ValidationException::withMessages([
                'portaria' => 'A portaria é obrigatória para Diretor e Coordenador.',
            ]);
        }
        if (filled($this->portaria) && ! preg_match('/^[\pL\pN][\pL\pN .\/_-]*$/u', (string) $this->portaria)) {
            throw ValidationException::withMessages([
                'portaria' => 'A portaria contém caracteres inválidos.',
            ]);
        }

        $turmasOptions = $this->turmasGestaoOptions($escolasOptions);
        $turmaIds = collect($this->turmaIds)->map(fn (mixed $id): int => (int) $id)->unique()->values();
        if ($turmaIds->contains(fn (int $id): bool => ! array_key_exists($id, $turmasOptions))) {
            throw ValidationException::withMessages([
                'turmaIds' => 'Uma das turmas não pertence à escola selecionada.',
            ]);
        }
        if ($cargos->contains(ServidorEquipeGestoraForm::CARGO_COORDENADOR) && $turmaIds->isEmpty()) {
            throw ValidationException::withMessages([
                'turmaIds' => 'Selecione ao menos uma turma para o Coordenador.',
            ]);
        }
    }

    private function validarEstruturaManutencao(): void
    {
        $this->autorizarEquipeGestora();
        $options = $this->setoresManutencaoOptions();

        if (! $this->setorManutencaoId || ! array_key_exists($this->setorManutencaoId, $options)) {
            throw ValidationException::withMessages([
                'setorManutencaoId' => 'Selecione um setor ativo, sem vínculo escolar e dentro do seu escopo.',
            ]);
        }
    }

    private function validarEstruturaObras(): void
    {
        $this->autorizarEquipeGestora();
        $options = $this->setoresManutencaoOptions();

        if (! $this->setorObrasId || ! array_key_exists($this->setorObrasId, $options)) {
            throw ValidationException::withMessages([
                'setorObrasId' => 'Selecione um setor ativo, sem vínculo escolar e dentro do seu escopo.',
            ]);
        }
    }

    private function validarEstadoSomenteLeitura(Servidor $pessoa): void
    {
        if (
            (string) $this->nome !== (string) $pessoa->nome
            || (string) $this->status !== (string) $pessoa->status
            || filled($this->observacoes)
            || $this->cargo !== ServidorResource::CARGO_PROFESSOR
            || $this->idEscolaGestora !== null
            || $this->setorManutencaoId !== null
            || $this->setorObrasId !== null
            || $this->cargosGestores !== []
            || filled($this->portaria)
            || $this->turmaIds !== []
            || filled($this->matriculaOperacional)
        ) {
            throw ValidationException::withMessages([
                'formulario' => 'O formulário contém alterações estruturais não permitidas para este perfil.',
            ]);
        }
    }

    private function validarAtribuicoesEscopadas(Servidor $pessoa, bool $podeEditarAtribuicoes): void
    {
        $matriculaIdsRecebidos = collect($this->matriculas)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);
        if ($matriculaIdsRecebidos->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Uma mesma matrícula não pode aparecer mais de uma vez.',
            ]);
        }
        if (
            $matriculaIdsRecebidos->unique()->sort()->values()->all()
            !== collect($this->matriculaIdsPermitidos)->sort()->values()->all()
        ) {
            throw ValidationException::withMessages([
                'matriculas' => 'Matrículas são somente leitura neste perfil.',
            ]);
        }

        $idsRecebidos = collect($this->matriculas)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->flatMap(fn (array $matricula): array => is_array($matricula['escolas'] ?? null) ? $matricula['escolas'] : [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['id'] ?? null))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);
        if ($idsRecebidos->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Uma mesma lotação não pode aparecer mais de uma vez.',
            ]);
        }

        $idsEnviados = $idsRecebidos
            ->unique()
            ->sort()
            ->values();
        if ($idsEnviados->all() !== collect($this->lotacaoIdsPermitidos)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Matrículas e lotações são somente leitura neste perfil.',
            ]);
        }

        $professores = Professor::query()
            ->where('servidor_id', $pessoa->id)
            ->where('ativo', true)
            ->whereIn('id', $this->lotacaoIdsPermitidos)
            ->with('professorMatricula')
            ->get()
            ->keyBy('id');

        foreach ($this->matriculas as $matriculaKey => $matricula) {
            if (! is_array($matricula)) {
                throw ValidationException::withMessages(['matriculas' => 'Estado de matrícula inválido.']);
            }

            if (! is_array($matricula['escolas'] ?? null)) {
                throw ValidationException::withMessages(['matriculas' => 'Estado de lotações inválido.']);
            }

            foreach ($matricula['escolas'] as $lotacaoKey => $lotacao) {
                if (! is_array($lotacao) || ! filled($lotacao['id'] ?? null)) {
                    throw ValidationException::withMessages(['matriculas' => 'Não é permitido adicionar ou alterar lotações.']);
                }

                /** @var Professor|null $professor */
                $professor = $professores->get((int) $lotacao['id']);
                if (! $professor
                    || (int) ($lotacao['id_escola'] ?? 0) !== (int) $professor->id_escola
                    || (string) ($matricula['matricula'] ?? '') !== (string) $professor->matricula
                    || (string) ($matricula['turno'] ?? '') !== (string) $professor->turnoEfetivo()
                    || (filled($professor->professor_matricula_id)
                        && (int) ($matricula['id'] ?? 0) !== (int) $professor->professor_matricula_id)) {
                    throw ValidationException::withMessages([
                        "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}" => 'Dados estruturais da lotação foram alterados sem permissão.',
                    ]);
                }

                if (! is_array($lotacao['vinculos_turma_componente'] ?? null)) {
                    throw ValidationException::withMessages(['matriculas' => 'Estado de vínculos pedagógicos inválido.']);
                }

                $vinculosRecebidos = collect($lotacao['vinculos_turma_componente'])
                    ->filter(fn (mixed $vinculo): bool => is_array($vinculo))
                    ->map(fn (array $vinculo): string => (int) ($vinculo['turma_id'] ?? 0).'-'.(int) ($vinculo['componente_curricular_id'] ?? 0));
                if ($vinculosRecebidos->duplicates()->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente" => 'Uma turma e componente não podem ser vinculados duas vezes.',
                    ]);
                }

                foreach ($lotacao['vinculos_turma_componente'] as $vinculoKey => $vinculo) {
                    if (! is_array($vinculo)) {
                        throw ValidationException::withMessages(['matriculas' => 'Vínculo pedagógico inválido.']);
                    }
                    $this->validarVinculoPedagogico(
                        $matriculaKey,
                        $lotacaoKey,
                        $vinculoKey,
                        $matricula,
                        $lotacao,
                        $vinculo,
                    );
                }

                if (! $podeEditarAtribuicoes) {
                    $vinculosPersistidos = TurmaComponenteProfessor::query()
                        ->where('professor_id', $professor->id)
                        ->where('tem_professor', true)
                        ->get(['turma_id', 'componente_curricular_id'])
                        ->map(fn (TurmaComponenteProfessor $vinculo): string => $vinculo->turma_id.'-'.$vinculo->componente_curricular_id)
                        ->sort()
                        ->values();

                    if ($vinculosRecebidos->sort()->values()->all() !== $vinculosPersistidos->all()) {
                        throw ValidationException::withMessages([
                            "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente" => 'Turmas e componentes são somente leitura neste perfil.',
                        ]);
                    }
                }
            }
        }
    }

    private function validarVinculoPedagogico(
        string $matriculaKey,
        string $lotacaoKey,
        string $vinculoKey,
        array $matricula,
        array $lotacao,
        array $vinculo,
    ): void {
        $turmaId = (int) ($vinculo['turma_id'] ?? 0);
        $componenteId = (int) ($vinculo['componente_curricular_id'] ?? 0);
        $escolaId = (int) ($lotacao['id_escola'] ?? 0);
        $turma = Turma::query()->with('serie.componentesCurriculares')->find($turmaId);

        if (! $turma || (int) $turma->id_escola !== $escolaId) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente.{$vinculoKey}.turma_id" => 'A turma não pertence à escola da lotação.',
            ]);
        }

        $turnosCompativeis = PessoaMatricula::turnosTurmaCompativeis((string) ($matricula['turno'] ?? ''));
        $vinculoJaExistia = filled($lotacao['id'] ?? null) && TurmaComponenteProfessor::query()
            ->where('professor_id', (int) $lotacao['id'])
            ->where('turma_id', $turmaId)
            ->where('componente_curricular_id', $componenteId)
            ->where('tem_professor', true)
            ->exists();
        if (! in_array((string) $turma->turno, $turnosCompativeis, true) && ! $vinculoJaExistia) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente.{$vinculoKey}.turma_id" => 'A turma não é compatível com o turno da matrícula.',
            ]);
        }

        $componentes = $turma->serie?->componentesCurriculares
            ?->pluck('id')->map(fn (mixed $id): int => (int) $id) ?? collect();
        if (! $componentes->contains($componenteId)) {
            throw ValidationException::withMessages([
                "matriculas.{$matriculaKey}.escolas.{$lotacaoKey}.vinculos_turma_componente.{$vinculoKey}.componente_curricular_id" => 'O componente não pertence à série da turma.',
            ]);
        }
    }

    private function validarMatriculasDuplicadas(): void
    {
        $duplicadas = collect($this->matriculas)
            ->pluck('matricula')
            ->map(fn (mixed $matricula): string => mb_strtolower(trim((string) $matricula)))
            ->duplicates();
        if ($duplicadas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Não é permitido repetir o número de matrícula.',
            ]);
        }
    }

    private function validarIdsDasMatriculas(): void
    {
        $idsRecebidos = collect($this->matriculas)
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);
        if ($idsRecebidos->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'matriculas' => 'Uma mesma matrícula não pode aparecer mais de uma vez.',
            ]);
        }

        $ids = $idsRecebidos->unique();

        if ($this->modoCriacao() && $ids->isNotEmpty()) {
            throw ValidationException::withMessages(['matriculas' => 'A criação não aceita IDs de matrículas existentes.']);
        }
        if (! $this->modoCriacao() && $ids->diff($this->matriculaIdsPermitidos)->isNotEmpty()) {
            throw ValidationException::withMessages(['matriculas' => 'Uma matrícula informada não pertence a esta pessoa.']);
        }
    }

    private function validarIdLotacao(mixed $id): void
    {
        if (! filled($id)) {
            return;
        }
        if ($this->modoCriacao() || ! in_array((int) $id, $this->lotacaoIdsPermitidos, true)) {
            throw ValidationException::withMessages(['matriculas' => 'Uma lotação informada não pertence a esta pessoa.']);
        }
    }

    /** @return array<string, mixed> */
    private function payloadEstrutural(): array
    {
        return [
            'nome' => trim($this->nome),
            'cpf' => $this->cpf,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'cargo' => $this->cargo,
            'matricula_motorista' => $this->matriculaMotorista,
            'matricula_operacional' => $this->matriculaOperacional,
            'matriculas_professor' => $this->payloadMatriculas(),
            'id_escola' => $this->idEscolaGestora,
            'setor_manutencao_id' => $this->setorManutencaoId,
            'setor_obras_id' => $this->setorObrasId,
            'cargos_gestores' => array_values($this->cargosGestores),
            'portaria' => $this->portaria,
            'turma_ids' => collect($this->turmaIds)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function payloadMatriculas(): array
    {
        return collect($this->matriculas)
            ->filter(fn (mixed $matricula): bool => is_array($matricula))
            ->map(function (array $matricula): array {
                return [
                    'id' => filled($matricula['id'] ?? null) ? (int) $matricula['id'] : null,
                    'matricula' => trim((string) ($matricula['matricula'] ?? '')),
                    'turno' => (string) ($matricula['turno'] ?? ''),
                    'escolas' => collect($matricula['escolas'] ?? [])
                        ->filter(fn (mixed $lotacao): bool => is_array($lotacao))
                        ->map(fn (array $lotacao): array => [
                            'id' => filled($lotacao['id'] ?? null) ? (int) $lotacao['id'] : null,
                            'id_escola' => filled($lotacao['id_escola'] ?? null) ? (int) $lotacao['id_escola'] : null,
                            'vinculos_turma_componente' => collect($lotacao['vinculos_turma_componente'] ?? [])
                                ->filter(fn (mixed $vinculo): bool => is_array($vinculo))
                                ->map(fn (array $vinculo): array => [
                                    'turma_id' => filled($vinculo['turma_id'] ?? null) ? (int) $vinculo['turma_id'] : null,
                                    'componente_curricular_id' => filled($vinculo['componente_curricular_id'] ?? null)
                                        ? (int) $vinculo['componente_curricular_id']
                                        : null,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $matriculas
     * @return array<string, array<string, mixed>>
     */
    private function normalizarEstadoMatriculas(array $matriculas): array
    {
        $estado = [];

        foreach ($matriculas as $matricula) {
            if (! is_array($matricula)) {
                continue;
            }

            $key = filled($matricula['id'] ?? null) ? 'm'.(int) $matricula['id'] : $this->novaChave('m');
            $lotacoes = [];
            foreach (($matricula['escolas'] ?? []) as $lotacao) {
                if (! is_array($lotacao)) {
                    continue;
                }

                $lotacaoKey = filled($lotacao['id'] ?? null) ? 'l'.(int) $lotacao['id'] : $this->novaChave('l');
                $vinculos = [];
                foreach (($lotacao['vinculos_turma_componente'] ?? []) as $vinculo) {
                    if (! is_array($vinculo)) {
                        continue;
                    }
                    $vinculos[$this->novaChave('v')] = [
                        'turma_id' => filled($vinculo['turma_id'] ?? null) ? (int) $vinculo['turma_id'] : null,
                        'componente_curricular_id' => filled($vinculo['componente_curricular_id'] ?? null)
                            ? (int) $vinculo['componente_curricular_id']
                            : null,
                    ];
                }
                $lotacoes[$lotacaoKey] = [
                    'id' => filled($lotacao['id'] ?? null) ? (int) $lotacao['id'] : null,
                    'id_escola' => filled($lotacao['id_escola'] ?? null) ? (int) $lotacao['id_escola'] : null,
                    'vinculos_turma_componente' => $vinculos,
                ];
            }

            $turno = (string) ($matricula['turno'] ?? '');
            if (! array_key_exists($turno, PessoaMatricula::turnosOptions())) {
                $turno = $this->inferirTurnoLegado($lotacoes);
            }
            $estado[$key] = [
                'id' => filled($matricula['id'] ?? null) ? (int) $matricula['id'] : null,
                'matricula' => (string) ($matricula['matricula'] ?? ''),
                'turno' => $turno,
                'escolas' => $lotacoes,
            ];
        }

        return $estado;
    }

    /** @param array<string, array<string, mixed>> $lotacoes */
    private function inferirTurnoLegado(array $lotacoes): string
    {
        $professorIds = collect($lotacoes)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
        if ($professorIds === []) {
            return '';
        }

        $turnos = Professor::query()
            ->whereIn('id', $professorIds)
            ->get()
            ->map(fn (Professor $professor): ?string => $professor->turnoEfetivo())
            ->filter(fn (?string $turno): bool => filled($turno) && array_key_exists($turno, PessoaMatricula::turnosOptions()))
            ->unique()
            ->values();

        return $turnos->count() === 1 ? (string) $turnos->first() : '';
    }

    private function completarTurnoLegadoAposRemocao(): void
    {
        if (count($this->matriculas) !== PessoaMatricula::MAX_POR_PESSOA) {
            return;
        }

        $semTurno = collect($this->matriculas)
            ->filter(fn (mixed $matricula): bool => is_array($matricula)
                && ! array_key_exists((string) ($matricula['turno'] ?? ''), PessoaMatricula::turnosOptions()));
        $comTurno = collect($this->matriculas)
            ->filter(fn (mixed $matricula): bool => is_array($matricula)
                && in_array((string) ($matricula['turno'] ?? ''), ['manha', 'tarde'], true));

        if ($semTurno->count() !== 1 || $comTurno->count() !== 1) {
            return;
        }

        $matriculaKey = (string) $semTurno->keys()->first();
        $turnoOcupado = (string) $comTurno->first()['turno'];
        $this->matriculas[$matriculaKey]['turno'] = $turnoOcupado === 'manha' ? 'tarde' : 'manha';
    }

    private function aplicarRemocoesPendentesDeMatriculas(): void
    {
        foreach ($this->matriculaKeysRemovidas as $matriculaKey) {
            unset($this->matriculas[$matriculaKey], $this->lotacoesAtivas[$matriculaKey]);
        }

        if ($this->matriculaIdsRemovidos !== []) {
            $this->matriculas = collect($this->matriculas)
                ->reject(fn (mixed $matricula): bool => is_array($matricula)
                    && filled($matricula['id'] ?? null)
                    && in_array((int) $matricula['id'], $this->matriculaIdsRemovidos, true))
                ->all();
        }

        $this->completarTurnoLegadoAposRemocao();
        if (! array_key_exists((string) $this->matriculaAtiva, $this->matriculas)) {
            $this->matriculaAtiva = array_key_first($this->matriculas);
        }
    }

    private function capturarIdsPermitidos(): void
    {
        $this->matriculaIdsPermitidos = collect($this->matriculas)
            ->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $this->lotacaoIdsPermitidos = collect($this->matriculas)
            ->flatMap(fn (array $matricula): array => $matricula['escolas'] ?? [])
            ->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    /** @return array<int, string> */
    private function escolasOptionsSeguras(): array
    {
        return collect(ServidorResource::escolasOptionsEscopadas())
            ->mapWithKeys(fn (mixed $nome, mixed $id): array => [(int) $id => (string) $nome])
            ->all();
    }

    /** @return array<int, string> */
    private function setoresManutencaoOptions(): array
    {
        $user = $this->usuarioAutenticado();
        $scope = app(PessoaScopeService::class);
        $query = Setor::query()
            ->where('ativo', true)
            ->where('exige_vinculo_escola', false);

        if (! $scope->hasGlobalAccess($user)) {
            $ids = $scope->visibleSetorIds($user);

            if ($ids === []) {
                return [];
            }

            $query->whereIn('id', $ids);
        }

        return $query
            ->orderedTree()
            ->get()
            ->mapWithKeys(fn (Setor $setor): array => [$setor->id => $setor->nome_completo])
            ->all();
    }

    /**
     * @param  list<int>  $selecionadas
     * @param  array<int, string>  $escolasOptions
     * @return array<int, string>
     */
    private function turmasOptionsSeguras(?int $escolaId, string $turno, array $selecionadas, array $escolasOptions): array
    {
        if (! $escolaId || ! array_key_exists($escolaId, $escolasOptions)) {
            return [];
        }

        $query = Turma::query()
            ->where('id_escola', $escolaId)
            ->with('serie:id,nome')
            ->orderBy('turno')
            ->orderBy('nome');
        $turnos = array_key_exists($turno, PessoaMatricula::turnosOptions())
            ? PessoaMatricula::turnosTurmaCompativeis($turno)
            : [];

        if ($turnos !== []) {
            $query->where(function ($turmas) use ($turnos, $selecionadas): void {
                $turmas->whereIn('turno', $turnos);
                if ($selecionadas !== []) {
                    $turmas->orWhereIn('id', $selecionadas);
                }
            });
        } elseif ($selecionadas !== []) {
            $query->whereIn('id', $selecionadas);
        } else {
            return [];
        }

        return $query->get()->mapWithKeys(function (Turma $turma): array {
            $nome = collect([$turma->serie?->nome, $turma->nome])->filter()->implode(' - ');
            $turno = Professor::turnosOptions()[$turma->turno] ?? $turma->turno;

            return [$turma->id => trim("{$nome} ({$turno})")];
        })->all();
    }

    /**
     * @param  list<int|string>  $turmasPermitidas
     * @return array<int, string>
     */
    private function componentesOptionsSeguras(?int $turmaId, array $turmasPermitidas): array
    {
        if (! $turmaId || ! in_array($turmaId, array_map('intval', $turmasPermitidas), true)) {
            return [];
        }

        $turma = Turma::query()->with('serie.componentesCurriculares')->find($turmaId);

        return $turma?->serie?->componentesCurriculares
            ?->sortBy('nome')
            ->mapWithKeys(fn ($componente): array => [$componente->id => $componente->nome])
            ->all() ?? [];
    }

    /**
     * @param  array<int, string>  $escolasOptions
     * @return array<int, string>
     */
    private function turmasGestaoOptions(array $escolasOptions): array
    {
        if (! $this->idEscolaGestora || ! array_key_exists($this->idEscolaGestora, $escolasOptions)) {
            return [];
        }

        return Turma::query()
            ->where('id_escola', $this->idEscolaGestora)
            ->with('serie:id,nome')
            ->orderBy('turno')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Turma $turma): array {
                $nome = collect([$turma->serie?->nome, $turma->nome])->filter()->implode(' - ');
                $turno = Professor::turnosOptions()[$turma->turno] ?? $turma->turno;

                return [$turma->id => trim("{$nome} ({$turno})")];
            })
            ->all();
    }

    /** @param array<string, mixed> $matricula */
    private function matriculaLabel(array $matricula, array $turnosOptions): string
    {
        $numero = filled($matricula['matricula'] ?? null) ? (string) $matricula['matricula'] : 'Nova matrícula';
        $turno = $turnosOptions[$matricula['turno'] ?? ''] ?? null;

        return $turno ? "{$numero} · {$turno}" : $numero;
    }

    private function modoCriacao(): bool
    {
        return $this->pessoaId === null;
    }

    private function usuarioAutenticado(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function novaChave(string $prefixo): string
    {
        return $prefixo.Str::lower(Str::random(12));
    }
}
