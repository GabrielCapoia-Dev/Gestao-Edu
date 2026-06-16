<?php

declare(strict_types=1);

/**
 * Auditoria somente leitura de possíveis problemas de português em textos visíveis.
 *
 * Uso:
 * php .codex/skills/gestao-edu-revisao-lingua-portuguesa/scripts/auditar_textos.php [raiz-do-repositorio]
 */

$repositoryRoot = isset($argv[1])
    ? realpath($argv[1])
    : realpath(dirname(__DIR__, 4));

if ($repositoryRoot === false || ! is_dir($repositoryRoot)) {
    fwrite(STDERR, "Raiz do repositório não encontrada.\n");
    exit(1);
}

$scanTargets = [
    'app/Exceptions',
    'app/Filament',
    'app/Http/Controllers',
    'app/Notifications',
    'app/Services',
    'app/Support',
    'resources/js',
    'resources/views',
    'routes',
    'lang/pt_BR',
    'lang/pt_BR.json',
];

$accentRules = [
    'acao' => 'ação',
    'acoes' => 'ações',
    'avaliacao' => 'avaliação',
    'avaliacoes' => 'avaliações',
    'balanco' => 'balanço',
    'configuracao' => 'configuração',
    'conclusao' => 'conclusão',
    'confirmacao' => 'confirmação',
    'descricao' => 'descrição',
    'educacao' => 'educação',
    'exportacao' => 'exportação',
    'importacao' => 'importação',
    'informacao' => 'informação',
    'informacoes' => 'informações',
    'impossivel' => 'impossível',
    'inicio' => 'início',
    'inventario' => 'inventário',
    'manutencao' => 'manutenção',
    'matricula' => 'matrícula',
    'nao' => 'não',
    'niveis' => 'níveis',
    'notificacao' => 'notificação',
    'numero' => 'número',
    'observacao' => 'observação',
    'observacoes' => 'observações',
    'opcao' => 'opção',
    'opcoes' => 'opções',
    'periodo' => 'período',
    'permissao' => 'permissão',
    'permissoes' => 'permissões',
    'possivel' => 'possível',
    'rapido' => 'rápido',
    'relatorio' => 'relatório',
    'relatorios' => 'relatórios',
    'satisfacao' => 'satisfação',
    'serie' => 'série',
    'series' => 'séries',
    'situacao' => 'situação',
    'solicitacao' => 'solicitação',
    'usuario' => 'usuário',
    'usuarios' => 'usuários',
    'voce' => 'você',
];

$grammarRules = [
    [
        'pattern' => '/\bentre\s+(:[a-z_]+|\d+)\s+a\s+(:[a-z_]+|\d+)\b/iu',
        'suggestion' => 'usar "entre ... e ..."',
        'reason' => 'regência',
    ],
    [
        'pattern' => '/\bfoi\s+solicitado\s+uma\b/iu',
        'suggestion' => 'usar "foi solicitada uma"',
        'reason' => 'concordância',
    ],
    [
        'pattern' => '/\barquivo\s+:attribute\s+ser\s+menor\b/iu',
        'suggestion' => 'usar "arquivo :attribute deve ser menor"',
        'reason' => 'construção incompleta',
    ],
];

$allowedExtensions = ['php', 'json', 'js', 'ts'];
$findings = [];
$scannedFiles = 0;

foreach (collectFiles($repositoryRoot, $scanTargets, $allowedExtensions) as $filePath) {
    $contents = file_get_contents($filePath);

    if ($contents === false) {
        continue;
    }

    $scannedFiles++;
    $relativePath = normalizePath(substr($filePath, strlen($repositoryRoot) + 1));

    if (! mb_check_encoding($contents, 'UTF-8')) {
        $findings[] = [
            'file' => $relativePath,
            'line' => 1,
            'reason' => 'codificação',
            'text' => 'arquivo não está em UTF-8 válido',
            'suggestion' => 'revisar a codificação sem alterar o conteúdo técnico',
        ];
        continue;
    }

    foreach (preg_split('/\R/u', $contents) ?: [] as $lineIndex => $line) {
        $lineNumber = $lineIndex + 1;

        foreach (extractReviewableTexts($line, $relativePath) as $text) {
            foreach ($accentRules as $incorrect => $correct) {
                if (preg_match('/\b'.preg_quote($incorrect, '/').'\b/iu', $text) !== 1) {
                    continue;
                }

                $findings[] = [
                    'file' => $relativePath,
                    'line' => $lineNumber,
                    'reason' => 'possível falta de acentuação',
                    'text' => compactText($text),
                    'suggestion' => sprintf('revisar "%s" como "%s"', $incorrect, $correct),
                ];
            }

            foreach ($grammarRules as $rule) {
                if (preg_match($rule['pattern'], $text) !== 1) {
                    continue;
                }

                $findings[] = [
                    'file' => $relativePath,
                    'line' => $lineNumber,
                    'reason' => $rule['reason'],
                    'text' => compactText($text),
                    'suggestion' => $rule['suggestion'],
                ];
            }

            if (str_contains($text, 'Ã') || str_contains($text, 'Â')) {
                $findings[] = [
                    'file' => $relativePath,
                    'line' => $lineNumber,
                    'reason' => 'possível texto corrompido por codificação',
                    'text' => compactText($text),
                    'suggestion' => 'confirmar a leitura e gravação em UTF-8',
                ];
            }
        }
    }
}

