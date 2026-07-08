<?php

use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            if (! Schema::hasColumn('funcao_administrativa', 'concede_acesso_sistema')) {
                $table->boolean('concede_acesso_sistema')->default(false)->after('exige_professor');
            }
        });

        if (! Schema::hasTable('funcao_administrativa_role')) {
            Schema::create('funcao_administrativa_role', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('funcao_administrativa_id')
                    ->constrained('funcao_administrativa')
                    ->cascadeOnDelete();
                $table->foreignId('role_id')
                    ->constrained('roles')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['funcao_administrativa_id', 'role_id'], 'funcao_role_unique');
            });
        }

        $this->configurarCargoProfessor();
    }

    public function down(): void
    {
        Schema::dropIfExists('funcao_administrativa_role');

        Schema::table('funcao_administrativa', function (Blueprint $table): void {
            if (Schema::hasColumn('funcao_administrativa', 'concede_acesso_sistema')) {
                $table->dropColumn('concede_acesso_sistema');
            }
        });
    }

    private function configurarCargoProfessor(): void
    {
        if (! Schema::hasTable('funcao_administrativa')) {
            return;
        }

        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->update(['concede_acesso_sistema' => true]);

        $roleProfessor = Role::query()->where('name', 'Professor')->first();

        if (! $roleProfessor) {
            return;
        }

        $exists = DB::table('funcao_administrativa_role')
            ->where('funcao_administrativa_id', $funcao->id)
            ->where('role_id', $roleProfessor->id)
            ->exists();

        if (! $exists) {
            DB::table('funcao_administrativa_role')->insert([
                'funcao_administrativa_id' => $funcao->id,
                'role_id' => $roleProfessor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};