<?php

namespace Tests\Feature\Pessoas;

use App\Services\PessoaSalvarExceptionService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class PessoaSalvarExceptionServiceTest extends TestCase
{
    public function test_mapeia_conflito_de_email_sem_expor_sql(): void
    {
        $anterior = new PDOException("Duplicate entry 'pessoa@teste' for key 'users_email_unique'");
        $anterior->errorInfo = ['23000', 1062, 'duplicate'];
        $query = new QueryException('mysql', 'update users set email = ?', ['pessoa@teste'], $anterior);

        $validacao = app(PessoaSalvarExceptionService::class)->mapear($query);

        $this->assertInstanceOf(ValidationException::class, $validacao);
        $mensagem = collect($validacao->errors())->flatten()->implode(' ');
        $this->assertStringContainsString('outra conta de usuário', $mensagem);
        $this->assertStringNotContainsString('update users', $mensagem);
        $this->assertStringNotContainsString('SQLSTATE', $mensagem);
    }

    public function test_mapeia_conflito_de_conta_e_gera_protocolo_para_erro_inesperado(): void
    {
        $service = app(PessoaSalvarExceptionService::class);
        $validacao = $service->mapear(new LogicException('A conta já está vinculada a outra Pessoa.'));

        $this->assertInstanceOf(ValidationException::class, $validacao);
        $this->assertArrayHasKey('email', $validacao->errors());

        $protocolo = $service->registrarInesperada(new RuntimeException('Falha de teste'), 10, 20);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{8}$/', $protocolo);
    }

    public function test_mapeia_conflitos_de_identidade_para_os_campos_corretos_sem_sql(): void
    {
        $casos = [
            ['servidores_cpf_unique', 'cpf'],
            ['uq_servidores_email_normalizado', 'email'],
            ['uniq_professor_matriculas_servidor_matricula', 'matriculas'],
            ['professores_id_escola_matricula_unique', 'matriculas'],
        ];

        foreach ($casos as [$indice, $campo]) {
            $anterior = new PDOException("Duplicate entry 'valor' for key '{$indice}'");
            $anterior->errorInfo = ['23000', 1062, 'duplicate'];
            $query = new QueryException('mysql', 'insert into tabela values (?)', ['valor'], $anterior);

            $validacao = app(PessoaSalvarExceptionService::class)->mapear($query);

            $this->assertInstanceOf(ValidationException::class, $validacao);
            $this->assertArrayHasKey($campo, $validacao->errors());
            $mensagem = collect($validacao->errors())->flatten()->implode(' ');
            $this->assertStringNotContainsString('insert into', $mensagem);
            $this->assertStringNotContainsString('SQLSTATE', $mensagem);
        }
    }
}
