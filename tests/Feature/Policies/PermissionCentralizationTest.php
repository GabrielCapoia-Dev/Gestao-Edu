<?php

namespace Tests\Feature\Policies;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCentralizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_aluno_policy_delete_bulk_exige_permissao(): void
    {
        Permission::findOrCreate('Excluir Alunos em Massa');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('deleteBulk', Aluno::class));

        $user->givePermissionTo('Excluir Alunos em Massa');

        $this->assertTrue(Gate::forUser($user)->allows('deleteBulk', Aluno::class));
    }

    public function test_script_centralizacao_permite_apenas_policies(): void
    {
        $script = base_path('scripts/check-permission-centralization.sh');

        $this->assertFileExists($script);

        $output = [];
        $exitCode = 0;
        exec('bash ' . escapeshellarg($script), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertStringContainsString('OK:', implode("\n", $output));
    }
}