<?php

namespace Tests\Feature\Pessoas;

use App\Livewire\Pessoas\PessoaForm;
use App\Models\Escola;
use App\Models\Lotacao;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PessoaDadosFuncionaisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_novos_campos_sao_opcionais_no_banco_e_possuem_casts_e_relacao(): void
    {
        $legado = Servidor::query()->create([
            'nome' => 'Pessoa Legada',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->assertNull($legado->carga_horaria);
        $this->assertNull($legado->jornada);
        $this->assertNull($legado->lotacao_id);

        $escola = $this->criarEscola('Escola da Lotação');
        $lotacao = Lotacao::query()->create([
            'escola_id' => $escola->id,
            'codigo' => 'LOT-01',
            'nome' => 'Secretaria',
        ]);
        $pessoa = Servidor::query()->create([
            'nome' => 'Pessoa com Dados Funcionais',
            'status' => Servidor::STATUS_ATIVO,
            'carga_horaria' => Pessoa::CARGA_HORARIA_20,
            'jornada' => false,
            'lotacao_id' => $lotacao->id,
        ])->fresh(['lotacao.escola']);

        $this->assertSame(20, $pessoa->carga_horaria);
        $this->assertFalse($pessoa->jornada);
        $this->assertSame('20 horas semanais', $pessoa->cargaHorariaLabel());
        $this->assertSame('Não', $pessoa->jornadaLabel());
        $this->assertSame('LOT-01 - Secretaria', $pessoa->lotacaoLabel());
        $this->assertSame($escola->id, $pessoa->lotacao?->escola?->id);
    }

    public function test_rejeita_carga_invalida_e_jornada_fora_de_20_horas(): void
    {
        $this->assertValidationMessage(
            fn (): bool => Servidor::query()->create([
                'nome' => 'Carga Inválida',
                'status' => Servidor::STATUS_ATIVO,
                'carga_horaria' => 30,
            ])->exists,
            '20 ou 40 horas',
        );

        $this->assertValidationMessage(
            fn (): bool => Servidor::query()->create([
                'nome' => 'Jornada Inválida',
                'status' => Servidor::STATUS_ATIVO,
                'carga_horaria' => Pessoa::CARGA_HORARIA_40,
                'jornada' => true,
            ])->exists,
            'só é permitida',
        );
    }

    public function test_valida_matriculas_conforme_carga_horaria_e_jornada(): void
    {
        PessoaMatricula::assertCompativelComCargaHoraria(40, false, [
            ['matricula' => 'INT-01', 'turno' => 'integral'],
        ]);
        PessoaMatricula::assertCompativelComCargaHoraria(20, false, [
            ['matricula' => 'MANHA-01', 'turno' => 'manha'],
        ]);
        PessoaMatricula::assertCompativelComCargaHoraria(20, true, [
            ['matricula' => 'MANHA-01', 'turno' => 'manha'],
            ['matricula' => 'TARDE-01', 'turno' => 'tarde'],
        ]);

        $this->assertValidationMessage(
            fn () => PessoaMatricula::assertCompativelComCargaHoraria(40, false, [
                ['matricula' => 'MANHA-01', 'turno' => 'manha'],
            ]),
            'turno integral',
        );
        $this->assertValidationMessage(
            fn () => PessoaMatricula::assertCompativelComCargaHoraria(20, true, [
                ['matricula' => 'MESMA', 'turno' => 'manha'],
                ['matricula' => 'MESMA', 'turno' => 'tarde'],
            ]),
            'duas matrículas diferentes',
        );
    }

    public function test_formulario_exige_carga_e_automatiza_matricula_de_jornada_e_lotacao(): void
    {
        Permission::findOrCreate('Criar Pessoas');
        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->assignRole(Role::findOrCreate('Admin', 'web'));
        $usuario->givePermissionTo('Criar Pessoas');

        $escola = $this->criarEscola('Escola Jornada');
        $lotacao = Lotacao::query()->create([
            'escola_id' => $escola->id,
            'codigo' => 'LOT-JOR',
            'nome' => 'Sala de Recursos',
        ]);
        $outraEscola = $this->criarEscola('Escola sem Vínculo');
        $lotacaoSemVinculo = Lotacao::query()->create([
            'escola_id' => $outraEscola->id,
            'codigo' => 'LOT-FORA',
            'nome' => 'Lotação fora do vínculo',
        ]);

        $componente = Livewire::actingAs($usuario)
            ->test(PessoaForm::class, ['pessoaId' => null]);
        $principal = (string) array_key_first($componente->get('matriculas'));

        $componente
            ->set('nome', 'Pessoa sem Carga')
            ->set('email', 'pessoa.sem.carga@edu.umuarama.pr.gov.br')
            ->set("matriculas.{$principal}.matricula", 'MAT-SEM-CARGA')
            ->set("matriculas.{$principal}.turno", 'manha')
            ->call('salvar')
            ->assertHasErrors(['cargaHoraria' => 'required']);

        $componente
            ->call('cargaHorariaAlterada', 20)
            ->call('turnoAlterado', $principal, 'manha')
            ->call('jornadaAlterada', true)
            ->assertCount('matriculas', 2)
            ->assertSet("matriculas.{$principal}.turno", 'manha');

        $chaves = array_keys($componente->get('matriculas'));
        $secundaria = (string) $chaves[1];
        $componente
            ->assertSet("matriculas.{$secundaria}.turno", 'tarde')
            ->call('jornadaAlterada', false)
            ->assertCount('matriculas', 1)
            ->call('cargaHorariaAlterada', 40)
            ->assertSet("matriculas.{$principal}.turno", 'integral')
            ->call('adicionarLotacao', $principal);

        $lotacaoKey = (string) array_key_first(
            $componente->get("matriculas.{$principal}.escolas"),
        );
        $componente
            ->call('escolaAlterada', $principal, $lotacaoKey, $escola->id)
            ->assertSee('LOT-JOR - Sala de Recursos')
            ->call('lotacaoAlterada', $lotacaoSemVinculo->id)
            ->assertHasErrors(['lotacaoId'])
            ->call('lotacaoAlterada', $lotacao->id)
            ->assertSet('lotacaoId', $lotacao->id);
    }

    private function assertValidationMessage(callable $acao, string $mensagem): void
    {
        try {
            $acao();
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                $mensagem,
                collect($exception->errors())->flatten()->implode(' '),
            );

            return;
        }

        $this->fail("Era esperada uma falha de validação contendo: {$mensagem}");
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
