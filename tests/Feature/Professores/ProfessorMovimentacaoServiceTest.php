<?php

namespace Tests\Feature\Professores;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\ProfessorMovimentacaoService;
use App\Services\UserService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAvaliacaoDocumentos;
use Tests\TestCase;

class ProfessorMovimentacaoServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAvaliacaoDocumentos;

    public function test_bloqueia_transferencia_com_resposta_ausente(): void
    {
        $cenario = $this->criarCenarioAvaliativo();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('O professor precisa terminar de preencher as avaliações pendentes antes de ser transferido.');

        app(ProfessorMovimentacaoService::class)->transferir(
            $cenario['professor'],
            $this->criarEscola('Escola Destino'),
            $cenario['usuario'],
        );
    }

    public function test_bloqueia_desativacao_com_observacao_obrigatoria_vazia(): void
    {
        $cenario = $this->criarCenarioAvaliativo(observacaoObrigatoria: true);

        $this->criarDocumentoResposta([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'professor_id' => $cenario['professor']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'observacao' => '',
            'respondido_em' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('O professor precisa terminar de preencher as avaliações pendentes antes de ser desativado.');

        app(ProfessorMovimentacaoService::class)->desativar($cenario['professor'], $cenario['usuario']);
    }

    public function test_transfere_professor_sem_pendencias_remove_vinculos_e_preserva_respostas(): void
    {
        $cenario = $this->criarCenarioAvaliativo();
        $destino = $this->criarEscola('Escola Transferencia Destino');

        $this->criarDocumentoResposta([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'professor_id' => $cenario['professor']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'respondido_em' => now(),
        ]);

        app(ProfessorMovimentacaoService::class)->transferir($cenario['professor'], $destino, $cenario['usuario']);

        $this->assertDatabaseHas('professores', [
            'id' => $cenario['professor']->id,
            'id_escola' => $destino->id,
            'ativo' => true,
        ]);

        $this->assertDatabaseHas('turma_componente_professor', [
            'turma_id' => $cenario['turma']->id,
            'componente_curricular_id' => $cenario['componente']->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $this->assertDatabaseHas('avaliacao_aluno_documentos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'professor_id' => $cenario['professor']->id,
            'alternativa_id' => $cenario['alternativa']->id,
        ]);

        $usuarioAtualizado = $cenario['usuario']->fresh('escolas');
        $this->assertSame($destino->id, (int) $usuarioAtualizado->id_escola);
        $this->assertSame([$destino->id], $usuarioAtualizado->escolas->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_desativa_professor_sem_pendencias_oculta_acesso_e_preserva_historico(): void
    {
        $cenario = $this->criarCenarioAvaliativo();

        $this->criarDocumentoResposta([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'professor_id' => $cenario['professor']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'respondido_em' => now(),
        ]);

        app(ProfessorMovimentacaoService::class)->desativar($cenario['professor'], $cenario['usuario'], 'Removido da unidade.');

        $this->assertDatabaseHas('professores', [
            'id' => $cenario['professor']->id,
            'ativo' => false,
            'desativado_por_id' => $cenario['usuario']->id,
            'motivo_desativacao' => 'Removido da unidade.',
        ]);

        $this->assertNull($cenario['usuario']->fresh()->id_escola);
        $this->assertSame([], $cenario['usuario']->fresh('escolas')->escolas->pluck('id')->all());

        $this->assertFalse($cenario['usuario']->fresh()->ehProfessor());
        $this->assertDatabaseMissing('turma_componente_professor', [
            'turma_id' => $cenario['turma']->id,
            'professor_id' => $cenario['professor']->id,
            'tem_professor' => true,
        ]);

        $this->assertDatabaseHas('avaliacao_aluno_documentos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'professor_id' => $cenario['professor']->id,
        ]);
    }

    public function test_professor_substituto_assume_vinculo_sem_apagar_respostas_antigas(): void
    {
        $cenario = $this->criarCenarioAvaliativo();

        $this->criarDocumentoResposta([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'professor_id' => $cenario['professor']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'respondido_em' => now(),
        ]);

        app(ProfessorMovimentacaoService::class)->transferir(
            $cenario['professor'],
            $this->criarEscola('Escola Novo Contexto'),
            $cenario['usuario'],
        );

        $usuarioSubstituto = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $substituto = Professor::query()->create([
            'user_id' => $usuarioSubstituto->id,
            'id_escola' => $cenario['escola']->id,
            'matricula' => 'PROF-SUB',
            'turno' => 'manha',
            'nome' => 'Professor Substituto',
            'email' => 'substituto@edu.umuarama.pr.gov.br',
        ]);

        $cenario['turma']->componentes()->updateExistingPivot($cenario['componente']->id, [
            'professor_id' => $substituto->id,
            'tem_professor' => true,
        ]);

        $turmasVisiveis = app(UserService::class)
            ->aplicarFiltroTurmasDoUsuario(Turma::query(), $usuarioSubstituto->fresh())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$cenario['turma']->id], $turmasVisiveis);

        $this->assertDatabaseHas('avaliacao_aluno_documentos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['aluno']->id,
            'professor_id' => $cenario['professor']->id,
        ]);
    }

    private function criarCenarioAvaliativo(bool $observacaoObrigatoria = false): array
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Movimentacao', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Movimentacao', 'status' => true]);
        $escola = $this->criarEscola('Escola Movimentacao');
        $serie = Serie::query()->create(['codigo' => 'SER-MOV', 'nome' => 'Serie Movimentacao']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-MOV',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-MOV',
            'nome' => 'Componente Movimentacao',
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $professor = Professor::query()->create([
            'user_id' => $usuario->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-MOV',
            'turno' => 'manha',
            'nome' => 'Professor Movimentacao',
            'email' => 'movimentacao@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => $observacaoObrigatoria,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta Movimentacao',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao Movimentacao',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDay()->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Movimentacao',
            'cgm' => 'CGM-MOV',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        return compact('tipo', 'periodo', 'escola', 'serie', 'turma', 'componente', 'usuario', 'professor', 'alternativa', 'pauta', 'avaliacao', 'aluno');
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
