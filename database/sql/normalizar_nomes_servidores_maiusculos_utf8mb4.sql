-- MySQL/MariaDB
-- Coloca em maiusculas os nomes de todos os servidores e sincroniza os
-- campos legados de professores e usuarios vinculados.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ABNT 2 e layout de teclado. No banco, o suporte a ã, ç, á, à, õ e demais
-- caracteres e garantido por utf8mb4.
ALTER TABLE `servidores`
    MODIFY COLUMN `nome` VARCHAR(255)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci
    NOT NULL;

SET @sql_safe_updates_anterior := @@SQL_SAFE_UPDATES;
SET SQL_SAFE_UPDATES = 0;

START TRANSACTION;

-- Atualiza todos os registros, sem depender de comparacao de collation.
UPDATE `servidores`
SET
    `nome` = UPPER(TRIM(`nome`)),
    `updated_at` = CURRENT_TIMESTAMP;

SET @servidores_atualizados := ROW_COUNT();

-- professores.nome ainda e usado por relatorios e fluxos legados.
UPDATE `professores` AS `p`
INNER JOIN `servidores` AS `s` ON `s`.`id` = `p`.`servidor_id`
SET
    `p`.`nome` = `s`.`nome`,
    `p`.`updated_at` = CURRENT_TIMESTAMP;

SET @professores_sincronizados := ROW_COUNT();

-- users.name e o espelho do nome exibido nos fluxos de acesso.
UPDATE `users` AS `u`
INNER JOIN `servidores` AS `s` ON `s`.`user_id` = `u`.`id`
SET
    `u`.`name` = `s`.`nome`,
    `u`.`updated_at` = CURRENT_TIMESTAMP;

SET @usuarios_sincronizados := ROW_COUNT();

SELECT
    @servidores_atualizados AS `servidores_atualizados`,
    @professores_sincronizados AS `professores_sincronizados`,
    @usuarios_sincronizados AS `usuarios_sincronizados`;

COMMIT;

SET SQL_SAFE_UPDATES = @sql_safe_updates_anterior;

-- Conferencia: esta consulta deve retornar zero registros.
SELECT `id`, `nome`
FROM `servidores`
WHERE CAST(`nome` AS BINARY) <> CAST(UPPER(`nome`) AS BINARY);