usort(
    $findings,
    static fn (array $left, array $right): int => [$left['file'], $left['line'], $left['reason']]
        <=> [$right['file'], $right['line'], $right['reason']],
);

foreach ($findings as $finding) {
    printf(
        "%s:%d [%s] %s | %s\n",
        $finding['file'],
        $finding['line'],
        $finding['reason'],
        $finding['text'],
        $finding['suggestion'],
    );
}

printf(
    "\nAuditoria concluída: %d arquivo(s) lido(s), %d candidato(s), nenhuma alteração realizada.\n",
    $scannedFiles,
    count($findings),
);

exit(0);

/**
 * @return list<string>
 */
function collectFiles(string $root, array $targets, array $allowedExtensions): array
{
    $files = [];

    foreach ($targets as $target) {
        $absoluteTarget = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $target);

        if (is_file($absoluteTarget)) {
            if (isAllowedFile($absoluteTarget, $allowedExtensions)) {
                $files[] = $absoluteTarget;
            }
            continue;
        }

        if (! is_dir($absoluteTarget)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteTarget, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && isAllowedFile($fileInfo->getPathname(), $allowedExtensions)) {
                $files[] = $fileInfo->getPathname();
            }
        }
    }

    return array_values(array_unique($files));
}

function isAllowedFile(string $filePath, array $allowedExtensions): bool
{
    foreach ($allowedExtensions as $extension) {
        if (str_ends_with(mb_strtolower($filePath), '.'.$extension)) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function extractReviewableTexts(string $line, string $relativePath): array
{
    $texts = [];

    if (preg_match_all('/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"/u', $line, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as [$literal, $offset]) {
            $content = substr($literal, 1, -1);
            $afterLiteral = substr($line, $offset + strlen($literal));

            if (preg_match('/^\s*(=>|:)/', $afterLiteral) === 1) {
                continue;
            }

            if (isTechnicalText($content, $line, $relativePath)) {
                continue;
            }

            $texts[] = sanitizeReviewText(stripcslashes($content));
        }
    }

    if (str_ends_with($relativePath, '.blade.php')) {
        $visibleText = preg_replace([
            '/\{\{.*?\}\}/u',
            '/\{!!.*?!!\}/u',
            '/<\?.*?\?>/u',
            '/<[^>]+>/u',
            '/@[a-zA-Z_][a-zA-Z0-9_]*(?:\(.*\))?/u',
        ], ' ', $line);
        $visibleText = html_entity_decode((string) $visibleText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (trim($visibleText) !== '') {
            $texts[] = $visibleText;
        }
    }

    return array_values(array_unique($texts));
}

function isTechnicalText(string $text, string $line, string $relativePath): bool
{
    $trimmed = trim($text);

    if ($trimmed === '') {
        return true;
    }

    if (preg_match('/(?:\\\\|::|->|https?:\/\/|^[\/#.]|[_]{1,}|^[a-z0-9.-]+\.[a-z0-9.-]+$)/i', $trimmed) === 1) {
        return true;
    }

    if (preg_match('#[/\\\\]|^[a-z0-9_.]+:[a-z0-9_,.]+$#i', $trimmed) === 1) {
        return true;
    }

    if (preg_match('/\b(Route::|->name\s*\(|config\s*\(|env\s*\(|where\s*\(|select\s*\(|pluck\s*\(|can(?:Any)?\s*\(|authorize\s*\(|hasPermissionLike\s*\(|request\s*\(\)\s*->\s*query\s*\(|permission)/i', $line) === 1) {
        return true;
    }

    if (preg_match('#(?:Permission|Permissions|Policies|Console/Commands)#i', normalizePath($relativePath)) === 1) {
        return true;
    }

    if (! str_contains($trimmed, ' ') && preg_match('/^[a-z0-9.-]+$/i', $trimmed) === 1) {
        $hasPresentationContext = str_ends_with($relativePath, '.blade.php')
            || preg_match(
                '/->(?:label|title|body|heading|description|helperText|placeholder|modalHeading|modalDescription)\s*\(|navigation(?:Group|Label)|modelLabel|pluralModelLabel/i',
                $line,
            ) === 1;

        if (! $hasPresentationContext) {
            return true;
        }

        $knownVisibleWords = [
            'acao', 'acoes', 'avaliacao', 'avaliacoes', 'balanco', 'configuracao',
            'conclusao', 'confirmacao', 'descricao', 'educacao', 'exportacao',
            'importacao', 'informacao', 'informacoes', 'impossivel', 'inicio', 'inventario',
            'manutencao', 'matricula', 'nao', 'niveis', 'notificacao', 'numero',
            'observacao', 'observacoes', 'opcao', 'opcoes', 'periodo', 'permissao',
            'permissoes', 'possivel', 'rapido', 'relatorio', 'relatorios', 'satisfacao',
            'serie', 'series', 'situacao', 'solicitacao', 'usuario', 'usuarios', 'voce',
        ];

        return ! in_array(mb_strtolower($trimmed), $knownVisibleWords, true);
    }

    return false;
}

function compactText(string $text): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return mb_strlen($text) > 120
        ? mb_substr($text, 0, 117).'...'
        : $text;
}

function sanitizeReviewText(string $text): string
{
    return (string) preg_replace([
        '/\{\$[^}]+\}/u',
        '/\$[a-zA-Z_][a-zA-Z0-9_]*/u',
    ], ' ', $text);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}
