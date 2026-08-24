<?php

namespace Tests\Feature\Pessoas;

use App\Livewire\Pessoas\PessoaForm;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Lotacao;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\PessoaProfessorService;
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

    public function test_carga_e_jornada_pertencem_a_cada_matricula(): void
    {
        PessoaMatricula::assertConjuntoFuncionalValido([
            ['matricula' => 'INT-01', 'turno' => 'integral', 'carga_horaria' => 40, 'jornada' => false],
        ]);
        PessoaMatricula::assertConjuntoFuncionalValido([
            ['matricula' => 'MANHA-01', 'turno' => 'manha', 'carga_horaria' => 20, 'jornada' => false],
            ['matricula' => 'TARDE-01', 'turno' => 'tarde', 'carga_horaria' => 20, 'jornada' => false],
        ]);
        PessoaMatricula::assertConjuntoFuncionalValido([
            ['matricula' => 'MANHA-01', 'turno' => 'manha', 'carga_horaria' => 20, 'jornada' => false],
            ['matricula' => 'JOR-01', 'turno' => 'tarde', 'carga_horaria' => 20, 'jornada' => true],
        ]);

        $this->assertValidationMessage(
            fn () => PessoaMatricula::assertConjuntoFuncionalValido([
                ['matricula' => 'INT-01', 'turno' => 'integral', 'carga_horaria' => 40, 'jornada' => false],
                ['matricula' => 'TARDE-01', 'turno' => 'tarde', 'carga_horaria' => 20, 'jornada' => false],
            ]),
            'Matrícula integral',
        );
        $this->assertValidationMessage(
            fn () => PessoaMatricula::assertConjuntoFuncionalValido([
                ['matricula' => 'JOR-SEM-BASE', 'turno' => 'manha', 'carga_horaria' => 20, 'jornada' => true],
            ]),
            'matrícula comum',
        );
    }

    public function test_formulario_distingue_segundo_concurso_de_jornada_e_lotacao(): void
    {
        Permission::findOrCreate('Criar Pessoas');
        $usuario = User::factory()->create([
            'email' => 'admin.dados.funcionais@edu.umuarama.pr.gov.br',
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->assignRole(Role::findOrCreate('Admin', 'web'));
        $usuario->givePermissionTo('Criar Pessoas');
        $pessoaAdmin = Servidor::query()->create([
            'user_id' => $usuario->id,
            'nome' => $usuario->name,
            'email' => $usuario->email,
            'status' => Pessoa::STATUS_ATIVO,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaAdmin->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::professorPadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);
        $this->actingAs($usuario);

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

        $componente = Livewire::test(PessoaForm::class, ['pessoaId' => null]);
        $componente->assertStatus(200);
        $principal = (string) array_key_first($componente->get('matriculas'));

        $componente
            ->set("matriculas.{$principal}.matricula", 'CONCURSO-MANHA')
            ->call('turnoAlterado', $principal, 'manha')
            ->call('adicionarMatricula')
            ->assertCount('matriculas', 2)
            ->assertSet("matriculas.{$principal}.turno", 'manha');

        $chaves = array_keys($componente->get('matriculas'));
        $secundaria = (string) $chaves[1];
        $componente
            ->assertSet("matriculas.{$secundaria}.turno", 'tarde')
            ->assertSet("matriculas.{$secundaria}.jornada", false)
            ->call('removerMatricula', $secundaria)
            ->assertCount('matriculas', 1)
            ->call('adicionarJornada')
            ->assertCount('matriculas', 2);

        $jornadaKey = (string) collect($componente->get('matriculas'))
            ->search(fn (array $item): bool => (bool) $item['jornada']);
        $componente
            ->assertSet("matriculas.{$jornadaKey}.turno", 'tarde')
            ->assertSet("matriculas.{$jornadaKey}.jornada", true)
            ->call('removerMatricula', $jornadaKey)
            ->call('turnoAlterado', $principal, 'integral')
            ->assertSet("matriculas.{$principal}.carga_horaria", 40)
            ->call('adicionarLotacao', $principal);

        $lotacaoKey = (string) array_key_first(
            $componente->get("matriculas.{$principal}.escolas"),
        );
        $componente
            ->call('escolaAlterada', $principal, $lotacaoKey, $escola->id)
            ->assertSee('LOT-JOR - Sala de Recursos')
            ->assertDontSee('LOT-FORA - Lotação fora do vínculo')
            ->call('lotacaoAlterada', $lotacaoSemVinculo->id)
            ->assertHasErrors(['lotacaoId'])
            ->call('lotacaoAlterada', $lotacao->id)
            ->assertSet('lotacaoId', $lotacao->id);
    }

    public function test_matricula_de_jornada_e_arquivada_e_reativada_com_o_mesmo_id(): void
    {
        $pessoa = Servidor::query()->create([
            'nome' => 'Pessoa com Jornada',
            'status' => Servidor::STATUS_ATIVO,
            'carga_horaria' => 20,
            'jornada' => true,
        ]);
        $service = app(PessoaProfessorService::class);
        $comum = ['matricula' => 'CONCURSO-01', 'turno' => 'manha', 'jornada' => false, 'escolas' => []];
        $jornada = ['matricula' => 'JORNADA-01', 'turno' => 'tarde', 'jornada' => true, 'escolas' => []];

        $service->sincronizarRegistros($pessoa, [$comum, $jornada]);
        $jornadaId = ProfessorMatricula::query()->where('jornada', true)->value('id');

        $service->sincronizarRegistros($pessoa, [$comum]);

        $this->assertNotNull($jornadaId);
        $this->assertDatabaseHas('professor_matriculas', [
            'id' => $jornadaId,
            'jornada' => true,
        ]);
        $this->assertNotNull(ProfessorMatricula::onlyTrashed()->find($jornadaId));

        $service->sincronizarRegistros($pessoa, [$comum, $jornada + ['id' => $jornadaId]]);

        $reativada = ProfessorMatricula::query()->findOrFail($jornadaId);
        $this->assertSame('JORNADA-01', $reativada->matricula);
        $this->assertTrue($reativada->jornada);
        $this->assertSame(20, $reativada->carga_horaria);
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
