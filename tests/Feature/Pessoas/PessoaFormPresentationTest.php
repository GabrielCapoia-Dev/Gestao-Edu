<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PessoaFormPresentationTest extends TestCase
{
    public function test_formulario_exibe_o_cargo_rh_para_administradores(): void
    {
        $html = Blade::render(
            file_get_contents(resource_path('views/livewire/pessoas/partials/dados-pessoais.blade.php')),
            [
                'cargo' => ServidorResource::CARGO_PROFESSOR,
                'podeGerenciarEquipeGestora' => true,
                'modoCriacao' => true,
                'gerenciaEstrutura' => false,
                'podeEditarDados' => false,
                'statusOptions' => ['ativo' => 'Ativo'],
                'errors' => new ViewErrorBag(),
            ],
        );

        $this->assertStringContainsString('value="rh"', $html);
        $this->assertStringContainsString('>RH<', $html);
    }

    public function test_matriculas_de_assessoria_permitem_nova_matricula_sem_controles_de_jornada(): void
    {
        $html = Blade::render(
            file_get_contents(resource_path('views/livewire/pessoas/partials/matriculas-lotacoes.blade.php')),
            [
                'cargo' => ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
                'permiteJornada' => false,
                'modoCriacao' => true,
                'gerenciaEstrutura' => false,
                'podeEditarTurmas' => false,
                'jornadasArquivadas' => [],
                'matriculas' => [
                    'm1' => [
                        'id' => null,
                        'matricula' => 'ASSESSORIA-1',
                        'turno' => 'manha',
                        'jornada' => false,
                        'escolas' => [],
                    ],
                ],
                'matriculaLabels' => ['m1' => 'Manhã'],
                'matriculaAtiva' => 'm1',
                'turnosOptions' => [
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    'integral' => 'Integral',
                ],
                'turnosOptionsPorMatricula' => [
                    'm1' => [
                        'manha' => 'Manhã',
                        'tarde' => 'Tarde',
                        'integral' => 'Integral',
                    ],
                ],
                'lotacoesAtivas' => ['m1' => null],
                'escolasOptions' => [],
                'lotacaoLabels' => [],
                'turmasOptions' => [],
                'componentesOptions' => [],
                'errors' => new ViewErrorBag(),
            ],
        );

        $this->assertStringContainsString('Matrícula comum', $html);
        $this->assertStringContainsString('wire:click="adicionarMatricula"', $html);
        $this->assertStringNotContainsString('Jornada', $html);
        $this->assertStringNotContainsString('jornadaDaMatriculaAlterada', $html);
    }
}
