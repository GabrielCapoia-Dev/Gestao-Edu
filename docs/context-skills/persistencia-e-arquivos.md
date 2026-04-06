# Persistencia e Arquivos

## Objetivo

Resumir os pontos de persistencia que mais influenciam comportamento global: migrations estruturantes, seeders e storage.

## Onde isso vive no codigo

- `database/migrations`
- `database/seeders`
- `config/filesystems.php`
- `app/Http/Controllers/LaudoArquivoController.php`
- `app/Http/Controllers/PedidoArquivoController.php`

## Persistencia estruturante

- `users`, `roles`, `permissions` e tabelas de Spatie sustentam autenticacao e autorizacao.
- `setor`, `escolas`, `tipo_status`, `tipo_manutencao` sustentam o fluxo de manutencao.
- `pedidos`, `pedido_historicos`, `pedido_arquivos`, `feedback_pedidos` sustentam rastreabilidade de manutencao.
- `contratos`, `contrato_item`, `pedidos_merenda`, `pedido_merenda_itens`, `estoque`, `estoque_movimentacoes` sustentam alimentacao escolar.
- `laudos`, `alunos`, `aluno_laudo`, `turmas`, `professores`, `componentes` sustentam o dominio pedagogico.

## Seeders criticos

- `DatabaseSeeder` monta usuarios, roles, permissoes e dominios iniciais.
- `SetorSeeder` define os setores esperados pelo fluxo.
- `TipoStatusSeeder` define o vocabulario de status usado por services e observer.
- `TipoManutencaoSeeder` define tipos base de manutencao.
- `PedidoSeeder` e `PedidoMerendaSeeder` geram massa de dados aderente aos fluxos.
- `SecretarioUnidadesSeeder` baixa CSV externo e vincula usuarios a escolas.

## Arquivos e storage

- Disk `public`: arquivos publicos, inclusive uploads de pedidos.
- Disk `laudos`: storage local privado em `storage/app/laudos`.
- Laudos sao servidos por controller com policy, nao por link publico direto.
- Downloads de anexos de pedido tambem passam por policy.

## Riscos e cuidados

- Mudar nomes seedados sem revisar services quebra comportamento.
- `SecretarioUnidadesSeeder` depende de recurso externo; isso e fragil para reproducao.
- Alteracoes em caminhos de disk ou policy podem expor arquivos sensiveis ou quebrar downloads.

## Quando consultar

- Criacao de migration ou seeder novo
- Mudanca em upload/download
- Analise de dados iniciais e dependencias de ambiente
