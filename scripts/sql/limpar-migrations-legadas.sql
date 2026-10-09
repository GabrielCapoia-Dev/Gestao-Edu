-- Remove somente registros de migrations antigas que continuam no banco,
-- mas não existem mais no código atual.
--
-- Não remove tabelas, colunas, índices ou dados da aplicação.
-- Cria uma cópia dos registros antes da exclusão para permitir restauração.

USE `gestao-edu`;

CREATE TABLE IF NOT EXISTS migrations_removidas_backup_20261009 LIKE migrations;

INSERT IGNORE INTO migrations_removidas_backup_20261009
    (id, migration, batch)
SELECT id, migration, batch
FROM migrations
WHERE migration IN (
    '2025_05_09_203508_create_activity_log_table',
    '2025_05_09_203509_add_event_column_to_activity_log_table',
    '2025_05_09_203510_add_batch_uuid_column_to_activity_log_table',
    '2025_11_04_101303_create_laudos_table',
    '2025_11_04_101304_create_alunos_table',
    '2025_11_06_083651_create_aluno_retencoes_table',
    '2025_11_07_013651_create_aluno_laudo_table'
);

-- Conferência dos registros que serão removidos.
SELECT id, migration, batch
FROM migrations
WHERE migration IN (
    '2025_05_09_203508_create_activity_log_table',
    '2025_05_09_203509_add_event_column_to_activity_log_table',
    '2025_05_09_203510_add_batch_uuid_column_to_activity_log_table',
    '2025_11_04_101303_create_laudos_table',
    '2025_11_04_101304_create_alunos_table',
    '2025_11_06_083651_create_aluno_retencoes_table',
    '2025_11_07_013651_create_aluno_laudo_table'
)
ORDER BY id;

DELETE FROM migrations
WHERE migration IN (
    '2025_05_09_203508_create_activity_log_table',
    '2025_05_09_203509_add_event_column_to_activity_log_table',
    '2025_05_09_203510_add_batch_uuid_column_to_activity_log_table',
    '2025_11_04_101303_create_laudos_table',
    '2025_11_04_101304_create_alunos_table',
    '2025_11_06_083651_create_aluno_retencoes_table',
    '2025_11_07_013651_create_aluno_laudo_table'
);

-- Conferência final: deve retornar zero registros.
SELECT id, migration, batch
FROM migrations
WHERE migration IN (
    '2025_05_09_203508_create_activity_log_table',
    '2025_05_09_203509_add_event_column_to_activity_log_table',
    '2025_05_09_203510_add_batch_uuid_column_to_activity_log_table',
    '2025_11_04_101303_create_laudos_table',
    '2025_11_04_101304_create_alunos_table',
    '2025_11_06_083651_create_aluno_retencoes_table',
    '2025_11_07_013651_create_aluno_laudo_table'
)
ORDER BY id;

-- Para restaurar os registros, execute manualmente:
-- INSERT INTO migrations (id, migration, batch)
-- SELECT id, migration, batch
-- FROM migrations_removidas_backup_20261009;
