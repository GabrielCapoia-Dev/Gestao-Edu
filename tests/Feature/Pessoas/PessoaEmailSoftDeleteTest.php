<?php

namespace Tests\Feature\Pessoas;

use App\Models\PessoaMatricula;
use App\Models\Servidor;
use App\Models\User;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PessoaEmailSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_normaliza_email_e_bloqueia_reuso_inclusive_quando_arquivado(): void
    {
        $pessoa = Servidor::query()->create([
            'nome' => 'Pessoa Original',
            'email' => '  PESSOA@EXEMPLO.COM  ',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->assertSame('pessoa@exemplo.com', $pessoa->email);

        $pessoa->delete();

        $this->assertSoftDeleted('servidores', ['id' => $pessoa->id]);
        $this->assertSame(
            'pessoa@exemplo.com',
            Servidor::withTrashed()->whereKey($pessoa->id)->value('email_normalizado'),
        );

        $this->expectException(ValidationException::class);

        Servidor::query()->create([
            'nome' => 'Pessoa Nova',
            'email' => 'pessoa@exemplo.com',
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }

    public function test_duplicado_legado_pode_editar_outro_campo_sem_mudar_identidade(): void
    {
        $agora = now();
        $ids = [];

        foreach (['Legado Um', 'Legado Dois'] as $nome) {
            $ids[] = DB::table('servidores')->insertGetId([
                'nome' => $nome,
                'email' => 'duplicado@exemplo.com',
                'status' => Servidor::STATUS_ATIVO,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        $primeira = Servidor::query()->findOrFail($ids[0]);
        $primeira->update(['nome' => 'Nome corrigido']);

        $this->assertSame(2, Servidor::query()->comEmailDuplicado()->count());
        $this->assertSame('Nome corrigido', $primeira->fresh()->nome);

        $ocupante = Servidor::query()->create([
            'nome' => 'Outra Pessoa',
            'email' => 'ocupado@exemplo.com',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->expectException(ValidationException::class);

        $primeira->update(['email' => $ocupante->email]);
    }

    public function test_arquivamento_preserva_vinculos_e_restauracao_permanece_inativa(): void
    {
        User::factory()->create(); // Reserva o ID protegido do primeiro administrador.
        $user = User::factory()->create([
            'ativo' => true,
            'remember_token' => 'token-anterior',
        ]);
        $pessoa = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => 'Pessoa com Acesso',
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $matricula = PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'MAT-ARQ-1',
            'turno' => 'manha',
        ]);

        $service = app(ServidorService::class);
        $service->arquivarPessoa($pessoa);

        $this->assertSoftDeleted('servidores', ['id' => $pessoa->id]);
        $this->assertDatabaseHas('servidores', [
            'id' => $pessoa->id,
            'user_id' => $user->id,
            'status' => Servidor::STATUS_INATIVO,
        ]);
        $this->assertDatabaseHas('professor_matriculas', [
            'id' => $matricula->id,
            'servidor_id' => $pessoa->id,
        ]);
        $this->assertFalse($user->fresh()->ativo);
        $this->assertNotSame('token-anterior', $user->fresh()->remember_token);

        $restaurada = $service->restaurarPessoa(Servidor::withTrashed()->findOrFail($pessoa->id));

        $this->assertFalse($restaurada->trashed());
        $this->assertSame(Servidor::STATUS_INATIVO, $restaurada->status);
        $this->assertFalse($user->fresh()->ativo);
        $this->assertSame($matricula->id, $restaurada->matriculas()->firstOrFail()->id);
    }

    public function test_pessoa_arquivada_nao_mantem_alerta_de_email_duplicado_na_pessoa_ativa(): void
    {
        $agora = now();
        $ids = [];

        foreach (['Pessoa mantida', 'Pessoa arquivada'] as $nome) {
            $ids[] = DB::table('servidores')->insertGetId([
                'nome' => $nome,
                'email' => 'legado.duplicado@exemplo.com',
                'status' => Servidor::STATUS_INATIVO,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        Servidor::query()->findOrFail($ids[1])->delete();

        $this->assertSame(0, Servidor::query()->comEmailDuplicado()->count());
        $this->assertDatabaseHas('servidores', [
            'id' => $ids[0],
            'deleted_at' => null,
        ]);
    }
}
