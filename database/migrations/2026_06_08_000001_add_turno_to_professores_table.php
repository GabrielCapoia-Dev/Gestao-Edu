<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professores', function (Blueprint $table): void {
            if (! Schema::hasColumn('professores', 'turno')) {
                $table->enum('turno', ['manha', 'tarde', 'integral'])
                    ->nullable()
                    ->after('matricula');
            }
        });
    }

    public function down(): void
    {
        Schema::table('professores', function (Blueprint $table): void {
            if (Schema::hasColumn('professores', 'turno')) {
                $table->dropColumn('turno');
            }
        });
    }
};
