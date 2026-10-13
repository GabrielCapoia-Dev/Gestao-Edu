<?php

namespace Tests\Feature\Policies;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
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

    public function test_centralizacao_permite_apenas_policies(): void
    {
        $violations = [];

        foreach (File::allFiles(app_path()) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if (str_starts_with($relativePath, 'Policies/') || $relativePath === 'Models/User.php') {
                continue;
            }

            if (str_contains($file->getContents(), 'hasPermissionTo')) {
                $violations[] = $relativePath;
            }
        }

        $this->assertSame([], $violations, 'hasPermissionTo encontrado fora de Policies/ e Models/User.php: '.implode(', ', $violations));
    }
}
