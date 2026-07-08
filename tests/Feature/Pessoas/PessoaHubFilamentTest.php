<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
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

    public function test_hub_possui_abas_todos_professores_e_usuarios(): void
    {
        $usuario = $this->usuarioComPermissaoListar();

        $tabs = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->instance()
            ->getTabs();

        $this->assertArrayHasKey('todos', $tabs);
        $this->assertArrayHasKey('professores', $tabs);
        $this->assertArrayHasKey('usuarios', $tabs);
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

    public function test_aba_usuarios_lista_registros_de_user_com_colunas_de_acesso(): void
    {
        Permission::findOrCreate('Listar Usuarios');

        $admin = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $admin->givePermissionTo(['Listar Servidores', 'Listar Usuarios']);

        $userAlvo = User::factory()->create([
            'name' => 'Usuario Hub Teste',
            'email' => 'hub.teste@edu.umuarama.pr.gov.br',
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ManageServidores::class)
            ->set('activeTab', 'usuarios')
            ->assertCanSeeTableRecords([$userAlvo]);
    }

    private function usuarioComPermissaoListar(): User
    {
        Permission::findOrCreate('Listar Servidores');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Servidores');

        return $usuario;
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