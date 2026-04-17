<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\AvaliacoesProfessor;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AvaliacaoProfessorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_visualiza_apenas_avaliacoes_pendentes_dos_componentes_que_leciona(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $escola = $this->criarEscola('Escola Base');
        $turma = $this->criarTurma($escola, 'Turma A');

        $componenteMatematica = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-MAT',
            'nome' => 'Matemática',
        ]);

        $componenteHistoria = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-HIS',
            'nome' => 'História',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-001',
            'nome' => 'Professor Matemática',
            'email' => 'matematica@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componenteMatematica->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $pautaMatematica = $this->criarPautaComAlternativa('Pauta de Matemática', $componenteMatematica->id);
        $pautaHistoria = $this->criarPautaComAlternativa('Pauta de História', $componenteHistoria->id);

        $avaliacaoVisivel = Avaliacao::query()->create([
            'nome' => 'Avaliação Bimestral - Matemática',
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacaoVisivel->pautas()->attach($pautaMatematica->id);
        $avaliacaoVisivel->turmas()->attach($turma->id);

        $avaliacaoNaoVisivelComponente = Avaliacao::query()->create([
            'nome' => 'Avaliação Bimestral - História',
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacaoNaoVisivelComponente->pautas()->attach($pautaHistoria->id);
        $avaliacaoNaoVisivelComponente->turmas()->attach($turma->id);

        $avaliacaoInativa = Avaliacao::query()->create([
            'nome' => 'Avaliação Inativa',
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_INATIVA,
        ]);
        $avaliacaoInativa->pautas()->attach($pautaMatematica->id);
        $avaliacaoInativa->turmas()->attach($turma->id);

        $this->actingAs($userProfessor)
            ->get(route('filament.admin.pages.avaliacoes-professor'))
            ->assertOk()
            ->assertSee('Avaliação Bimestral - Matemática')
            ->assertDontSee('Avaliação Bimestral - História')
            ->assertDontSee('Avaliação Inativa');
    }

    public function test_professor_aplica_avaliacao_em_massa_e_salva_respostas_por_aluno(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $escola = $this->criarEscola('Escola Avaliações');
        $turma = $this->criarTurma($escola, 'Turma B');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-CIEN',
            'nome' => 'Ciências',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-002',
            'nome' => 'Professor Ciências',
            'email' => 'ciencias@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $pauta = Pauta::query()->create([
            'texto' => 'Participação em sala',
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);

        $alternativaA = Alternativa::query()->create([
            'nome' => 'Excelente',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $alternativaB = Alternativa::query()->create([
            'nome' => 'Regular',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta->alternativas()->attach([$alternativaA->id, $alternativaB->id]);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliação Diagnóstica',
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(5)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);

        $alunoA = Aluno::query()->create([
            'nome' => 'Aluno A',
            'cgm' => 'CGM-A-001',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        $alunoB = Aluno::query()->create([
            'nome' => 'Aluno B',
            'cgm' => 'CGM-B-001',
            'data_nascimento' => '2015-02-01',
            'id_turma' => $turma->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacoesProfessor::class)
            ->set("avaliacaoEmMassa.{$pauta->id}", $alternativaA->id)
            ->call('aplicarEmMassa', $pauta->id)
            ->assertSet("respostas.{$pauta->id}.{$alunoA->id}.alternativa_id", $alternativaA->id)
            ->assertSet("respostas.{$pauta->id}.{$alunoB->id}.alternativa_id", $alternativaA->id)
            ->set("respostas.{$pauta->id}.{$alunoA->id}.observacao", 'Participou bastante.')
            ->call('salvarRespostas');

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoA->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativaA->id,
            'observacao' => null,
        ]);

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoB->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativaA->id,
        ]);
    }

    private function criarPautaComAlternativa(string $texto, int $componenteId): Pauta
    {
        $pauta = Pauta::query()->create([
            'texto' => $texto,
            'componente_curricular_id' => $componenteId,
            'status' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta->alternativas()->attach($alternativa->id);

        return $pauta;
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, string $nome): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER' . strtoupper(substr(md5($nome), 0, 4)),
            'nome' => 'Série ' . $nome,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR' . strtoupper(substr(md5($nome . microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
