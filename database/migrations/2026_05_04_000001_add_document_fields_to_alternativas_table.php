<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alternativas', function (Blueprint $table): void {
            $table->boolean('vai_no_documento')
                ->default(true)
                ->after('observacao');

            $table->text('descricao_documento')
                ->nullable()
                ->after('vai_no_documento');
        });
    }

    public function down(): void
    {
        Schema::table('alternativas', function (Blueprint $table): void {
            $table->dropColumn(['vai_no_documento', 'descricao_documento']);
        });
    }
};
