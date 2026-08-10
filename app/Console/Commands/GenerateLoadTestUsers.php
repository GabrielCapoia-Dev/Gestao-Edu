<?php

namespace App\Console\Commands;

use App\Models\Escola;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class GenerateLoadTestUsers extends Command
{
    protected $signature = 'loadtest:users
        {--count=300 : Total de usuarios de carga}
        {--password=Mudar@1234 : Senha gravada para todos os usuarios}
        {--output=load-tests/data/users.local.csv : Caminho do CSV exportado}
        {--prefix=loadtest-secretario : Prefixo dos emails gerados}
        {--domain=loadtest.local : Dominio dos emails gerados}
        {--profile=secretario : Perfil gravado no CSV do K6}
        {--force : Permite execucao em APP_ENV=production}';

    protected $description = 'Cria usuarios sinteticos de carga com perfil de secretario e exporta um CSV compativel com K6.';

    /**
     * @var array<int, string>
     */
    private const LOAD_TEST_ROLES = [
        'Secretário',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Bloqueado em production. Use --force somente em ambiente de teste controlado.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $password = (string) $this->option('password');
        $prefix = Str::lower(trim((string) $this->option('prefix')));
        $domain = Str::lower(trim((string) $this->option('domain')));
        $profile = Str::lower(trim((string) $this->option('profile'))) ?: 'secretario';

        if ($prefix === '' || $domain === '') {
            $this->error('Prefixo e dominio sao obrigatorios.');

            return self::FAILURE;
        }

        if (! $this->rolesExist()) {
            $this->error('A role "Secretário" não existe.');
            $this->line('Execute "php artisan permissoes:criar" antes de gerar a massa de carga.');

            return self::FAILURE;
        }

        $schools = $this->schools();

        if ($schools->isEmpty()) {
            $this->error('Nenhuma escola ativa encontrada para vincular os usuarios de carga.');

            return self::FAILURE;
        }

        $rows = [['email', 'password', 'profile']];

        for ($i = 1; $i <= $count; $i++) {
            /** @var Escola $school */
            $school = $schools->random();
            $sequence = str_pad((string) $i, max(3, strlen((string) $count)), '0', STR_PAD_LEFT);
            $email = "{$prefix}+{$sequence}@{$domain}";

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'id_escola' => $school->getKey(),
                    'setor_id' => $school->setor_id,
                    'name' => "LOADTEST SECRETARIO {$sequence}",
                    'email_approved' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'google_id' => null,
                    'google_email' => null,
                    'avatar_url' => null,
                    'google_token' => null,
                    'google_refresh_token' => null,
                    'google_token_expires_in' => null,
                ],
            );

            $user->syncRoles(self::LOAD_TEST_ROLES);
            $user->syncPermissions([]);
            $user->escolas()->sync([$school->getKey()]);

            $rows[] = [$email, $password, $profile];
        }

        Artisan::call('pessoas:sincronizar-acessos', ['--apply' => true]);
        $this->writeCsv($rows, (string) $this->option('output'));
        $this->info("Usuarios de carga prontos: {$count}");
        $this->line('Roles: '.implode(', ', self::LOAD_TEST_ROLES));
        $this->line('Escolas usadas: '.$schools->count());
        $this->line('CSV: '.base_path((string) $this->option('output')));

        return self::SUCCESS;
    }

    private function rolesExist(): bool
    {
        $existing = Role::query()
            ->whereIn('name', self::LOAD_TEST_ROLES)
            ->pluck('name')
            ->all();

        return count(array_intersect(self::LOAD_TEST_ROLES, $existing)) === count(self::LOAD_TEST_ROLES);
    }

    /**
     * @return Collection<int, Escola>
     */
    private function schools(): Collection
    {
        return Escola::query()
            ->where('ativo', true)
            ->whereNotNull('id')
            ->get(['id', 'setor_id']);
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function writeCsv(array $rows, string $output): void
    {
        $path = Str::startsWith($output, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $output)
            ? $output
            : base_path($output);

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'wb');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
