# Mapa de textos e diagnóstico

## Pontos textuais do projeto

- `app/Filament/`: labels, títulos, descrições, placeholders, ajuda, modais, ações e notificações.
- `app/Services/`, `app/Support/`, `app/Exceptions/` e `app/Http/Controllers/`: mensagens de validação, retorno, bloqueio e orientação.
- `resources/views/`: páginas Blade, componentes, autenticação, relatórios HTML e templates de PDF.
- `routes/`: mensagens flash declaradas diretamente nas rotas.
- `lang/pt_BR/` e `lang/pt_BR.json`: traduções próprias da aplicação.
- `resources/js/`: mensagens exibidas pelo frontend, quando existirem.

Não revisar `lang/vendor/` como código próprio. Mudanças em traduções publicadas de dependências devem ser deliberadas e separadas de uma correção comum.

## Diagnóstico inicial

A varredura inicial identificou exemplos reais que justificam revisão contextual:

| Texto encontrado | Forma esperada | Exemplo de localização |
| --- | --- | --- |
| `Notificacao enviada` | `Notificação enviada` | `routes/web.php` |
| `Descricao no documento` | `Descrição no documento` | páginas e resources de alternativas/pautas |
| `Periodo do pedido` | `Período do pedido` | página de feedback de manutenção |
| `Secretaria de Educacao` | `Secretaria de Educação` | páginas de autenticação |

Também há ocorrências sem acento em relatórios, mensagens de importação/exportação e textos de inventário. Cada ocorrência deve ser confirmada no contexto porque a mesma palavra pode aparecer em identificadores técnicos.

## Prioridade da revisão

1. Mensagens de erro, bloqueio e confirmação.
2. Labels, títulos e instruções de formulários.
3. Relatórios, PDFs e exportações entregues ao usuário.
4. Páginas públicas e autenticação.
5. Traduções próprias e textos auxiliares.
6. Comentários e saídas internas de desenvolvimento, somente quando fizerem parte da tarefa.

## Proteções obrigatórias

- Não renomear rotas, métodos, variáveis, colunas, enums, chaves ou slugs.
- Não alterar permissions e roles persistidos sem mapear o impacto no banco e nas verificações de acesso.
- Não remover nem traduzir placeholders, tags, componentes Blade ou interpolações.
- Não usar substituição global automática.
- Tratar o resultado do script como lista de candidatos, não como prova de erro.
