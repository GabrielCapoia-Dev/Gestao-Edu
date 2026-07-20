<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicos_alvo', function (Blueprint $table): void {
            $table->id();
            $table->string('modo_correspondencia', 16)->default('qualquer');
            $table->boolean('todos_usuarios')->default(false);
            $table->boolean('escopo_global')->default(false);
            $table->timestamps();

            $table->index(
                ['todos_usuarios', 'modo_correspondencia', 'escopo_global'],
                'publicos_alvo_matching_index',
            );
        });

        $this->createPivot('publico_alvo_user', 'user_id', 'users', 'pa_user');
        $this->createPivot('publico_alvo_role', 'role_id', 'roles', 'pa_role');
        $this->createPivot('publico_alvo_permission', 'permission_id', 'permissions', 'pa_permission');
        $this->createPivot(
            'publico_alvo_funcao_administrativa',
            'funcao_administrativa_id',
            'funcao_administrativa',
            'pa_funcao',
        );
        $this->createPivot('publico_alvo_escola', 'escola_id', 'escolas', 'pa_escola');
        $this->createPivot('publico_alvo_setor', 'setor_id', 'setor', 'pa_setor');
        $this->createPivot('publico_alvo_escopo_escola', 'escola_id', 'escolas', 'pa_scope_escola');
        $this->createPivot('publico_alvo_escopo_setor', 'setor_id', 'setor', 'pa_scope_setor');
    }

    public function down(): void
    {
        Schema::dropIfExists('publico_alvo_escopo_setor');
        Schema::dropIfExists('publico_alvo_escopo_escola');
        Schema::dropIfExists('publico_alvo_setor');
        Schema::dropIfExists('publico_alvo_escola');
        Schema::dropIfExists('publico_alvo_funcao_administrativa');
        Schema::dropIfExists('publico_alvo_permission');
        Schema::dropIfExists('publico_alvo_role');
        Schema::dropIfExists('publico_alvo_user');
        Schema::dropIfExists('publicos_alvo');
    }

    private function createPivot(
        string $tableName,
        string $targetColumn,
        string $targetTable,
        string $constraintPrefix,
    ): void {
        Schema::create($tableName, function (Blueprint $table) use (
            $targetColumn,
            $targetTable,
            $constraintPrefix,
        ): void {
            $table->foreignId('publico_alvo_id');
            $table->foreignId($targetColumn);

            $table->primary(
                ['publico_alvo_id', $targetColumn],
                $constraintPrefix.'_primary',
            );
            $table->index(
                [$targetColumn, 'publico_alvo_id'],
                $constraintPrefix.'_reverse_index',
            );

            $table->foreign('publico_alvo_id', $constraintPrefix.'_publico_fk')
                ->references('id')
                ->on('publicos_alvo')
                ->cascadeOnDelete();
            $table->foreign($targetColumn, $constraintPrefix.'_target_fk')
                ->references('id')
                ->on($targetTable)
                ->cascadeOnDelete();
        });
    }
};
