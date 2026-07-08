<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
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

    public function test_aba_professores_filtra_servidores_com_funcao_pedagogica(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Hub', $setor);

        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();
        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Apoio Administrativo',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
        ]);

        $comProfessor = $this->criarServidor('Com função professor', $escola, $setor);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $comProfessor->id,
            'funcao_administrativa_id' => $funcaoProfessor->id,
            'matricula' => 'PROF-HUB',
            'setor_id' => $setor->id,
            'id_escola' => $escola->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        $semProfessor = $this->criarServidor('Sem função professor', $escola, $setor);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $semProfessor->id,
            'funcao_administrativa_id' => $funcaoAuxiliar->id,
            'matricula' => 'AUX-HUB',
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->set('activeTab', 'professores')
            ->assertCanSeeTableRecords([$comProfessor])
            ->assertCanNotSeeTableRecords([$semProfessor]);
    }

    public function test_aba_usuarios_filtra_servidores_com_acesso_ao_sistema(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Acesso');
        $escola = $this->criarEscola('Escola Acesso', $setor);

        $userAcesso = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $comUsuario = $this->criarServidor('Com usuário', $escola, $setor, $userAcesso->id);
        $semUsuario = $this->criarServidor('Sem usuário', $escola, $setor);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->set('activeTab', 'usuarios')
            ->assertCanSeeTableRecords([$comUsuario])
            ->assertCanNotSeeTableRecords([$semUsuario]);
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