# Padrao de Relatorios PDF

## Objetivo

Definir o padrao obrigatorio para qualquer relatorio PDF novo ou alterado no projeto, evitando que header, footer, paginacao e metadados voltem a divergir entre dominios.

## Onde isso vive no codigo

- `app/Services/Relatorios/RelatorioPdfRenderer.php`
- `app/Relatorios/Relatorios.php`
- `resources/views/relatorios/layouts/base-pdf.blade.php`
- `resources/views/relatorios/layouts/partials/header.blade.php`
- `resources/views/relatorios/layouts/partials/footer.blade.php`

## Comportamento padrao

- Todo PDF deve ser renderizado por `RelatorioPdfRenderer`.
- Todo Blade de relatorio PDF deve estender `relatorios.layouts.base-pdf`.
- O cabecalho institucional usa as logos da Educacao e Abrinq e o nome do sistema.
- O rodape institucional mostra origem do documento, usuario/data de exportacao e deixa o centro reservado para `Pagina X de Y`.
- O resumo de filtros deve ser enviado em `reportFilters` pelo service/controller e nao montado manualmente no Blade.
- Metadados padrao esperados:
  - `reportTitle`
  - `reportSubtitle` opcional
  - `reportFilters` opcional
  - `usuarioExportacao`
  - `dataExportacao`
  - `orientation` opcional
  - `paperSize` opcional
  - `showPagination` opcional

## Riscos e cuidados

- Nao use `Pdf::loadView()` diretamente em novos fluxos, senao a paginacao e o layout padrao podem divergir.
- Se o Blade precisar de imagens locais, mantenha os assets dentro de `public/` para respeitar o `chroot`.
- Em relatorios multipagina, preserve `display: table-header-group` em tabelas longas e `page-break-*` nos blocos sensiveis.
- O fluxo `relatorios.Ficha.alunos-ficha` continua referenciado no codigo, mas o Blade nao existe hoje. Se esse fluxo voltar a ser usado, criar o arquivo seguindo este padrao.

## Quando consultar

- Antes de criar um novo relatorio PDF.
- Antes de refatorar exportacoes ja existentes.
- Quando houver bug de layout, quebra de pagina, cabecalho ou rodape em PDF.
