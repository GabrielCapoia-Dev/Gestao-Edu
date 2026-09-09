-- Restaura o status original apos a exportacao.
-- Execute somente depois que o arquivo do parecer estiver gerado.

DELIMITER $$
CREATE PROCEDURE restaurar_exportacao_ouro_branco()
BEGIN
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    ROLLBACK;
    RESIGNAL;
END;
START TRANSACTION;

SELECT a.id,a.nome,a.cgm,a.status,b.status_original
FROM alunos a
JOIN srm_workaround_export_ouro_branco b ON b.aluno_id=a.id
FOR UPDATE;

UPDATE alunos a
JOIN srm_workaround_export_ouro_branco b ON b.aluno_id=a.id
SET a.status=b.status_original,
    a.status_alterado_em=b.status_alterado_em_original,
    a.status_alterado_por=b.status_alterado_por_original,
    a.status_motivo=b.status_motivo_original
WHERE a.status='pendente';

SELECT id,nome,cgm,status,id_turma
FROM alunos
WHERE id=27450;

COMMIT;

SELECT 'Status original restaurado. Mantenha a tabela de auditoria para conferencia.' AS resultado;
END$$
DELIMITER ;
CALL restaurar_exportacao_ouro_branco();
DROP PROCEDURE restaurar_exportacao_ouro_branco;
