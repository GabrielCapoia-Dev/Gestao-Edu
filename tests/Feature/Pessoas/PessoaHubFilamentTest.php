<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\ServidorService;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PessoaHubFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_resource_exibe_entrada_pessoas_no_menu(): void
    {
        $this->assertSame('Pessoas', ServidorResource::getNavigationLabel());
        $this->assertSame('Pessoa', ServidorResource::getModelLabel());
    }

    public function test_hub_lista_unica_sem_metodo_de_abas_proprio(): void
    {
        $reflection = new \ReflectionClass(ManageServidores::class);

        $this->assertFalse(
            $reflection->hasMethod('getTabs')
                && $reflection->getMethod('getTabs')->getDeclaringClass()->getName() === ManageServidores::class,
            'ManageServidores não deve declarar abas próprias.',
        );
    }

    public function test_lista_exibe_todas_as_pessoas_sem_filtro_de_aba(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Hub', $setor);

        $comProfessor = $this->criarServidor('Com registro professor', $escola, $setor);
        Professor::query()->create([
            'servidor_id' => $comProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-HUB',
            'turno' => 'manha',
            'nome' => $comProfessor->nome,
            'email' => 'prof.hub@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        $semProfessor = $this->criarServidor('Sem registro professor', $escola, $setor);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->assertCanSeeTableRecords([$comProfessor, $semProfessor]);
    }

    public function test_header_nova_pessoa(): void
    {
        $usuario = $this->usuarioComPermissaoListar();

        $actions = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->instance()
            ->getCachedHeaderActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(CreateAction::class, $actions[0]);
        $this->assertSame('Nova pessoa', $actions[0]->getLabel());
    }

    public function test_create_slideover_exibe_campos_de_servidor_e_acesso(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Servidores', 'Criar Servidores']);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->mountAction('create')
            ->assertSchemaComponentExists('nome')
            ->assertSchemaComponentExists('matriculas_professor')
            ->assertSchemaComponentExists('email_approved')
            ->assertSchemaComponentDoesNotExist('password');
    }

    public function test_edit_servidor_abre_slideover_com_registros(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Servidores', 'Editar Servidores']);

        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Edit', $setor);
        $servidor = $this->criarServidor('Servidor Editável', $escola, $setor);

        Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'EDIT-001',
            'turno' => 'tarde',
            'nome' => $servidor->nome,
            'email' => 'edit@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->mountTableAction('edit', $servidor)
            ->assertSchemaComponentExists('matriculas_professor')
            ->assertSchemaComponentExists('email_approved')
            ->assertSchemaStateSet([
                'nome' => 'Servidor Editável',
                'cargo' => ServidorResource::CARGO_PROFESSOR,
            ]);
    }

    public function test_exclui_pessoa_sem_avaliacoes_e_limpa_lotacoes(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Servidores', 'Excluir Servidores']);
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Del', $setor);
        $servidor = $this->criarServidor('Para Excluir', $escola, $setor);

        $professor = Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'DEL-001',
            'turno' => 'manha',
            'nome' => $servidor->nome,
            'email' => 'del@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        app(ServidorService::class)->excluirPessoa($servidor->fresh());

        $this->assertDatabaseMissing('servidores', ['id' => $servidor->id]);
        $this->assertDatabaseMissing('professores', ['id' => $professor->id]);
    }

    private function usuarioComPermissaoListar(): User
    {
        return $this->usuarioHubAdmin(['Listar Servidores']);
    }

    /** @param array<int, string> $permissoes */
    private function usuarioHubAdmin(array $permissoes): User
    {
        $permissoesCriadas = collect($permissoes)
            ->map(fn (string $permissao): Permission => $this->garantirPermissao($permissao))
            ->all();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->syncPermissions($permissoesCriadas);

        // Escopo global para listar/editar pessoas no hub de testes.
        $roleAdmin = Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $usuario->assignRole($roleAdmin);

        return $usuario;
    }

    private function garantirPermissao(string $nome): Permission
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Permission::query()->firstOrCreate([
            'name' => $nome,
            'guard_name' => 'web',
        ]);
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }

    private function criarServidor(string $nome, Escola $escola, Setor $setor, ?int $userId = null): Servidor
    {
        return Servidor::query()->create([
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@edu.umuarama.pr.gov.br',
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'user_id' => $userId,
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }
}
