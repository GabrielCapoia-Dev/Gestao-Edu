<?php

namespace App\Livewire\Home;

use App\Services\Dashboard\EventoCalendarioDetalhesService;
use App\Services\ProfilePreviewService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EventoCalendarioDetalhesModal extends Component
{
    public bool $aberto = false;

    public bool $carregando = false;

    public ?int $eventoId = null;

    public string $contexto = 'pessoal';

    public string $aba = 'resumo';

    public string $buscaParticipante = '';

    public string $buscaAluno = '';

    public int $paginaParticipantes = 1;

    public int $porPaginaParticipantes = 10;

    public int $paginaEscolas = 1;

    public int $porPaginaEscolas = 10;

    public int $limiteAlunos = 50;

    public ?string $erro = null;

    /** @var array<string, mixed> */
    public array $resumo = [];

    /** @var array<string, mixed> */
    public array $participantes = [];

    /** @var array<string, mixed> */
    public array $escolas = [];

    /** @var array<string, mixed> */
    public array $alunos = [];

    #[On('abrir-evento-detalhes')]
    public function abrir(int $eventoId, string $contexto = 'pessoal'): void
    {
        $this->reset();
        $this->eventoId = $eventoId;
        $this->contexto = in_array($contexto, ['pessoal', 'rede'], true) ? $contexto : 'pessoal';
        $this->aberto = true;
        $this->carregando = true;

        try {
            $this->resumo = $this->service()->resumo($this->usuario(), $eventoId, $this->contexto);
        } catch (\Throwable $exception) {
            report($exception);
            $this->erro = $exception->getMessage() ?: 'Não foi possível carregar os detalhes deste evento.';
        } finally {
            $this->carregando = false;
        }
    }

    public function fechar(): void
    {
        $this->aberto = false;
        $this->reset(['eventoId', 'resumo', 'participantes', 'escolas', 'alunos', 'erro']);
    }

    public function selecionarAba(string $aba): void
    {
        abort_unless(in_array($aba, $this->abasDisponiveis(), true), 422);
        $this->aba = $aba;
        $this->carregarAba();
    }

    public function pesquisarParticipantes(): void
    {
        $this->paginaParticipantes = 1;
        $this->participantes = [];
        $this->carregarParticipantes();
    }

    public function alterarPorPaginaParticipantes(int $porPagina): void
    {
        abort_unless(in_array($porPagina, [5, 10, 25, 50], true), 422);
        $this->porPaginaParticipantes = $porPagina;
        $this->paginaParticipantes = 1;
        $this->carregarParticipantes();
    }

    public function irParaPaginaParticipantes(int $pagina): void
    {
        $this->paginaParticipantes = max(1, $pagina);
        $this->carregarParticipantes();
    }

    public function alterarPorPaginaEscolas(int $porPagina): void
    {
        abort_unless(in_array($porPagina, [5, 10, 25, 50], true), 422);
        $this->porPaginaEscolas = $porPagina;
        $this->paginaEscolas = 1;
        $this->carregarEscolas();
    }

    public function irParaPaginaEscolas(int $pagina): void
    {
        $this->paginaEscolas = max(1, $pagina);
        $this->carregarEscolas();
    }

    public function pesquisarAlunos(): void
    {
        $this->limiteAlunos = 50;
        $this->alunos = [];
        $this->carregarAlunos();
    }

    public function maisAlunos(): void
    {
        $this->limiteAlunos += 50;
        $this->carregarAlunos();
    }

    public function render(): View
    {
        return view('livewire.home.evento-calendario-detalhes-modal');
    }

    private function carregarAba(): void
    {
        if (! $this->eventoId) {
            return;
        }

        $this->carregando = true;
        $this->erro = null;

        try {
            match ($this->aba) {
                'participantes' => $this->carregarParticipantes(),
                'escolas' => $this->carregarEscolas(),
                'alunos' => $this->carregarAlunos(),
                default => null,
            };
        } catch (\Throwable $exception) {
            report($exception);
            $this->erro = 'Não foi possível carregar esta seção do evento.';
        } finally {
            $this->carregando = false;
        }
    }

    /** @return list<string> */
    public function abasDisponiveis(): array
    {
        $abas = ['resumo', 'participantes'];

        if ((bool) ($this->resumo['possui_transporte'] ?? false)) {
            $abas[] = 'escolas';
            $abas[] = 'alunos';
        }

        return $abas;
    }

    private function carregarParticipantes(): void
    {
        $this->participantes = $this->service()->participantes(
            $this->usuario(), $this->eventoId, $this->contexto, trim($this->buscaParticipante), $this->paginaParticipantes, $this->porPaginaParticipantes,
        );
    }

    private function carregarEscolas(): void
    {
        $this->escolas = $this->service()->escolas(
            $this->usuario(), $this->eventoId, $this->contexto, $this->paginaEscolas, $this->porPaginaEscolas,
        );
    }

    private function carregarAlunos(): void
    {
        $this->alunos = $this->service()->alunos(
            $this->usuario(), $this->eventoId, $this->contexto, trim($this->buscaAluno), $this->limiteAlunos,
        );
    }

    private function usuario()
    {
        return app(ProfilePreviewService::class)->effectiveUser() ?? abort(403);
    }

    private function service(): EventoCalendarioDetalhesService
    {
        return app(EventoCalendarioDetalhesService::class);
    }
}
