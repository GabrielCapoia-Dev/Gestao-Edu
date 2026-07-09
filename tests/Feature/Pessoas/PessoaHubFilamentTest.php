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

    public function test_hub_possui_abas_de_filtro_unificadas(): void
    {
        $usuario = $this->usuarioComPermissaoListar();

        $tabs = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->instance()
            ->getTabs();

        $this->assertArrayHasKey('todos', $tabs);
        $this->assertArrayHasKey('professores', $tabs);
        $this->assertArrayHasKey('com_acesso', $tabs);
        $this->assertArrayHasKey('sem_acesso', $tabs);
        $this->assertArrayNotHasKey('usuarios', $tabs);
    }

    public function test_aba_legada_usuarios_redireciona_para_com_acesso(): void
    {
        $usuario = $this->usuarioComPermissaoListar();

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class, ['activeTab' => 'usuarios'])
            ->assertSet('activeTab', 'com_acesso');
    }

    public function test_aba_professores_filtra_servidores_com_registros_pedagogicos(): void
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
            ->set('activeTab', 'professores')
            ->assertCanSeeTableRecords([$comProfessor])
            ->assertCanNotSeeTableRecords([$semProfessor]);
    }

    public function test_aba_com_acesso_filtra_servidores_com_usuario_vinculado(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Acesso', $setor);

        $userVinculado = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $comAcesso = $this->criarServidor('Com acesso', $escola, $setor, $userVinculado->id);
        $semAcesso = $this->criarServidor('Sem acesso', $escola, $setor);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->set('activeTab', 'com_acesso')
            ->assertCanSeeTableRecords([$comAcesso])
            ->assertCanNotSeeTableRecords([$semAcesso]);
    }

    public function test_header_nova_pessoa_em_todas_as_abas(): void
    {
        $usuario = $this->usuarioComPermissaoListar();

        foreach (['todos', 'professores', 'com_acesso', 'sem_acesso'] as $aba) {
            $actions = Livewire::actingAs($usuario)
                ->test(ManageServidores::class)
                ->set('activeTab', $aba)
                ->instance()
                ->getCachedHeaderActions();

            $this->assertCount(1, $actions, "A aba {$aba} deve ter um único botão de criação.");
            $this->assertInstanceOf(CreateAction::class, $actions[0]);
            $this->assertSame('Nova pessoa', $actions[0]->getLabel());
        }
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

        $adminRole = Role::findOrCreate('Admin', 'web');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->assignRole($adminRole);
        $usuario->syncPermissions($permissoesCriadas);

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

    private function criarServidor(string $nome, Escola $escola, Setor $setor, ?int $userId = null): Servidor
    {
        return Servidor::query()->create([
            'user_id' => $userId,
            'nome' => $nome,
            'matricula' => strtoupper(substr(md5($nome), 0, 8)),
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'central',
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
}