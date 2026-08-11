<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\PessoaLegadoNormalizacaoService;
use Database\Seeders\PessoaLegadoNormalizacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PessoaLegadoNormalizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_sem_servidor_gera_pessoa_e_matricula(): void
    {
        $this->seedCargo();
        $escola = $this->criarEscola('Escola A');

        $professor = Professor::withoutEvents(fn () => Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'MAT-100',
            'turno' => 'manha',
            'nome' => 'Ana Legado',
            'email' => 'ana.legado@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        $this->assertNull($professor->fresh()->servidor_id);

        $stats = app(PessoaLegadoNormalizacaoService::class)->normalizar();

        $professor->refresh();
        $this->assertNotNull($professor->servidor_id);
        $this->assertNotNull($professor->professor_matricula_id);
        $this->assertDatabaseHas('servidores', [
            'id' => $professor->servidor_id,
            'email' => 'ana.legado@edu.umuarama.pr.gov.br',
        ]);
        $this->assertGreaterThanOrEqual(1, $stats['pessoas_criadas']);
    }

    public function test_mesmo_email_sem_identificador_forte_nao_mescla_professores(): void
    {
        $this->seedCargo();
        $escolaA = $this->criarEscola('Escola Norte');
        $escolaB = $this->criarEscola('Escola Sul');

        $p1 = Professor::withoutEvents(fn () => Professor::query()->create([
            'id_escola' => $escolaA->id,
            'matricula' => 'MAT-SHARED',
            'turno' => 'manha',
            'nome' => 'Bruno Multi',
            'email' => 'bruno.multi@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));
        $p2 = Professor::withoutEvents(fn () => Professor::query()->create([
            'id_escola' => $escolaB->id,
            'matricula' => 'MAT-SHARED',
            'turno' => 'manha',
            'nome' => 'Bruno Multi',
            'email' => 'bruno.multi@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        $stats = app(PessoaLegadoNormalizacaoService::class)->normalizar();

        $p1->refresh();
        $p2->refresh();

        $this->assertNotNull($p1->servidor_id);
        $this->assertNull($p2->servidor_id);
        $this->assertSame(1, Servidor::query()->where('email', 'bruno.multi@edu.umuarama.pr.gov.br')->count());
        $this->assertSame(1, ProfessorMatricula::query()->where('servidor_id', $p1->servidor_id)->count());
        $this->assertCount(1, Professor::query()->where('servidor_id', $p1->servidor_id)->get());
        $this->assertNotEmpty($stats['anomalias']);
    }

    public function test_mesmo_email_duas_matriculas_cria_duas_professor_matriculas(): void
    {
        $this->seedCargo();
        $escola = $this->criarEscola('Escola Dupla');
        $user = User::factory()->create([
            'email' => 'carla.dupla@edu.umuarama.pr.gov.br',
        ]);

        Professor::withoutEvents(fn () => Professor::query()->create([
            'user_id' => $user->id,
            'id_escola' => $escola->id,
            'matricula' => 'MAT-MANHA',
            'turno' => 'manha',
            'nome' => 'Carla Dupla',
            'email' => 'carla.dupla@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));
        Professor::withoutEvents(fn () => Professor::query()->create([
            'user_id' => $user->id,
            'id_escola' => $escola->id,
            'matricula' => 'MAT-TARDE',
            'turno' => 'tarde',
            'nome' => 'Carla Dupla',
            'email' => 'carla.dupla@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        app(PessoaLegadoNormalizacaoService::class)->normalizar();

        $servidor = Servidor::query()->where('email', 'carla.dupla@edu.umuarama.pr.gov.br')->first();
        $this->assertNotNull($servidor);
        $this->assertSame(2, ProfessorMatricula::query()->where('servidor_id', $servidor->id)->count());
        $this->assertEqualsCanonicalizing(
            ['MAT-MANHA', 'MAT-TARDE'],
            ProfessorMatricula::query()->where('servidor_id', $servidor->id)->pluck('matricula')->all(),
        );
    }

    public function test_apenas_audita_servidores_duplicados_do_mesmo_email(): void
    {
        $this->seedCargo();
        $escolaA = $this->criarEscola('Escola TCP A');
        $escolaB = $this->criarEscola('Escola TCP B');

        $user = User::factory()->create([
            'email' => 'tcp.prof@edu.umuarama.pr.gov.br',
            'email_approved' => true,
        ]);

        $s1 = Servidor::query()->create([
            'nome' => 'TCP Um',
            'email' => 'tcp.prof@edu.umuarama.pr.gov.br',
            'user_id' => $user->id,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $s2Id = DB::table('servidores')->insertGetId([
            'nome' => 'TCP Dois',
            'email' => 'tcp.prof@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $s2 = Servidor::query()->findOrFail($s2Id);

        $p1 = Professor::withoutEvents(fn () => Professor::query()->create([
            'servidor_id' => $s1->id,
            'user_id' => $user->id,
            'id_escola' => $escolaA->id,
            'matricula' => 'TCP-1',
            'turno' => 'integral',
            'nome' => 'TCP Um',
            'email' => 'tcp.prof@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));
        $p2 = Professor::withoutEvents(fn () => Professor::query()->create([
            'servidor_id' => $s2->id,
            'id_escola' => $escolaB->id,
            'matricula' => 'TCP-1',
            'turno' => 'integral',
            'nome' => 'TCP Dois',
            'email' => 'tcp.prof@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        $stats = app(PessoaLegadoNormalizacaoService::class)->normalizar();

        $this->assertSame(2, Servidor::query()->where('email', 'tcp.prof@edu.umuarama.pr.gov.br')->count());
        $this->assertDatabaseHas('servidores', ['id' => $s2->id]);
        $this->assertNotSame((int) $p1->fresh()->servidor_id, (int) $p2->fresh()->servidor_id);
        $this->assertSame(2, ProfessorMatricula::query()->where('matricula', 'TCP-1')->count());
        $this->assertSame(1, $stats['grupos_email']);
        $this->assertNotEmpty($stats['anomalias']);
    }

    public function test_rerun_e_noop_quando_normalizado(): void
    {
        $this->seedCargo();
        $escola = $this->criarEscola('Escola Noop');

        Professor::withoutEvents(fn () => Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'NOOP-1',
            'turno' => 'tarde',
            'nome' => 'Noop User',
            'email' => 'noop.user@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        $service = app(PessoaLegadoNormalizacaoService::class);
        $service->normalizar();

        $this->assertFalse($service->haPendencias());
        $segunda = $service->normalizar();
        $this->assertSame(1, $segunda['early_exit']);
    }

    public function test_seeder_executa_sem_erro(): void
    {
        $this->seedCargo();
        $escola = $this->criarEscola('Escola Seed');

        Professor::withoutEvents(fn () => Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'SEED-1',
            'turno' => 'manha',
            'nome' => 'Seed Prof',
            'email' => 'seed.prof@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        $this->seed(PessoaLegadoNormalizacaoSeeder::class);

        $this->assertDatabaseHas('servidores', [
            'email' => 'seed.prof@edu.umuarama.pr.gov.br',
        ]);
    }

    public function test_seeder_materializa_matricula_legada_sem_exigir_acesso_para_pessoa_sem_email(): void
    {
        $this->seedCargo();
        $escola = $this->criarEscola('Escola Legado Sem Email');
        $servidorId = DB::table('servidores')->insertGetId([
            'nome' => 'Professor Legado Sem Email',
            'status' => Servidor::STATUS_ATIVO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $professor = Professor::withoutEvents(fn () => Professor::query()->create([
            'servidor_id' => $servidorId,
            'id_escola' => $escola->id,
            'matricula' => 'LEGADO-SEM-EMAIL',
            'turno' => 'manha',
            'nome' => 'Professor Legado Sem Email',
            'email' => 'professor.legado@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]));

        $this->seed(PessoaLegadoNormalizacaoSeeder::class);

        $this->assertNotNull($professor->fresh()->professor_matricula_id);
        $this->assertNull(Servidor::query()->findOrFail($servidorId)->user_id);
    }

    private function seedCargo(): void
    {
        Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->update(['concede_acesso_sistema' => true, 'exige_professor' => true]);
    }

    private function criarEscola(string $nome): Escola
    {
        $setor = Setor::query()->create([
            'nome' => 'Pedagógico '.$nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);

        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
