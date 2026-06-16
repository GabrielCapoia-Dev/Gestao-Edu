---
name: gestao-edu-revisao-lingua-portuguesa
description: Use para revisar ou corrigir textos visiveis do Gestao-Edu em portugues brasileiro, incluindo acentuacao, ortografia, gramatica, concordancia e clareza em Filament, Livewire, Blade, notificacoes, validacoes, PDFs, exportacoes e traducoes proprias.
---

# Revisão de Língua Portuguesa

Use esta skill sempre que uma tarefa criar, alterar ou revisar texto apresentado ao usuário.

## Objetivo

Garantir português brasileiro correto e consistente sem alterar contratos técnicos, regras de negócio ou dados persistidos.

## Sequência recomendada

1. Identificar os textos visíveis tocados pela tarefa e o contexto em que aparecem.
2. Ler [`references/mapa-textos-e-diagnostico.md`](references/mapa-textos-e-diagnostico.md) quando a revisão atravessar mais de um arquivo ou fluxo.
3. Para uma varredura inicial, executar:

   ```powershell
   php .codex/skills/gestao-edu-revisao-lingua-portuguesa/scripts/auditar_textos.php
   ```

4. Revisar cada candidato no contexto. O script apenas aponta indícios e nunca edita arquivos.
5. Corrigir somente o conteúdo apresentado ao usuário.
6. Executar validação de sintaxe e testes focados proporcionais aos arquivos alterados.

## Regras de revisão

- Usar português brasileiro e preservar o sentido funcional da mensagem.
- Corrigir acentuação, ortografia, pontuação, regência, concordância e construções pouco claras.
- Manter o tom já usado no fluxo; não reescrever mensagens corretas apenas por preferência estilística.
- Preservar placeholders como `:attribute`, `{$nome}`, `%s`, tags HTML, diretivas Blade e interpolações.
- Manter os arquivos em UTF-8 e verificar sinais de codificação incorreta antes de salvar.
- Revisar singular, plural e gênero conforme os valores dinâmicos que compõem a frase.
- Em PDFs e exportações, conferir títulos, cabeçalhos, colunas, legendas, textos substitutos e mensagens de ausência de dados.

## Limites técnicos

Não alterar apenas para aplicar acentuação ou estilo:

- nomes de classes, métodos, variáveis, propriedades, colunas e tabelas
- rotas, slugs, URLs, nomes de eventos, filas e chaves de configuração
- enums, constantes, códigos, identificadores de integração e chaves de tradução
- nomes de permissions e roles persistidos, salvo quando houver migração explícita e segura
- conteúdo de dados históricos ou seeders que funcione como chave de negócio

Quando um mesmo texto também funcionar como identificador persistido, separar a apresentação do identificador ou manter o valor técnico existente.

## Checklist de validação

- Confirmar que somente textos visíveis foram alterados.
- Confirmar que placeholders e interpolações continuam presentes e na mesma quantidade.
- Executar `php -l` nos arquivos PHP alterados.
- Executar testes focados quando mensagens, validações ou saídas geradas tiverem assertions.
- Para Blade ou PDF sem teste automatizado, revisar o HTML/texto gerado sem iniciar servidor ou navegador.
- Executar `git diff --check` e inspecionar o diff final.

## Saída esperada

- textos corrigidos com o significado original preservado
- identificadores técnicos mantidos
- candidatos do diagnóstico confirmados ou descartados por contexto
- validações executadas e limitações registradas
