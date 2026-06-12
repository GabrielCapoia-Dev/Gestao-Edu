<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update([
            'google_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_in' => null,
        ]);
    }

    public function down(): void
    {
        // Tokens removidos por seguranca nao podem ser restaurados.
    }
};
