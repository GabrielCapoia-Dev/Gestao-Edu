-- SOMENTE CONSULTAS: nao cria turmas, nao move alunos e nao altera respostas.
-- Execute inteiro no banco atual e envie os resultados antes da consolidacao.
-- O escopo e SRM (serie srm_serie), todas as escolas/avaliacoes relacionadas.

-- BEGIN GRUPOS
WITH srm AS (
    SELECT t.* FROM turmas t JOIN series s ON s.id=t.id_serie
    WHERE s.codigo='srm_serie'
), unidades AS (
    SELECT DISTINCT t.id_escola FROM srm t
    JOIN alunos a ON a.id_turma=t.id
    WHERE a.status IN ('matriculado','pendente')
), estudantes AS (
    SELECT t.id_escola,t.turno,
           SUM(CASE WHEN a.status='matriculado' THEN 1 ELSE 0 END) AS matriculados,
           SUM(CASE WHEN a.status='pendente' THEN 1 ELSE 0 END) AS pendentes,
           SUM(CASE WHEN a.status='remanejado' THEN 1 ELSE 0 END) AS remanejados_historicos
    FROM srm t JOIN alunos a ON a.id_turma=t.id GROUP BY t.id_escola,t.turno
), responsaveis AS (
    SELECT t.id_escola,t.turno,COUNT(DISTINCT tc.professor_id) AS quantidade,
           GROUP_CONCAT(DISTINCT tc.professor_id) AS professor_ids
    FROM srm t JOIN turma_componente_professor tc ON tc.turma_id=t.id
    WHERE tc.tem_professor=1 AND tc.professor_id IS NOT NULL
    GROUP BY t.id_escola,t.turno
)
SELECT e.id AS escola_id,e.nome AS escola,turnos.turno,
       CASE WHEN turnos.turno='manha' THEN 'Manhã' ELSE 'Tarde' END AS turma_destino,
       COALESCE(a.matriculados,0) AS matriculados,COALESCE(a.pendentes,0) AS pendentes,
       COALESCE(a.remanejados_historicos,0) AS historicos_preservar,
       COALESCE(r.quantidade,0) AS quantidade_professores,r.professor_ids,
       CASE WHEN COALESCE(a.matriculados,0)+COALESCE(a.pendentes,0)=0 THEN 'SEM ALUNOS ATIVOS'
            WHEN COALESCE(r.quantidade,0)=0 THEN 'DEFINIR PROFESSOR'
            WHEN r.quantidade>1 THEN 'CONFERIR VINCULOS DE PROFESSOR'
            ELSE 'PROFESSOR IDENTIFICADO' END AS situacao
FROM unidades u JOIN escolas e ON e.id=u.id_escola
CROSS JOIN (SELECT 'manha' AS turno UNION ALL SELECT 'tarde') turnos
LEFT JOIN estudantes a ON a.id_escola=e.id AND a.turno=turnos.turno
LEFT JOIN responsaveis r ON r.id_escola=e.id AND r.turno=turnos.turno
ORDER BY e.nome,turnos.turno
-- END GRUPOS
;

-- Professores atuais: varios IDs podem representar a mesma pessoa/matriculas distintas.
SELECT DISTINCT e.id AS escola_id,e.nome AS escola,t.turno AS turno_turma,
       tc.componente_curricular_id,p.id AS professor_id,p.nome AS professor,
       p.turno AS turno_cadastro_professor,p.ativo,p.matricula,p.user_id
FROM turmas t JOIN series s ON s.id=t.id_serie
JOIN escolas e ON e.id=t.id_escola
JOIN turma_componente_professor tc ON tc.turma_id=t.id
JOIN professores p ON p.id=tc.professor_id
WHERE s.codigo='srm_serie' AND tc.tem_professor=1
ORDER BY e.nome,t.turno,p.nome,p.id;

-- Ciclos que precisam de tratamento historico ou migracao do JSON.
SELECT e.nome AS escola,t.id AS turma_id,t.nome AS turma,t.turno,
       c.id AS ciclo_id,c.avaliacao_id,c.status,c.roster_mode,
       c.operacional_inicializado_em,c.snapshot_evento_atual_id
FROM avaliacao_turma_ciclos c JOIN turmas t ON t.id=c.turma_avaliativa_id
JOIN series s ON s.id=t.id_serie JOIN escolas e ON e.id=t.id_escola
WHERE s.codigo='srm_serie'
  AND (c.status<>'aberta' OR c.operacional_inicializado_em IS NULL OR c.roster_mode<>'dinamico')
ORDER BY e.nome,t.turno,c.avaliacao_id,t.id;

-- Turnos inesperados: nao devem ser convertidos automaticamente em manha/tarde.
SELECT e.nome AS escola,t.id AS turma_id,t.nome,t.turno,COUNT(a.id) AS alunos
FROM turmas t JOIN series s ON s.id=t.id_serie JOIN escolas e ON e.id=t.id_escola
JOIN alunos a ON a.id_turma=t.id AND a.status IN ('matriculado','pendente')
WHERE s.codigo='srm_serie' AND (t.turno IS NULL OR t.turno NOT IN ('manha','tarde'))
GROUP BY e.nome,t.id,t.nome,t.turno;
