-- CORREÇÃO TEMPORÁRIA — RESTAURAÇÃO — ESCOLA PAULO FREIRE (ID 62) — 1º ANO
--
-- Executar somente depois da exportação dos pareceres.
-- A relação com a turma original é o código TMP-TRANSFERIDOS-{ID_TURMA}.
-- Nenhum ID fixo de aluno é necessário.

START TRANSACTION;

UPDATE alunos AS a
INNER JOIN turmas AS tmp
    ON tmp.id = a.id_turma
   AND tmp.id_escola = 62
   AND tmp.codigo REGEXP '^TMP-TRANSFERIDOS-[0-9]+$'
INNER JOIN turmas AS original
    ON original.id = CAST(
        SUBSTRING(tmp.codigo, CHAR_LENGTH('TMP-TRANSFERIDOS-') + 1)
        AS UNSIGNED
    )
   AND original.id_escola = tmp.id_escola
   AND original.id_serie = 5
SET a.id_turma = original.id,
    a.updated_at = NOW();

SELECT ROW_COUNT() AS quantidade_alunos_restaurados;

SELECT
    tmp.id AS turma_temporaria_id,
    tmp.codigo AS turma_temporaria,
    COUNT(a.id) AS alunos_ainda_na_turma_temporaria
FROM turmas AS tmp
LEFT JOIN alunos AS a ON a.id_turma = tmp.id
WHERE tmp.id_escola = 62
  AND tmp.id_serie = 5
  AND tmp.codigo REGEXP '^TMP-TRANSFERIDOS-[0-9]+$'
GROUP BY tmp.id, tmp.codigo
ORDER BY tmp.codigo;

COMMIT;

-- Não remove a turma automaticamente. Limpe-a somente depois de confirmar
-- que está vazia, usando o script 05-limpar-turmas-temporarias.sql.
