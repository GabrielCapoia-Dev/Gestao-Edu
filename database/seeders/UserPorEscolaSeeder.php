<?php

namespace Database\Seeders;

use App\Models\Escola;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

class SecretarioUnidadesSeeder extends Seeder
{
    private const CSV_URL = 'https://docs.google.com/spreadsheets/d/16gcXTkCooDaL3cjTO4UNg_O_SOweXMDTYEBXeOXyvdM/export?format=csv&gid=0';

    private const PASSWORD = 'Senha@123';

    private array $escolasCache = [];

    public function run(): void
    {
        $this->command->info('📥 Carregando CSV de unidades educacionais...');

        $response = Http::timeout(30)->get(self::CSV_URL);

        if (!$response->ok()) {
            $this->command->error('❌ Falha ao baixar CSV.');
            return;
        }

        $linhas = $this->parseCsv($response->body());

        if (empty($linhas)) {
            $this->command->warn('⚠️ CSV vazio.');
            return;
        }

        array_shift($linhas); // Remove cabeçalho

        // Cache de escolas normalizado
        $this->escolasCache = Escola::all()
            ->keyBy(fn($e) => $this->normalize($e->nome))
            ->toArray();

        $secretarioRole = Role::firstOrCreate(['name' => 'Secretario']);

        $criados = 0;
        $existentes = 0;
        $ignorados = 0;
        $semEscola = [];

        foreach ($linhas as $linha) {
            // Coluna A e B: Escola
            $escolaNome = trim($linha[0] ?? '');
            $escolaEmail = trim($linha[1] ?? '');

            if ($escolaNome !== '' && $escolaEmail !== '') {
                $result = $this->criarUsuario($escolaNome, $escolaEmail, $secretarioRole, $semEscola);
                if ($result === 'criado') $criados++;
                elseif ($result === 'existente') $existentes++;
                else $ignorados++;
            }

            // Coluna D e E: CMEI
            $cmeiNome = trim($linha[3] ?? '');
            $cmeiEmail = trim($linha[4] ?? '');

            if ($cmeiNome !== '' && $cmeiEmail !== '') {
                $result = $this->criarUsuario($cmeiNome, $cmeiEmail, $secretarioRole, $semEscola);
                if ($result === 'criado') $criados++;
                elseif ($result === 'existente') $existentes++;
                else $ignorados++;
            }
        }

        $this->command->newLine();
        $this->command->info('=====================================');
        $this->command->info("✅ Usuários criados: {$criados}");
        $this->command->info("⏭️ Já existentes: {$existentes}");
        $this->command->info("⚠️ Ignorados: {$ignorados}");
        $this->command->info('=====================================');

        if (!empty($semEscola)) {
            $this->command->newLine();
            $this->command->warn('🏫 Escolas não encontradas no banco:');
            foreach ($semEscola as $nome => $qtd) {
                $this->command->line("   - {$nome}");
            }
        }
    }

    private function criarUsuario(string $nome, string $email, Role $role, array &$semEscola): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->warn("   ⚠️ Email inválido: {$email}");
            return 'ignorado';
        }

        // Busca escola pelo nome normalizado
        $escolaData = $this->escolasCache[$this->normalize($nome)] ?? null;

        if (!$escolaData) {
            $semEscola[$nome] = true;
            $this->command->warn("   ⚠️ Escola não encontrada: {$nome}");
            return 'ignorado';
        }

        $user = User::firstOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => $nome,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'email_approved' => true,
                'id_escola' => $escolaData['id'],
            ]
        );

        if ($user->wasRecentlyCreated) {
            $user->assignRole($role);
            $this->command->info("   ✅ {$nome} ({$email}) → Escola ID: {$escolaData['id']}");
            return 'criado';
        }

        return 'existente';
    }

    private function normalize(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = preg_replace('/[^A-Z0-9\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }

    private function parseCsv(string $csv): array
    {
        $linhas = [];
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $csv);
        rewind($fh);

        while (($row = fgetcsv($fh)) !== false) {
            $linhas[] = $row;
        }

        fclose($fh);

        return $linhas;
    }
}