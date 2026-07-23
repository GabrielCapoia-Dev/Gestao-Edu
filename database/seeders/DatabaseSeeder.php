<?php

namespace Database\Seeders;

use App\Models\DominioEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $secretarioRole = Role::firstOrCreate([
            'name' => 'Secretário',
            'guard_name' => 'web',
        ]);

        $administrativoRole = Role::firstOrCreate([
            'name' => 'Administrativo',
            'guard_name' => 'web',
        ]);

        Artisan::call('permissoes:criar');

        if ($this->command) {
            $this->command->info(Artisan::output());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (app()->environment('local')) {
            $password = Str::password(20);

            $adminUser = User::firstOrCreate(
                ['email' => 'admin@admin.com'],
                [
                    'codigo' => 100,
                    'name' => 'Admin',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'email_approved' => true,
                    'must_change_password' => true,
                ]
            );

            $secretarioUser = User::firstOrCreate(
                ['email' => 'secretario@secretario.com'],
                [
                    'codigo' => 101,
                    'name' => 'Secretário',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'email_approved' => true,
                    'must_change_password' => true,
                ]
            );

            $administrativoUser = User::firstOrCreate(
                ['email' => 'administrativo@administrativo.com'],
                [
                    'codigo' => 102,
                    'name' => 'Administrativo',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'email_approved' => true,
                    'must_change_password' => true,
                ]
            );

            $adminUser->syncRoles([$adminRole]);
            $secretarioUser->syncRoles([$secretarioRole]);
            $administrativoUser->syncRoles([$administrativoRole]);

            if (
                $this->command
                && ($adminUser->wasRecentlyCreated || $secretarioUser->wasRecentlyCreated || $administrativoUser->wasRecentlyCreated)
            ) {
                $this->command->warn('Novas contas locais criadas com senha temporária: '.$password);
            }
        }

        $this->seedDominios();

        // Normaliza massa legada (professores → Pessoa + professor_matriculas). Idempotente.
        $this->call(PessoaLegadoNormalizacaoSeeder::class);

        $this->call([
            // SetorSeeder::class,
            // TipoStatusSeeder::class,
            // TipoManutencaoSeeder::class,
            // EscolaSeeder::class,
            // EmpresaContratadaSeeder::class,
            // PedidoSeeder::class,
            // ItensSeeder::class,
            // ContratoSeeder::class,
            // PedidoMerendaSeeder::class,
            // EstoqueInventarioOrganicoSeeder::class,
            // AvaliacaoFluxoSeeder::class,
            // PautasCombinatoriasSeeder::class,
            // AvaliacoesVariadasSeeder::class,
        ]);
    }

    private function seedDominios(): void
    {
        $setorPadrao = (string) config('app.default_setor_root_name', env('SETOR_DEFAULT_ROOT_NAME', 'Geral'));

        $dominios = [
            ['dominio' => 'gmail.com', 'setor' => $setorPadrao],
            ['dominio' => 'edu.umuarama.pr.gov.br', 'setor' => $setorPadrao],
            ['dominio' => 'umuarama.pr.gov.br', 'setor' => $setorPadrao],
        ];

        foreach ($dominios as $item) {
            DominioEmail::firstOrCreate(
                ['dominio_email' => $item['dominio']],
                ['setor' => $item['setor'], 'status' => 1]
            );
        }
    }
}
