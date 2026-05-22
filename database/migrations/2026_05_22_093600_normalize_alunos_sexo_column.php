<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alunos') || ! Schema::hasColumn('alunos', 'sexo')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `alunos` MODIFY `sexo` VARCHAR(16) NULL');
    }

    public function down(): void
    {
        //
    }
};
