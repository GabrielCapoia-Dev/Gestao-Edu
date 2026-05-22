<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table): void {
            if (! Schema::hasColumn('alunos', 'sexo')) {
                $table->string('sexo', 16)->nullable()->after('data_nascimento');
            }

            if (! Schema::hasColumn('alunos', 'data_matricula')) {
                $table->date('data_matricula')->nullable()->after('sexo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table): void {
            if (Schema::hasColumn('alunos', 'data_matricula')) {
                $table->dropColumn('data_matricula');
            }
        });
    }
};
