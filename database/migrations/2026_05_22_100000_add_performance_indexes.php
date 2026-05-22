<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // alunos.nome: usado como defaultSort e searchable na tabela de alunos
        if (! Schema::hasIndex('alunos', 'alunos_nome_index')) {
            Schema::table('alunos', function (Blueprint $table): void {
                $table->index('nome', 'alunos_nome_index');
            });
        }

        // turma_componente_professor.professor_id: usado no filtro de escopo
        // do professor (whereIn professor_id), que atualmente só tem um
        // unique composto por [turma_id, componente_curricular_id]
        if (! Schema::hasIndex('turma_componente_professor', 'turma_componente_professor_professor_id_index')) {
            Schema::table('turma_componente_professor', function (Blueprint $table): void {
                $table->index('professor_id', 'turma_componente_professor_professor_id_index');
            });
        }

        // export_requests.created_at: usado como defaultSort desc na tela
        // minhas-exportacoes
        if (! Schema::hasIndex('export_requests', 'export_requests_created_at_index')) {
            Schema::table('export_requests', function (Blueprint $table): void {
                $table->index('created_at', 'export_requests_created_at_index');
            });
        }

        // professores.id_escola: usado em filtros de escola no formulario de turmas
        if (! Schema::hasIndex('professores', 'professores_id_escola_index')) {
            Schema::table('professores', function (Blueprint $table): void {
                $table->index('id_escola', 'professores_id_escola_index');
            });
        }

        // users.name: usado como defaultSort em listagens de usuarios online e offline
        if (! Schema::hasIndex('users', 'users_name_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('name', 'users_name_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table): void {
            $table->dropIndex('alunos_nome_index');
        });

        Schema::table('turma_componente_professor', function (Blueprint $table): void {
            $table->dropIndex('turma_componente_professor_professor_id_index');
        });

        Schema::table('export_requests', function (Blueprint $table): void {
            $table->dropIndex('export_requests_created_at_index');
        });

        Schema::table('professores', function (Blueprint $table): void {
            $table->dropIndex('professores_id_escola_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_name_index');
        });
    }
};
