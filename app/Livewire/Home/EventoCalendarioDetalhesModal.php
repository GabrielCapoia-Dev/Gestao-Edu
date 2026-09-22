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

    public int $limiteParticipantes = 50;

    public int $limiteAlunos = 50;

    public ?string $erro = null;

    /** @var array<string, mixed> */
    public array $resumo = [];

    /** @var array<string, mixed> */
    public array $participantes = [];

    /** @var list<array<string, mixed>> */
    public array $escolas = [];

    /** @var array<string, mixed> */
    public array $alunos = [];

    /** @var list<array<string, mixed>> */
    public array $historico = [];

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
        $this->reset(['eventoId', 'resumo', 'participantes', 'escolas', 'alunos', 'historico', 'erro']);
    }

    public function selecionarAba(string $aba): void
    {
        abort_unless(in_array($aba, ['resumo', 'participantes', 'escolas', 'alunos', 'historico'], true), 422);
        $this->aba = $aba;
        $this->carregarAba();
    }

    public function pesquisarParticipantes(): void
    {
        $this->limiteParticipantes = 50;
        $this->participantes = [];
        $this->carregarParticipantes();
    }

    public function pesquisarAlunos(): void
    {
        $this->limiteAlunos = 50;
        $this->alunos = [];
        $this->carregarAlunos();
    }

    public function maisParticipantes(): void
    {
        $this->limiteParticipantes += 50;
        $this->carregarParticipantes();
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
                'escolas' => $this->escolas = $this->service()->escolas($this->usuario(), $this->eventoId, $this->contexto),
                'alunos' => $this->carregarAlunos(),
                'historico' => $this->historico = $this->service()->historico($this->usuario(), $this->eventoId, $this->contexto),
                default => null,
            };
        } catch (\Throwable $exception) {
            report($exception);
            $this->erro = 'Não foi possível carregar esta seção do evento.';
        } finally {
            $this->carregando = false;
        }
    }

    private function carregarParticipantes(): void
    {
        $this->participantes = $this->service()->participantes(
            $this->usuario(), $this->eventoId, $this->contexto, trim($this->buscaParticipante), $this->limiteParticipantes,
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
