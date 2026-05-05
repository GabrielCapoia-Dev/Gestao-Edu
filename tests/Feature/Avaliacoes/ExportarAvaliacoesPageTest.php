<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\ExportarAvaliacoes;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExportarAvaliacoesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_exportacao_respeita_escolas_vinculadas_quando_usuario_nao_lista_tudo(): void
    {
        Permission::findOrCreate('Exportar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Exportar Avaliações');

        $escolaPermitida = $this->criarEscola('Escola Permitida');
        $escolaBloqueada = $this->criarEscola('Escola Bloqueada');
        $usuario->escolas()->attach($escolaPermitida->id);

        $serie = $this->criarSerie('SER-EXP', '1o Ano');
        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'A');
        $turmaBloqueada = $this->criarTurma($escolaBloqueada, $serie, 'B');
        $avaliacao = $this->criarAvaliacao('Avaliacao Escopo', '2026-02-01');
        $avaliacao->turmas()->sync([$turmaPermitida->id, $turmaBloqueada->id]);

        $component = Livewire::actingAs($usuario)
            ->test(ExportarAvaliacoes::class)
            ->set('escopo', 'escola')
            ->set('avaliacao', $avaliacao->id);

        $escolas = $component->instance()->escolasDisponiveis;

        $this->assertTrue($escolas->contains('id', $escolaPermitida->id));
        $this->assertFalse($escolas->contains('id', $escolaBloqueada->id));
    }

    public function test_avaliacoes_do_aluno_aparecem_em_ordem_cronologica(): void
    {
        Permission::findOrCreate('Exportar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Exportar Avaliações');

        $escola = $this->criarEscola('Escola Cronologica');
        $usuario->escolas()->attach($escola->id);
        $serie = $this->criarSerie('SER-CRON', '2o Ano');
        $turma = $this->criarTurma($escola, $serie, 'C');
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Cronologico',
            'cgm' => 'CGM-CRON',
            'data_nascimento' => '2015-02-01',
            'id_turma' => $turma->id,
        ]);

        $avaliacaoMeio = $this->criarAvaliacao('Avaliacao Meio', '2026-03-01');
        $avaliacaoAntiga = $this->criarAvaliacao('Avaliacao Antiga', '2026-02-01');
        $avaliacaoNova = $this->criarAvaliacao('Avaliacao Nova', '2026-04-01');

        foreach ([$avaliacaoMeio, $avaliacaoAntiga, $avaliacaoNova] as $avaliacao) {
            $avaliacao->turmas()->sync([$turma->id]);
        }

        $component = Livewire::actingAs($usuario)
            ->test(ExportarAvaliacoes::class)
            ->set('escopo', 'aluno')
            ->set('aluno', $aluno->id);

        $this->assertSame(
            [$avaliacaoAntiga->id, $avaliacaoMeio->id, $avaliacaoNova->id],
            $component->instance()->avaliacoesDisponiveis->pluck('id')->all()
        );
    }

    private function criarAvaliacao(string $nome, string $dataInicio): Avaliacao
    {
        $tipo = TipoAvaliacao::query()->first()
            ?? TipoAvaliacao::query()->create(['nome' => 'Parecer Exportacao', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->first()
            ?? PeriodoAvaliacao::query()->create(['nome' => 'Periodo Exportacao', 'status' => true]);
        $componente = ComponenteCurricular::query()->first()
            ?? ComponenteCurricular::query()->create(['codigo' => 'COMP-EXP', 'nome' => 'Componente Exportacao']);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta '.$nome,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);

        $avaliacao = Avaliacao::query()->create([
            'nome' => $nome,
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => $dataInicio,
            'data_fim' => '2026-12-20',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pauta->id]);

        return $avaliacao;
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarSerie(string $codigo, string $nome): Serie
    {
        return Serie::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR'.strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
