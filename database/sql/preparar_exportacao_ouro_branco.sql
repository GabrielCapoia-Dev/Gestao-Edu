-- Workaround temporario para exportar o parecer do 1o Ano A - Ouro Branco.
-- Motivo: a aluna transferida nao possui snapshot final e o exportador inclui
-- todo status diferente de 'pendente'. O status pendente e excluido pelo filtro.
-- Execute antes da exportacao e execute restaurar_exportacao_ouro_branco.sql
-- imediatamente depois que o arquivo for gerado.
-- Nao execute durante outra movimentacao de alunos.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS srm_workaround_export_ouro_branco (
    aluno_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    status_original VARCHAR(255) NOT NULL,
    status_alterado_em_original DATETIME NULL,
    status_alterado_por_original BIGINT UNSIGNED NULL,
    status_motivo_original TEXT NULL,
    criado_em DATETIME NOT NULL
) ENGINE=InnoDB;

DELIMITER $$
CREATE PROCEDURE preparar_exportacao_ouro_branco()
BEGIN
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    ROLLBACK;
    RESIGNAL;
END;
START TRANSACTION;

-- Confirma que a aluna continua sendo a mesma do diagnóstico.
SELECT id,nome,cgm,status,id_turma
FROM alunos
WHERE id=27450
  AND cgm='1025817601'
  AND id_turma=1308
FOR UPDATE;

IF NOT EXISTS (
    SELECT 1 FROM alunos
    WHERE id=27450 AND cgm='1025817601' AND id_turma=1308
      AND status='transferido'
) THEN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT='Aluna 27450 nao esta no estado esperado. Nenhuma alteracao feita.';
END IF;

INSERT INTO srm_workaround_export_ouro_branco
    (aluno_id,status_original,status_alterado_em_original,status_alterado_por_original,status_motivo_original,criado_em)
SELECT id,status,status_alterado_em,status_alterado_por,status_motivo,NOW()
FROM alunos
WHERE id=27450
ON DUPLICATE KEY UPDATE aluno_id=VALUES(aluno_id);

UPDATE alunos
SET status='pendente'
WHERE id=27450
  AND cgm='1025817601'
  AND id_turma=1308
  AND status='transferido';

SELECT id,nome,cgm,status,id_turma
FROM alunos
WHERE id=27450;

COMMIT;

SELECT 'Status temporariamente ajustado. Agora exporte o parecer e depois execute o script de restauracao.' AS resultado;
END$$
DELIMITER ;
CALL preparar_exportacao_ouro_branco();
DROP PROCEDURE preparar_exportacao_ouro_branco;
