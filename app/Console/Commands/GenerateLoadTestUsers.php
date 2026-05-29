<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateLoadTestUsers extends Command
{
    protected $signature = 'loadtest:users
        {--count=300 : Total de usuarios de carga}
        {--password=Mudar@1234 : Senha gravada para todos os usuarios}
        {--output=load-tests/data/users.local.csv : Caminho do CSV exportado}
        {--prefix=loadtest : Prefixo dos emails gerados}
        {--domain=loadtest.local : Dominio dos emails gerados}
        {--profile-counts= : Distribuicao manual, ex: staff:70,school:120,cmei:110}
        {--force : Permite execucao em APP_ENV=production}';

    protected $description = 'Cria usuarios de carga nao-admin e exporta um CSV compativel com K6.';

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

        if ($prefix === '' || $domain === '') {
            $this->error('Prefixo e dominio sao obrigatorios.');

            return self::FAILURE;
        }

        $sources = $this->sourceUsers($prefix, $domain);

        if ($sources->isEmpty()) {
            $this->error('Nenhum usuario nao-admin encontrado para servir como modelo.');

            return self::FAILURE;
        }

        $quotas = $this->profileQuotas($sources, $count);
        $rows = [['email', 'password', 'profile']];
        $created = 0;

        foreach ($quotas as $profile => $quota) {
            $profileSources = $sources
                ->filter(fn (User $user): bool => $this->profileFor($user) === $profile)
                ->values();

            if ($profileSources->isEmpty()) {
                continue;
            }

            for ($i = 1; $i <= $quota; $i++) {
                /** @var User $source */
                $source = $profileSources[($i - 1) % $profileSources->count()];
                $email = "{$prefix}+{$profile}{$i}@{$domain}";
                $name = 'LOADTEST '.Str::upper($profile).' '.$i;

                $user = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'id_escola' => $source->id_escola,
                        'setor_id' => $source->setor_id,
                        'name' => $name,
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

                $user->syncRoles($source->roles->pluck('name')->all());
                $user->syncPermissions($source->getDirectPermissions()->pluck('name')->all());

                $rows[] = [$email, $password, $profile];
                $created++;
            }
        }

        $this->writeCsv($rows, (string) $this->option('output'));
        $this->info("Usuarios de carga prontos: {$created}");
        $this->line('CSV: '.base_path((string) $this->option('output')));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function sourceUsers(string $prefix, string $domain): Collection
    {
        return User::query()
            ->with(['roles.permissions', 'permissions'])
            ->where('id', '!=', 1)
            ->where('email', 'not like', "{$prefix}+%@{$domain}")
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Admin'))
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => $this->profileFor($user) !== 'admin')
            ->values();
    }

    /**
     * @param Collection<int, User> $sources
     * @return array<string, int>
     */
    private function profileQuotas(Collection $sources, int $count): array
    {
        $manual = $this->manualProfileQuotas();

        if ($manual !== []) {
            return $manual;
        }

        $groups = $sources->groupBy(fn (User $user): string => $this->profileFor($user));
        $total = max(1, $sources->count());
        $quotas = [];
        $fractions = [];
        $assigned = 0;

        foreach ($groups as $profile => $users) {
            $raw = ($users->count() / $total) * $count;
            $quota = (int) floor($raw);
            $quotas[$profile] = $quota;
            $fractions[$profile] = $raw - $quota;
            $assigned += $quota;
        }

        arsort($fractions);

        foreach (array_keys($fractions) as $profile) {
            if ($assigned >= $count) {
                break;
            }

            $quotas[$profile]++;
            $assigned++;
        }

        return array_filter($quotas, fn (int $quota): bool => $quota > 0);
    }

    /**
     * @return array<string, int>
     */
    private function manualProfileQuotas(): array
    {
        $value = trim((string) $this->option('profile-counts'));

        if ($value === '') {
            return [];
        }

        $quotas = [];

        foreach (explode(',', $value) as $part) {
            [$profile, $count] = array_pad(explode(':', trim($part), 2), 2, null);

            $profile = Str::lower(trim((string) $profile));
            $count = (int) $count;

            if ($profile !== '' && $count > 0) {
                $quotas[$profile] = $count;
            }
        }

        return $quotas;
    }

    private function profileFor(User $user): string
    {
        $text = Str::lower(Str::ascii($user->email.' '.$user->name));

        if (Str::lower((string) $user->email) === 'admin@admin.com' || Str::lower((string) $user->name) === 'admin') {
            return 'admin';
        }

        if (str_contains($text, 'cmei') || str_contains($text, 'cei')) {
            return 'cmei';
        }

        if (str_contains($text, 'escola')) {
            return 'school';
        }

        return 'staff';
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
