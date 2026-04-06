<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baixas_estoques', function (Blueprint $table) {
            if (! Schema::hasColumn('baixas_estoques', 'descricao')) {
                $table->text('descricao')->nullable()->after('motivo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('baixas_estoques', function (Blueprint $table) {
            if (Schema::hasColumn('baixas_estoques', 'descricao')) {
                $table->dropColumn('descricao');
            }
        });
    }
};
