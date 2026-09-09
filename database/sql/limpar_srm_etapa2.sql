-- ETAPA 2 SRM. MySQL 8 / phpMyAdmin: executar o arquivo INTEIRO.
-- Exclusao definitiva operacional: SOMENTE 153 turmas antigas e 202 remanejados
-- da etapa 1 auditada. Nao exclui outros remanejados nem as 36 turmas novas.
-- Backup atualizado OBRIGATORIO. Executar SEM usuarios/fila gravando.
-- Arquiva as linhas afetadas em srm2_20260909_* antes da exclusao.
-- Realoca respostas residuais de matriculados para o ciclo novo, sem sobrescrever
-- respostas existentes. Preserva ID/conteudo/autores/datas; incrementa version.
-- Mantem respostas atuais, documentos, snapshots e logs; limpa apenas ponteiros
-- para turma/ciclo historicos removidos. Nao altera payload ou hash de snapshot.
-- Se surgiram dependencias nos remanejados, ABORTA; nao apaga respostas novas.
-- ROLLBACK automatico em erro. Sucesso faz COMMIT. Reexecucao concluida nao opera.
-- Nao desliga FOREIGN_KEY_CHECKS. Nao exclui dados das tabelas de auditoria.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS srm2_20260909_execucao (
    id INT NOT NULL PRIMARY KEY, concluida_em DATETIME NOT NULL,
    alunos_excluidos INT NOT NULL, turmas_excluidas INT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm2_20260909_turmas LIKE turmas;
CREATE TABLE IF NOT EXISTS srm2_20260909_alunos LIKE alunos;
CREATE TABLE IF NOT EXISTS srm2_20260909_ciclos LIKE avaliacao_turma_ciclos;
CREATE TABLE IF NOT EXISTS srm2_20260909_tokens LIKE avaliacao_turma_tokens_escrita;
CREATE TABLE IF NOT EXISTS srm2_20260909_documentos LIKE avaliacao_aluno_documentos;
CREATE TABLE IF NOT EXISTS srm2_20260909_snapshots LIKE avaliacao_aluno_snapshots;
CREATE TABLE IF NOT EXISTS srm2_20260909_eventos LIKE avaliacao_snapshot_eventos;
CREATE TABLE IF NOT EXISTS srm2_20260909_exportacoes LIKE avaliacao_exportacoes;
CREATE TABLE IF NOT EXISTS srm2_20260909_resumos LIKE avaliacao_snapshot_resumos_componentes;
CREATE TABLE IF NOT EXISTS srm2_20260909_vinculos LIKE turma_componente_professor;
CREATE TABLE IF NOT EXISTS srm2_20260909_funcoes LIKE servidor_funcao_turma;
CREATE TABLE IF NOT EXISTS srm2_20260909_avaliacao_turma LIKE avaliacao_turma;
CREATE TABLE IF NOT EXISTS srm2_20260909_dashboard LIKE avaliacao_dashboard_turma_resumos;
CREATE TABLE IF NOT EXISTS srm2_20260909_respostas_relocadas LIKE avaliacao_respostas_operacionais;
DROP PROCEDURE IF EXISTS limpar_srm_etapa2_20260909;
DELIMITER $$
CREATE PROCEDURE limpar_srm_etapa2_20260909() main: BEGIN
    DECLARE v_lock INT DEFAULT 0;
    DECLARE v_dummy BIGINT DEFAULT 0;
    DECLARE v_nome_lock VARCHAR(64);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        DO RELEASE_LOCK(v_nome_lock);
        RESIGNAL;
    END;
    SET v_nome_lock=CONCAT('srm:',LEFT(SHA2(DATABASE(),256),48));
    SELECT GET_LOCK(v_nome_lock,0) INTO v_lock;
    IF v_lock IS NULL OR v_lock<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Outra operacao SRM em execucao.';
    END IF;
    START TRANSACTION;
    IF EXISTS(SELECT 1 FROM srm2_20260909_execucao WHERE id=1) THEN
        COMMIT;
        DO RELEASE_LOCK(v_nome_lock);
        SELECT 'Etapa 2 ja concluida. Nenhuma alteracao realizada.' AS resultado;
        LEAVE main;
    END IF;
    IF @@session.foreign_key_checks<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FOREIGN_KEY_CHECKS deve estar ligado.';
    END IF;
    IF EXISTS(SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Banco tem triggers nao auditados. Revisar antes de excluir.';
    END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='alunos' AND k.COLUMN_NAME='aluno_origem_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: alunos.aluno_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='alunos' AND k.COLUMN_NAME='id_turma'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: alunos.id_turma'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='alunos' AND k.COLUMN_NAME='pendencia_origem_aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: alunos.pendencia_origem_aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='alunos' AND k.COLUMN_NAME='turma_origem_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: alunos.turma_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_documentos' AND k.COLUMN_NAME='aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_documentos.aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_documentos' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_documentos.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_documentos_historico' AND k.COLUMN_NAME='aluno_origem_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_documentos_historico.aluno_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_snapshots' AND k.COLUMN_NAME='aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_snapshots.aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_snapshots' AND k.COLUMN_NAME='ciclo_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_ciclos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_snapshots.ciclo_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_snapshots' AND k.COLUMN_NAME='turma_avaliativa_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_snapshots.turma_avaliativa_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_snapshots' AND k.COLUMN_NAME='turma_destino_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_snapshots.turma_destino_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_aluno_snapshots' AND k.COLUMN_NAME='turma_origem_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_aluno_snapshots.turma_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_dashboard_turma_resumos' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_dashboard_turma_resumos.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_exportacoes' AND k.COLUMN_NAME='aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_exportacoes.aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_exportacoes' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_exportacoes.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_informacoes_operacionais' AND k.COLUMN_NAME='aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_informacoes_operacionais.aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_informacoes_operacionais' AND k.COLUMN_NAME='ciclo_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_ciclos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_informacoes_operacionais.ciclo_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_informacoes_operacionais' AND k.COLUMN_NAME='token_escrita_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_tokens_escrita' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_informacoes_operacionais.token_escrita_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_informacoes_operacionais' AND k.COLUMN_NAME='turma_avaliativa_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_informacoes_operacionais.turma_avaliativa_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_informacoes_operacionais' AND k.COLUMN_NAME='turma_origem_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_informacoes_operacionais.turma_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_migracao_inconsistencias' AND k.COLUMN_NAME='aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_migracao_inconsistencias.aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_respostas_operacionais' AND k.COLUMN_NAME='aluno_id'
        AND k.REFERENCED_TABLE_NAME='alunos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_respostas_operacionais.aluno_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_respostas_operacionais' AND k.COLUMN_NAME='ciclo_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_ciclos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_respostas_operacionais.ciclo_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_respostas_operacionais' AND k.COLUMN_NAME='token_escrita_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_tokens_escrita' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_respostas_operacionais.token_escrita_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_respostas_operacionais' AND k.COLUMN_NAME='turma_avaliativa_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_respostas_operacionais.turma_avaliativa_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_respostas_operacionais' AND k.COLUMN_NAME='turma_origem_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_respostas_operacionais.turma_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_snapshot_eventos' AND k.COLUMN_NAME='ciclo_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_ciclos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_snapshot_eventos.ciclo_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_snapshot_eventos' AND k.COLUMN_NAME='turma_avaliativa_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_snapshot_eventos.turma_avaliativa_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_snapshot_eventos' AND k.COLUMN_NAME='turma_destino_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_snapshot_eventos.turma_destino_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_snapshot_eventos' AND k.COLUMN_NAME='turma_origem_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='SET NULL') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_snapshot_eventos.turma_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_snapshot_resumos_componentes' AND k.COLUMN_NAME='ciclo_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_ciclos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_snapshot_resumos_componentes.ciclo_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_turma' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_turma.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_turma_ciclos' AND k.COLUMN_NAME='turma_avaliativa_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_turma_ciclos.turma_avaliativa_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_turma_ciclos' AND k.COLUMN_NAME='turma_origem_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_turma_ciclos.turma_origem_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='avaliacao_turma_tokens_escrita' AND k.COLUMN_NAME='ciclo_id'
        AND k.REFERENCED_TABLE_NAME='avaliacao_turma_ciclos' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: avaliacao_turma_tokens_escrita.ciclo_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='evento_calendario_escola_turma' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: evento_calendario_escola_turma.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='evento_transporte_alocacao_turma' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='RESTRICT') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: evento_transporte_alocacao_turma.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='professor_funcao_turma' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: professor_funcao_turma.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='professor_turma' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: professor_turma.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='servidor_funcao_turma' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: servidor_funcao_turma.turma_id'; END IF;
    IF NOT EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='turma_componente_professor' AND k.COLUMN_NAME='turma_id'
        AND k.REFERENCED_TABLE_NAME='turmas' AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FK diferente: turma_componente_professor.turma_id'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE()
        AND REFERENCED_TABLE_NAME IN ('alunos','avaliacao_dashboard_turma_resumos','avaliacao_snapshot_resumos_componentes','avaliacao_turma','avaliacao_turma_ciclos','avaliacao_turma_tokens_escrita','servidor_funcao_turma','turma_componente_professor','turmas'))<>41 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quantidade de FKs mudou. Revisar dependencias.'; END IF;
    IF EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('alunos','avaliacao_aluno_documentos','avaliacao_aluno_snapshots','avaliacao_dashboard_turma_resumos','avaliacao_exportacoes','avaliacao_informacoes_operacionais','avaliacao_respostas_operacionais','avaliacao_snapshot_eventos','avaliacao_snapshot_resumos_componentes','avaliacao_turma','avaliacao_turma_ciclos','avaliacao_turma_tokens_escrita','servidor_funcao_turma','srm2_20260909_alunos','srm2_20260909_avaliacao_turma','srm2_20260909_ciclos','srm2_20260909_dashboard','srm2_20260909_documentos','srm2_20260909_eventos','srm2_20260909_execucao','srm2_20260909_exportacoes','srm2_20260909_funcoes','srm2_20260909_respostas_relocadas','srm2_20260909_resumos','srm2_20260909_snapshots','srm2_20260909_tokens','srm2_20260909_turmas','srm2_20260909_vinculos','turma_componente_professor','turmas') AND ENGINE<>'InnoDB') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Tabela nao transacional. Operacao cancelada.'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alunos')<>32
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alunos' AND COLUMN_NAME NOT IN ('id','id_turma','tipo_vinculo','permite_contra_turno','status','status_alterado_em','status_alterado_por','status_motivo','aluno_origem_id','turma_origem_id','movimentacao_origem','pendencia_origem_aluno_id','id_professor','nome','cgm','cgm_matricula_ativa','cgm_contra_turno_ativo','cgm_unidade_matricula_ativa','sexo','data_matricula','data_nascimento','dificuldade_aprendizagem','frequenta_srm','encaminhado_para_sme','ja_foi_retido','encaminhado_para_caei','status_fonoaudiologo','status_psicologo','status_psicopedagogo','avanco_caei','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: alunos'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_aluno_documentos')<>23
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_aluno_documentos' AND COLUMN_NAME NOT IN ('id','avaliacao_id','aluno_id','cgm','turma_id','escola_id','serie_id','payload','responsaveis_snapshot','responsaveis_snapshot_em','alternativa_ids','professor_ids','pauta_ids_respondidas','total_pautas_esperadas','total_pautas_respondidas','total_infos_complementares','status_preenchimento','observacoes_obrigatorias_pendentes','primeira_resposta_em','ultima_resposta_em','version','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_aluno_documentos'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_aluno_snapshots')<>19
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_aluno_snapshots' AND COLUMN_NAME NOT IN ('id','evento_id','avaliacao_id','ciclo_id','turma_avaliativa_id','turma_origem_id','turma_destino_id','aluno_id','cgm','tipo','schema_version','payload','payload_hash','total_pautas_esperadas','total_respostas','total_informacoes','tamanho_bytes','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_aluno_snapshots'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_dashboard_turma_resumos')<>14
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_dashboard_turma_resumos' AND COLUMN_NAME NOT IN ('id','avaliacao_id','turma_id','componente_curricular_id','componente_chave','preenchimentos_esperados','preenchimentos_respondidos','alunos_total','alunos_pendentes','pautas_total','ultima_resposta_em','calculado_em','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_dashboard_turma_resumos'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_exportacoes')<>14
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_exportacoes' AND COLUMN_NAME NOT IN ('id','avaliacao_id','escola_id','turma_id','aluno_id','user_id','escopo','formato','quantidade_alunos','quantidade_paginas','parametros','exportado_em','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_exportacoes'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_informacoes_operacionais')<>14
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_informacoes_operacionais' AND COLUMN_NAME NOT IN ('id','token_escrita_id','ciclo_id','avaliacao_id','turma_avaliativa_id','turma_origem_id','aluno_id','componente_curricular_id','componente_chave','professor_id','texto','version','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_informacoes_operacionais'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_respostas_operacionais')<>16
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_respostas_operacionais' AND COLUMN_NAME NOT IN ('id','token_escrita_id','ciclo_id','avaliacao_id','turma_avaliativa_id','turma_origem_id','aluno_id','pauta_id','componente_curricular_id','professor_id','alternativa_id','observacao','respondido_em','version','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_respostas_operacionais'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_snapshot_eventos')<>21
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_snapshot_eventos' AND COLUMN_NAME NOT IN ('id','idempotency_key','tipo','ciclo_id','avaliacao_id','turma_avaliativa_id','turma_origem_id','turma_destino_id','versao','schema_version','criado_por','criado_por_snapshot','motivo','total_alunos','total_respostas_esperadas','total_respostas_geradas','tamanho_bytes','payload_hash_agregado','publicado_em','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_snapshot_eventos'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_snapshot_resumos_componentes')<>10
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_snapshot_resumos_componentes' AND COLUMN_NAME NOT IN ('id','evento_id','ciclo_id','componente_curricular_id','respostas_esperadas','respostas_concluidas','respostas_pendentes','percentual','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_snapshot_resumos_componentes'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_turma')<>5
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_turma' AND COLUMN_NAME NOT IN ('id','avaliacao_id','turma_id','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_turma'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_turma_ciclos')<>20
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_turma_ciclos' AND COLUMN_NAME NOT IN ('id','avaliacao_id','turma_avaliativa_id','turma_origem_id','status','roster_mode','versao_conclusao','snapshot_evento_atual_id','operacional_inicializado_em','legado_documentos_migrados','legado_migracao_hash','concluida_em','concluida_por','concluida_por_snapshot','reaberta_em','reaberta_por','reaberta_por_snapshot','motivo_reabertura','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_turma_ciclos'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_turma_tokens_escrita')<>5
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avaliacao_turma_tokens_escrita' AND COLUMN_NAME NOT IN ('id','ciclo_id','generation_uuid','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: avaliacao_turma_tokens_escrita'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='servidor_funcao_turma')<>9
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='servidor_funcao_turma' AND COLUMN_NAME NOT IN ('id','servidor_funcao_administrativa_id','turma_id','principal','status','data_inicio','data_fim','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: servidor_funcao_turma'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='turma_componente_professor')<>7
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='turma_componente_professor' AND COLUMN_NAME NOT IN ('id','turma_id','componente_curricular_id','professor_id','tem_professor','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: turma_componente_professor'; END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='turmas')<>8
        OR EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='turmas' AND COLUMN_NAME NOT IN ('id','codigo','nome','turno','id_serie','id_escola','created_at','updated_at')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Colunas alteradas: turmas'; END IF;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_alunos;
    CREATE TEMPORARY TABLE tmp_srm2_alunos LIKE alunos;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_respostas;
    CREATE TEMPORARY TABLE tmp_srm2_respostas LIKE avaliacao_respostas_operacionais;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_infos;
    CREATE TEMPORARY TABLE tmp_srm2_infos LIKE avaliacao_informacoes_operacionais;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_documentos;
    CREATE TEMPORARY TABLE tmp_srm2_documentos LIKE avaliacao_aluno_documentos;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_vinculos;
    CREATE TEMPORARY TABLE tmp_srm2_vinculos LIKE turma_componente_professor;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_ciclos;
    CREATE TEMPORARY TABLE tmp_srm2_ciclos LIKE avaliacao_turma_ciclos;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_tokens;
    CREATE TEMPORARY TABLE tmp_srm2_tokens LIKE avaliacao_turma_tokens_escrita;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_turmas;
    CREATE TEMPORARY TABLE tmp_srm2_turmas LIKE turmas;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_snapshots;
    CREATE TEMPORARY TABLE tmp_srm2_snapshots LIKE avaliacao_aluno_snapshots;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_eventos;
    CREATE TEMPORARY TABLE tmp_srm2_eventos LIKE avaliacao_snapshot_eventos;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm2_totais;
    CREATE TEMPORARY TABLE tmp_srm2_totais(tabela VARCHAR(64) PRIMARY KEY,quantidade BIGINT NOT NULL) ENGINE=InnoDB;
    SELECT COUNT(*) INTO v_dummy FROM turmas WHERE id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR id IN (SELECT turma_id FROM srm_20260909_destinos) FOR UPDATE;
    SELECT COUNT(*) INTO v_dummy FROM alunos WHERE id_turma IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR id IN (SELECT id FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente')) FOR UPDATE;
    SELECT COUNT(*) INTO v_dummy FROM avaliacao_turma_ciclos WHERE turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR turma_avaliativa_id IN (SELECT turma_id FROM srm_20260909_destinos) FOR UPDATE;
    SELECT COUNT(*) INTO v_dummy FROM avaliacao_turma_tokens_escrita WHERE ciclo_id IN (SELECT id FROM avaliacao_turma_ciclos WHERE turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR turma_avaliativa_id IN (SELECT turma_id FROM srm_20260909_destinos)) FOR UPDATE;
    -- Etapa 1 nao confirmada
    IF (SELECT NOT EXISTS(SELECT 1 FROM srm_20260909_execucao WHERE id=1 AND concluida_em IS NOT NULL AND alunos_movidos=408)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Etapa 1 nao confirmada'; END IF;
    -- Mapa SRM diferente do backup
    IF (SELECT (SELECT COUNT(*) FROM srm_20260909_mapa)<>153 OR (SELECT COUNT(*) FROM srm_20260909_ciclos_mapa)<>153 OR (SELECT COUNT(*) FROM srm_20260909_destinos)<>36) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Mapa SRM diferente do backup'; END IF;
    -- Origem ou destino mudou de escola serie ou turno
    IF (SELECT EXISTS(SELECT 1 FROM srm_20260909_mapa m
        LEFT JOIN turmas o ON o.id=m.turma_origem_id LEFT JOIN turmas d ON d.id=m.turma_destino_id
        LEFT JOIN series s ON s.id=o.id_serie WHERE o.id IS NULL OR d.id IS NULL OR o.id=d.id
        OR s.codigo<>'srm_serie' OR o.id_escola<>d.id_escola OR o.id_serie<>d.id_serie OR o.turno<>d.turno
        OR d.id NOT IN (SELECT turma_id FROM srm_20260909_destinos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Origem ou destino mudou de escola serie ou turno'; END IF;
    -- Aluno atual ausente ou mudou de turma: revisar backup
    IF (SELECT EXISTS(SELECT 1 FROM srm_20260909_alunos_antes b
        JOIN srm_20260909_mapa m ON m.turma_origem_id=b.id_turma LEFT JOIN alunos r ON r.id=b.id
        WHERE b.status IN ('matriculado','pendente') AND (r.id IS NULL OR r.id_turma<>m.turma_destino_id
        OR NOT(r.cgm<=>b.cgm) OR NOT(r.tipo_vinculo<=>b.tipo_vinculo) OR r.status NOT IN ('matriculado','pendente')))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aluno atual ausente ou mudou de turma: revisar backup'; END IF;
    -- Turma antiga recebeu aluno que nao e remanejado
    IF (SELECT EXISTS(SELECT 1 FROM alunos WHERE id_turma IN (SELECT turma_origem_id FROM srm_20260909_mapa) AND (status IS NULL OR status<>'remanejado'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Turma antiga recebeu aluno que nao e remanejado'; END IF;
    -- Remanejados divergentes do backup revisado
    IF (SELECT (SELECT COUNT(*) FROM alunos WHERE id_turma IN (SELECT turma_origem_id FROM srm_20260909_mapa) AND status='remanejado')<>202 OR EXISTS(SELECT 1 FROM alunos r LEFT JOIN srm_20260909_alunos_antes b ON b.id=r.id WHERE r.id_turma IN (SELECT turma_origem_id FROM srm_20260909_mapa) AND (b.id IS NULL OR b.status<>'remanejado' OR NOT(r.cgm<=>b.cgm) OR r.id_turma<>b.id_turma))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remanejados divergentes do backup revisado'; END IF;
    -- Remanejado sem matricula atual SRM na mesma escola
    IF (SELECT EXISTS(SELECT 1 FROM alunos o JOIN turmas ot ON ot.id=o.id_turma
        WHERE o.id_turma IN (SELECT turma_origem_id FROM srm_20260909_mapa) AND NOT EXISTS(SELECT 1 FROM alunos a JOIN turmas t ON t.id=a.id_turma
        WHERE a.cgm=o.cgm AND a.status IN ('matriculado','pendente') AND a.id_turma IN (SELECT turma_id FROM srm_20260909_destinos)
        AND t.id_escola=ot.id_escola AND t.id_serie=ot.id_serie))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remanejado sem matricula atual SRM na mesma escola'; END IF;
    -- Auditoria etapa 2 ja contem dados: turmas
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_turmas)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: turmas'; END IF;
    -- backup turmas
    INSERT INTO srm2_20260909_turmas SELECT * FROM turmas WHERE id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: alunos
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_alunos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: alunos'; END IF;
    -- backup alunos
    INSERT INTO srm2_20260909_alunos SELECT * FROM alunos WHERE id_turma IN (SELECT turma_origem_id FROM srm_20260909_mapa) AND status='remanejado';
    -- Auditoria etapa 2 ja contem dados: ciclos
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_ciclos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: ciclos'; END IF;
    -- backup ciclos
    INSERT INTO srm2_20260909_ciclos SELECT * FROM avaliacao_turma_ciclos WHERE id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa);
    -- Auditoria etapa 2 ja contem dados: tokens
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_tokens)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: tokens'; END IF;
    -- backup tokens
    INSERT INTO srm2_20260909_tokens SELECT * FROM avaliacao_turma_tokens_escrita WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa);
    -- Auditoria etapa 2 ja contem dados: documentos
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_documentos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: documentos'; END IF;
    -- backup documentos
    INSERT INTO srm2_20260909_documentos SELECT * FROM avaliacao_aluno_documentos WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: snapshots
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_snapshots)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: snapshots'; END IF;
    -- backup snapshots
    INSERT INTO srm2_20260909_snapshots SELECT * FROM avaliacao_aluno_snapshots WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa) OR turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR turma_destino_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: eventos
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_eventos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: eventos'; END IF;
    -- backup eventos
    INSERT INTO srm2_20260909_eventos SELECT * FROM avaliacao_snapshot_eventos WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa) OR turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR turma_destino_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: exportacoes
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_exportacoes)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: exportacoes'; END IF;
    -- backup exportacoes
    INSERT INTO srm2_20260909_exportacoes SELECT * FROM avaliacao_exportacoes WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: resumos
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_resumos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: resumos'; END IF;
    -- backup resumos
    INSERT INTO srm2_20260909_resumos SELECT * FROM avaliacao_snapshot_resumos_componentes WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa);
    -- Auditoria etapa 2 ja contem dados: vinculos
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_vinculos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: vinculos'; END IF;
    -- backup vinculos
    INSERT INTO srm2_20260909_vinculos SELECT * FROM turma_componente_professor WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: funcoes
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_funcoes)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: funcoes'; END IF;
    -- backup funcoes
    INSERT INTO srm2_20260909_funcoes SELECT * FROM servidor_funcao_turma WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: avaliacao_turma
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_avaliacao_turma)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: avaliacao_turma'; END IF;
    -- backup avaliacao_turma
    INSERT INTO srm2_20260909_avaliacao_turma SELECT * FROM avaliacao_turma WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: dashboard
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_dashboard)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: dashboard'; END IF;
    -- backup dashboard
    INSERT INTO srm2_20260909_dashboard SELECT * FROM avaliacao_dashboard_turma_resumos WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- Auditoria etapa 2 ja contem dados: respostas_relocadas
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_respostas_relocadas)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria etapa 2 ja contem dados: respostas_relocadas'; END IF;
    -- backup respostas_relocadas
    INSERT INTO srm2_20260909_respostas_relocadas SELECT * FROM avaliacao_respostas_operacionais WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa);
    -- Ciclo antigo diferente do mapeamento
    IF (SELECT EXISTS(SELECT 1 FROM avaliacao_turma_ciclos r
        WHERE (r.turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR r.turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa)) AND r.id NOT IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa))
        OR EXISTS(SELECT 1 FROM srm_20260909_ciclos_mapa m LEFT JOIN avaliacao_turma_ciclos o ON o.id=m.ciclo_origem_id
        LEFT JOIN avaliacao_turma_ciclos n ON n.id=m.ciclo_destino_id
        WHERE o.id IS NULL OR n.id IS NULL OR o.avaliacao_id<>n.avaliacao_id
        OR o.status='concluida' OR o.operacional_inicializado_em IS NULL OR n.operacional_inicializado_em IS NULL
        OR o.turma_avaliativa_id NOT IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR o.turma_origem_id NOT IN (SELECT turma_origem_id FROM srm_20260909_mapa) OR n.turma_avaliativa_id NOT IN (SELECT turma_id FROM srm_20260909_destinos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ciclo antigo diferente do mapeamento'; END IF;
    -- Resposta residual sem destino seguro
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_respostas_relocadas b
        LEFT JOIN alunos a ON a.id=b.aluno_id
        LEFT JOIN srm_20260909_alunos_antes ao ON ao.id=b.aluno_id
        LEFT JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        LEFT JOIN avaliacao_turma_ciclos oc ON oc.id=cm.ciclo_origem_id
        LEFT JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        LEFT JOIN avaliacao_turma_tokens_escrita ot ON ot.id=b.token_escrita_id
        LEFT JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        WHERE a.id IS NULL OR ao.id IS NULL OR ao.status NOT IN ('matriculado','pendente')
        OR a.status<>'matriculado' OR oc.id IS NULL OR nc.id IS NULL OR nt.id IS NULL OR ot.id IS NULL
        OR ot.ciclo_id<>oc.id OR b.avaliacao_id<>oc.avaliacao_id OR b.avaliacao_id<>nc.avaliacao_id
        OR ao.id_turma<>oc.turma_avaliativa_id OR a.id_turma<>nc.turma_avaliativa_id
        OR b.turma_avaliativa_id<>oc.turma_avaliativa_id OR b.turma_origem_id<>oc.turma_origem_id
        OR nc.turma_origem_id<>a.id_turma OR nc.status<>'aberta' OR nc.roster_mode<>'dinamico'
        OR nc.snapshot_evento_atual_id IS NOT NULL OR nc.operacional_inicializado_em IS NULL)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Resposta residual sem destino seguro'; END IF;
    -- Conflito: resposta residual ja existe no destino
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_respostas_relocadas b
        JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        JOIN avaliacao_respostas_operacionais n ON n.ciclo_id=cm.ciclo_destino_id
        AND n.aluno_id=b.aluno_id AND n.pauta_id=b.pauta_id)
        OR EXISTS(SELECT 1 FROM srm2_20260909_respostas_relocadas b JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        GROUP BY cm.ciclo_destino_id,b.aluno_id,b.pauta_id HAVING COUNT(*)>1)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflito: resposta residual ja existe no destino'; END IF;
    -- realocar respostas residuais preservando ID conteudo autores e datas
    UPDATE avaliacao_respostas_operacionais SET ciclo_id=(SELECT nc.id FROM srm2_20260909_respostas_relocadas b JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        WHERE b.id=avaliacao_respostas_operacionais.id),token_escrita_id=(SELECT nt.id FROM srm2_20260909_respostas_relocadas b JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        WHERE b.id=avaliacao_respostas_operacionais.id),turma_avaliativa_id=(SELECT nc.turma_avaliativa_id FROM srm2_20260909_respostas_relocadas b JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        WHERE b.id=avaliacao_respostas_operacionais.id),turma_origem_id=(SELECT nc.turma_origem_id FROM srm2_20260909_respostas_relocadas b JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id
        JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        WHERE b.id=avaliacao_respostas_operacionais.id),version=version+1 WHERE id IN (SELECT id FROM srm2_20260909_respostas_relocadas);
    -- Preservacao da resposta residual falhou
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_respostas_relocadas b
        JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        LEFT JOIN avaliacao_respostas_operacionais r ON r.id=b.id
        WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`token_escrita_id` AS BINARY) <=> CAST(nt.id AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(nc.id AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(nc.turma_avaliativa_id AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(nc.turma_origem_id AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`pauta_id` AS BINARY) <=> CAST(b.`pauta_id` AS BINARY)) AND (CAST(r.`componente_curricular_id` AS BINARY) <=> CAST(b.`componente_curricular_id` AS BINARY)) AND (CAST(r.`professor_id` AS BINARY) <=> CAST(b.`professor_id` AS BINARY)) AND (CAST(r.`alternativa_id` AS BINARY) <=> CAST(b.`alternativa_id` AS BINARY)) AND (CAST(r.`observacao` AS BINARY) <=> CAST(b.`observacao` AS BINARY)) AND (CAST(r.`respondido_em` AS BINARY) <=> CAST(b.`respondido_em` AS BINARY)) AND (CAST(r.`version` AS BINARY) <=> CAST(b.version+1 AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao da resposta residual falhou'; END IF;
    -- Dependencia nova: alunos.aluno_origem_id
    IF (SELECT EXISTS(SELECT 1 FROM `alunos` WHERE `aluno_origem_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: alunos.aluno_origem_id'; END IF;
    -- Dependencia nova: alunos.pendencia_origem_aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `alunos` WHERE `pendencia_origem_aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: alunos.pendencia_origem_aluno_id'; END IF;
    -- Dependencia nova: alunos.turma_origem_id
    IF (SELECT EXISTS(SELECT 1 FROM `alunos` WHERE `turma_origem_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: alunos.turma_origem_id'; END IF;
    -- Dependencia nova: avaliacao_aluno_documentos.aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_aluno_documentos` WHERE `aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_aluno_documentos.aluno_id'; END IF;
    -- Dependencia nova: avaliacao_aluno_documentos_historico.aluno_destino_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_aluno_documentos_historico` WHERE `aluno_destino_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_aluno_documentos_historico.aluno_destino_id'; END IF;
    -- Dependencia nova: avaliacao_aluno_documentos_historico.aluno_origem_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_aluno_documentos_historico` WHERE `aluno_origem_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_aluno_documentos_historico.aluno_origem_id'; END IF;
    -- Dependencia nova: avaliacao_aluno_documentos_historico.turma_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_aluno_documentos_historico` WHERE `turma_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_aluno_documentos_historico.turma_id'; END IF;
    -- Dependencia nova: avaliacao_aluno_snapshots.aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_aluno_snapshots` WHERE `aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_aluno_snapshots.aluno_id'; END IF;
    -- Dependencia nova: avaliacao_exportacoes.aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_exportacoes` WHERE `aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_exportacoes.aluno_id'; END IF;
    -- Dependencia nova: avaliacao_informacoes_operacionais.aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_informacoes_operacionais` WHERE `aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_informacoes_operacionais.aluno_id'; END IF;
    -- Dependencia nova: avaliacao_informacoes_operacionais.ciclo_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_informacoes_operacionais` WHERE `ciclo_id` IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_informacoes_operacionais.ciclo_id'; END IF;
    -- Dependencia nova: avaliacao_informacoes_operacionais.token_escrita_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_informacoes_operacionais` WHERE `token_escrita_id` IN (SELECT id FROM srm2_20260909_tokens))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_informacoes_operacionais.token_escrita_id'; END IF;
    -- Dependencia nova: avaliacao_informacoes_operacionais.turma_avaliativa_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_informacoes_operacionais` WHERE `turma_avaliativa_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_informacoes_operacionais.turma_avaliativa_id'; END IF;
    -- Dependencia nova: avaliacao_informacoes_operacionais.turma_origem_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_informacoes_operacionais` WHERE `turma_origem_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_informacoes_operacionais.turma_origem_id'; END IF;
    -- Dependencia nova: avaliacao_migracao_inconsistencias.aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_migracao_inconsistencias` WHERE `aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_migracao_inconsistencias.aluno_id'; END IF;
    -- Dependencia nova: avaliacao_respostas_operacionais.aluno_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_respostas_operacionais` WHERE `aluno_id` IN (SELECT id FROM srm2_20260909_alunos))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_respostas_operacionais.aluno_id'; END IF;
    -- Dependencia nova: avaliacao_respostas_operacionais.ciclo_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_respostas_operacionais` WHERE `ciclo_id` IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_respostas_operacionais.ciclo_id'; END IF;
    -- Dependencia nova: avaliacao_respostas_operacionais.token_escrita_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_respostas_operacionais` WHERE `token_escrita_id` IN (SELECT id FROM srm2_20260909_tokens))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_respostas_operacionais.token_escrita_id'; END IF;
    -- Dependencia nova: avaliacao_respostas_operacionais.turma_avaliativa_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_respostas_operacionais` WHERE `turma_avaliativa_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_respostas_operacionais.turma_avaliativa_id'; END IF;
    -- Dependencia nova: avaliacao_respostas_operacionais.turma_origem_id
    IF (SELECT EXISTS(SELECT 1 FROM `avaliacao_respostas_operacionais` WHERE `turma_origem_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: avaliacao_respostas_operacionais.turma_origem_id'; END IF;
    -- Dependencia nova: evento_calendario_escola_turma.turma_id
    IF (SELECT EXISTS(SELECT 1 FROM `evento_calendario_escola_turma` WHERE `turma_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: evento_calendario_escola_turma.turma_id'; END IF;
    -- Dependencia nova: evento_transporte_alocacao_turma.turma_id
    IF (SELECT EXISTS(SELECT 1 FROM `evento_transporte_alocacao_turma` WHERE `turma_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: evento_transporte_alocacao_turma.turma_id'; END IF;
    -- Dependencia nova: professor_funcao_turma.turma_id
    IF (SELECT EXISTS(SELECT 1 FROM `professor_funcao_turma` WHERE `turma_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: professor_funcao_turma.turma_id'; END IF;
    -- Dependencia nova: professor_turma.turma_id
    IF (SELECT EXISTS(SELECT 1 FROM `professor_turma` WHERE `turma_id` IN (SELECT turma_origem_id FROM srm_20260909_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dependencia nova: professor_turma.turma_id'; END IF;
    -- Documento atual sem destino coerente
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_documentos d
        LEFT JOIN alunos a ON a.id=d.aluno_id LEFT JOIN srm_20260909_mapa m ON m.turma_origem_id=d.turma_id
        WHERE a.id IS NULL OR a.id NOT IN (SELECT id FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente')) OR a.id_turma<>m.turma_destino_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento atual sem destino coerente'; END IF;
    -- proteger alunos
    INSERT INTO tmp_srm2_alunos SELECT * FROM alunos WHERE id IN (SELECT id FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente'));
    -- proteger respostas
    INSERT INTO tmp_srm2_respostas SELECT * FROM avaliacao_respostas_operacionais WHERE aluno_id IN (SELECT id FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente'));
    -- proteger infos
    INSERT INTO tmp_srm2_infos SELECT * FROM avaliacao_informacoes_operacionais WHERE aluno_id IN (SELECT id FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente'));
    -- proteger documentos
    INSERT INTO tmp_srm2_documentos SELECT * FROM avaliacao_aluno_documentos WHERE aluno_id IN (SELECT id FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente'));
    -- proteger vinculos
    INSERT INTO tmp_srm2_vinculos SELECT * FROM turma_componente_professor WHERE turma_id IN (SELECT turma_id FROM srm_20260909_destinos);
    -- proteger ciclos
    INSERT INTO tmp_srm2_ciclos SELECT * FROM avaliacao_turma_ciclos WHERE turma_avaliativa_id IN (SELECT turma_id FROM srm_20260909_destinos);
    -- proteger tokens
    INSERT INTO tmp_srm2_tokens SELECT * FROM avaliacao_turma_tokens_escrita WHERE ciclo_id IN (SELECT id FROM avaliacao_turma_ciclos WHERE turma_avaliativa_id IN (SELECT turma_id FROM srm_20260909_destinos));
    -- proteger turmas
    INSERT INTO tmp_srm2_turmas SELECT * FROM turmas WHERE id IN (SELECT turma_id FROM srm_20260909_destinos);
    -- proteger snapshots
    INSERT INTO tmp_srm2_snapshots SELECT * FROM avaliacao_aluno_snapshots WHERE 1=1;
    -- proteger eventos
    INSERT INTO tmp_srm2_eventos SELECT * FROM avaliacao_snapshot_eventos WHERE 1=1;
    -- total alunos
    INSERT INTO tmp_srm2_totais SELECT 'alunos',COUNT(*) FROM alunos;
    -- total turmas
    INSERT INTO tmp_srm2_totais SELECT 'turmas',COUNT(*) FROM turmas;
    -- total avaliacao_respostas_operacionais
    INSERT INTO tmp_srm2_totais SELECT 'avaliacao_respostas_operacionais',COUNT(*) FROM avaliacao_respostas_operacionais;
    -- total avaliacao_informacoes_operacionais
    INSERT INTO tmp_srm2_totais SELECT 'avaliacao_informacoes_operacionais',COUNT(*) FROM avaliacao_informacoes_operacionais;
    -- total avaliacao_aluno_documentos
    INSERT INTO tmp_srm2_totais SELECT 'avaliacao_aluno_documentos',COUNT(*) FROM avaliacao_aluno_documentos;
    -- total avaliacao_aluno_snapshots
    INSERT INTO tmp_srm2_totais SELECT 'avaliacao_aluno_snapshots',COUNT(*) FROM avaliacao_aluno_snapshots;
    -- total avaliacao_snapshot_eventos
    INSERT INTO tmp_srm2_totais SELECT 'avaliacao_snapshot_eventos',COUNT(*) FROM avaliacao_snapshot_eventos;
    -- realocar documentos sem alterar respostas
    UPDATE avaliacao_aluno_documentos SET turma_id=(
        SELECT m.turma_destino_id FROM srm_20260909_mapa m WHERE m.turma_origem_id=avaliacao_aluno_documentos.turma_id)
        WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- preservar funcoes administrativas no destino
    INSERT INTO servidor_funcao_turma
        (servidor_funcao_administrativa_id,turma_id,principal,status,data_inicio,data_fim,created_at,updated_at)
        SELECT s.servidor_funcao_administrativa_id,m.turma_destino_id,s.principal,s.status,s.data_inicio,s.data_fim,MIN(s.created_at),MAX(s.updated_at)
        FROM srm2_20260909_funcoes s JOIN srm_20260909_mapa m ON m.turma_origem_id=s.turma_id
        WHERE NOT EXISTS(SELECT 1 FROM servidor_funcao_turma n WHERE n.turma_id=m.turma_destino_id AND (n.servidor_funcao_administrativa_id<=>s.servidor_funcao_administrativa_id) AND (n.principal<=>s.principal) AND (n.status<=>s.status) AND (n.data_inicio<=>s.data_inicio) AND (n.data_fim<=>s.data_fim))
        GROUP BY s.servidor_funcao_administrativa_id,m.turma_destino_id,s.principal,s.status,s.data_inicio,s.data_fim;
    -- desvincular referencia historica avaliacao_aluno_snapshots.ciclo_id
    UPDATE avaliacao_aluno_snapshots SET ciclo_id=NULL WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa);
    -- desvincular referencia historica avaliacao_aluno_snapshots.turma_avaliativa_id
    UPDATE avaliacao_aluno_snapshots SET turma_avaliativa_id=NULL WHERE turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- desvincular referencia historica avaliacao_aluno_snapshots.turma_origem_id
    UPDATE avaliacao_aluno_snapshots SET turma_origem_id=NULL WHERE turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- desvincular referencia historica avaliacao_aluno_snapshots.turma_destino_id
    UPDATE avaliacao_aluno_snapshots SET turma_destino_id=NULL WHERE turma_destino_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- desvincular referencia historica avaliacao_snapshot_eventos.ciclo_id
    UPDATE avaliacao_snapshot_eventos SET ciclo_id=NULL WHERE ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa);
    -- desvincular referencia historica avaliacao_snapshot_eventos.turma_avaliativa_id
    UPDATE avaliacao_snapshot_eventos SET turma_avaliativa_id=NULL WHERE turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- desvincular referencia historica avaliacao_snapshot_eventos.turma_origem_id
    UPDATE avaliacao_snapshot_eventos SET turma_origem_id=NULL WHERE turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- desvincular referencia historica avaliacao_snapshot_eventos.turma_destino_id
    UPDATE avaliacao_snapshot_eventos SET turma_destino_id=NULL WHERE turma_destino_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- preservar logs de exportacao
    UPDATE avaliacao_exportacoes SET turma_id=NULL WHERE turma_id IN (SELECT turma_origem_id FROM srm_20260909_mapa);
    -- excluir somente resumos arquivados
    DELETE FROM avaliacao_snapshot_resumos_componentes WHERE id IN (SELECT id FROM srm2_20260909_resumos);
    -- excluir somente tokens arquivados
    DELETE FROM avaliacao_turma_tokens_escrita WHERE id IN (SELECT id FROM srm2_20260909_tokens);
    -- excluir somente ciclos arquivados
    DELETE FROM avaliacao_turma_ciclos WHERE id IN (SELECT id FROM srm2_20260909_ciclos);
    -- excluir somente vinculos arquivados
    DELETE FROM turma_componente_professor WHERE id IN (SELECT id FROM srm2_20260909_vinculos);
    -- excluir somente funcoes arquivados
    DELETE FROM servidor_funcao_turma WHERE id IN (SELECT id FROM srm2_20260909_funcoes);
    -- excluir somente avaliacao_turma arquivados
    DELETE FROM avaliacao_turma WHERE id IN (SELECT id FROM srm2_20260909_avaliacao_turma);
    -- excluir somente dashboard arquivados
    DELETE FROM avaliacao_dashboard_turma_resumos WHERE id IN (SELECT id FROM srm2_20260909_dashboard);
    -- excluir somente alunos arquivados
    DELETE FROM alunos WHERE id IN (SELECT id FROM srm2_20260909_alunos);
    -- excluir somente turmas arquivados
    DELETE FROM turmas WHERE id IN (SELECT id FROM srm2_20260909_turmas);
    -- Preservacao falhou: alunos
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_alunos b LEFT JOIN alunos r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`id_turma` AS BINARY) <=> CAST(b.`id_turma` AS BINARY)) AND (CAST(r.`tipo_vinculo` AS BINARY) <=> CAST(b.`tipo_vinculo` AS BINARY)) AND (CAST(r.`permite_contra_turno` AS BINARY) <=> CAST(b.`permite_contra_turno` AS BINARY)) AND (CAST(r.`status` AS BINARY) <=> CAST(b.`status` AS BINARY)) AND (CAST(r.`status_alterado_em` AS BINARY) <=> CAST(b.`status_alterado_em` AS BINARY)) AND (CAST(r.`status_alterado_por` AS BINARY) <=> CAST(b.`status_alterado_por` AS BINARY)) AND (CAST(r.`status_motivo` AS BINARY) <=> CAST(b.`status_motivo` AS BINARY)) AND (CAST(r.`aluno_origem_id` AS BINARY) <=> CAST(b.`aluno_origem_id` AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(b.`turma_origem_id` AS BINARY)) AND (CAST(r.`movimentacao_origem` AS BINARY) <=> CAST(b.`movimentacao_origem` AS BINARY)) AND (CAST(r.`pendencia_origem_aluno_id` AS BINARY) <=> CAST(b.`pendencia_origem_aluno_id` AS BINARY)) AND (CAST(r.`id_professor` AS BINARY) <=> CAST(b.`id_professor` AS BINARY)) AND (CAST(r.`nome` AS BINARY) <=> CAST(b.`nome` AS BINARY)) AND (CAST(r.`cgm` AS BINARY) <=> CAST(b.`cgm` AS BINARY)) AND (CAST(r.`cgm_matricula_ativa` AS BINARY) <=> CAST(b.`cgm_matricula_ativa` AS BINARY)) AND (CAST(r.`cgm_contra_turno_ativo` AS BINARY) <=> CAST(b.`cgm_contra_turno_ativo` AS BINARY)) AND (CAST(r.`cgm_unidade_matricula_ativa` AS BINARY) <=> CAST(b.`cgm_unidade_matricula_ativa` AS BINARY)) AND (CAST(r.`sexo` AS BINARY) <=> CAST(b.`sexo` AS BINARY)) AND (CAST(r.`data_matricula` AS BINARY) <=> CAST(b.`data_matricula` AS BINARY)) AND (CAST(r.`data_nascimento` AS BINARY) <=> CAST(b.`data_nascimento` AS BINARY)) AND (CAST(r.`dificuldade_aprendizagem` AS BINARY) <=> CAST(b.`dificuldade_aprendizagem` AS BINARY)) AND (CAST(r.`frequenta_srm` AS BINARY) <=> CAST(b.`frequenta_srm` AS BINARY)) AND (CAST(r.`encaminhado_para_sme` AS BINARY) <=> CAST(b.`encaminhado_para_sme` AS BINARY)) AND (CAST(r.`ja_foi_retido` AS BINARY) <=> CAST(b.`ja_foi_retido` AS BINARY)) AND (CAST(r.`encaminhado_para_caei` AS BINARY) <=> CAST(b.`encaminhado_para_caei` AS BINARY)) AND (CAST(r.`status_fonoaudiologo` AS BINARY) <=> CAST(b.`status_fonoaudiologo` AS BINARY)) AND (CAST(r.`status_psicologo` AS BINARY) <=> CAST(b.`status_psicologo` AS BINARY)) AND (CAST(r.`status_psicopedagogo` AS BINARY) <=> CAST(b.`status_psicopedagogo` AS BINARY)) AND (CAST(r.`avanco_caei` AS BINARY) <=> CAST(b.`avanco_caei` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: alunos'; END IF;
    -- Preservacao falhou: respostas
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_respostas b LEFT JOIN avaliacao_respostas_operacionais r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`token_escrita_id` AS BINARY) <=> CAST(b.`token_escrita_id` AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(b.`ciclo_id` AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(b.`turma_avaliativa_id` AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(b.`turma_origem_id` AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`pauta_id` AS BINARY) <=> CAST(b.`pauta_id` AS BINARY)) AND (CAST(r.`componente_curricular_id` AS BINARY) <=> CAST(b.`componente_curricular_id` AS BINARY)) AND (CAST(r.`professor_id` AS BINARY) <=> CAST(b.`professor_id` AS BINARY)) AND (CAST(r.`alternativa_id` AS BINARY) <=> CAST(b.`alternativa_id` AS BINARY)) AND (CAST(r.`observacao` AS BINARY) <=> CAST(b.`observacao` AS BINARY)) AND (CAST(r.`respondido_em` AS BINARY) <=> CAST(b.`respondido_em` AS BINARY)) AND (CAST(r.`version` AS BINARY) <=> CAST(b.`version` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: respostas'; END IF;
    -- Preservacao falhou: infos
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_infos b LEFT JOIN avaliacao_informacoes_operacionais r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`token_escrita_id` AS BINARY) <=> CAST(b.`token_escrita_id` AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(b.`ciclo_id` AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(b.`turma_avaliativa_id` AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(b.`turma_origem_id` AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`componente_curricular_id` AS BINARY) <=> CAST(b.`componente_curricular_id` AS BINARY)) AND (CAST(r.`componente_chave` AS BINARY) <=> CAST(b.`componente_chave` AS BINARY)) AND (CAST(r.`professor_id` AS BINARY) <=> CAST(b.`professor_id` AS BINARY)) AND (CAST(r.`texto` AS BINARY) <=> CAST(b.`texto` AS BINARY)) AND (CAST(r.`version` AS BINARY) <=> CAST(b.`version` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: infos'; END IF;
    -- Preservacao falhou: documentos
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_documentos b LEFT JOIN avaliacao_aluno_documentos r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`cgm` AS BINARY) <=> CAST(b.`cgm` AS BINARY)) AND (CAST(r.`turma_id` AS BINARY) <=> CAST(COALESCE((SELECT m.turma_destino_id FROM srm_20260909_mapa m WHERE m.turma_origem_id=b.turma_id),b.turma_id) AS BINARY)) AND (CAST(r.`escola_id` AS BINARY) <=> CAST(b.`escola_id` AS BINARY)) AND (CAST(r.`serie_id` AS BINARY) <=> CAST(b.`serie_id` AS BINARY)) AND (CAST(r.`payload` AS BINARY) <=> CAST(b.`payload` AS BINARY)) AND (CAST(r.`responsaveis_snapshot` AS BINARY) <=> CAST(b.`responsaveis_snapshot` AS BINARY)) AND (CAST(r.`responsaveis_snapshot_em` AS BINARY) <=> CAST(b.`responsaveis_snapshot_em` AS BINARY)) AND (CAST(r.`alternativa_ids` AS BINARY) <=> CAST(b.`alternativa_ids` AS BINARY)) AND (CAST(r.`professor_ids` AS BINARY) <=> CAST(b.`professor_ids` AS BINARY)) AND (CAST(r.`pauta_ids_respondidas` AS BINARY) <=> CAST(b.`pauta_ids_respondidas` AS BINARY)) AND (CAST(r.`total_pautas_esperadas` AS BINARY) <=> CAST(b.`total_pautas_esperadas` AS BINARY)) AND (CAST(r.`total_pautas_respondidas` AS BINARY) <=> CAST(b.`total_pautas_respondidas` AS BINARY)) AND (CAST(r.`total_infos_complementares` AS BINARY) <=> CAST(b.`total_infos_complementares` AS BINARY)) AND (CAST(r.`status_preenchimento` AS BINARY) <=> CAST(b.`status_preenchimento` AS BINARY)) AND (CAST(r.`observacoes_obrigatorias_pendentes` AS BINARY) <=> CAST(b.`observacoes_obrigatorias_pendentes` AS BINARY)) AND (CAST(r.`primeira_resposta_em` AS BINARY) <=> CAST(b.`primeira_resposta_em` AS BINARY)) AND (CAST(r.`ultima_resposta_em` AS BINARY) <=> CAST(b.`ultima_resposta_em` AS BINARY)) AND (CAST(r.`version` AS BINARY) <=> CAST(b.`version` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: documentos'; END IF;
    -- Preservacao falhou: vinculos
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_vinculos b LEFT JOIN turma_componente_professor r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`turma_id` AS BINARY) <=> CAST(b.`turma_id` AS BINARY)) AND (CAST(r.`componente_curricular_id` AS BINARY) <=> CAST(b.`componente_curricular_id` AS BINARY)) AND (CAST(r.`professor_id` AS BINARY) <=> CAST(b.`professor_id` AS BINARY)) AND (CAST(r.`tem_professor` AS BINARY) <=> CAST(b.`tem_professor` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: vinculos'; END IF;
    -- Preservacao falhou: ciclos
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_ciclos b LEFT JOIN avaliacao_turma_ciclos r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(b.`turma_avaliativa_id` AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(b.`turma_origem_id` AS BINARY)) AND (CAST(r.`status` AS BINARY) <=> CAST(b.`status` AS BINARY)) AND (CAST(r.`roster_mode` AS BINARY) <=> CAST(b.`roster_mode` AS BINARY)) AND (CAST(r.`versao_conclusao` AS BINARY) <=> CAST(b.`versao_conclusao` AS BINARY)) AND (CAST(r.`snapshot_evento_atual_id` AS BINARY) <=> CAST(b.`snapshot_evento_atual_id` AS BINARY)) AND (CAST(r.`operacional_inicializado_em` AS BINARY) <=> CAST(b.`operacional_inicializado_em` AS BINARY)) AND (CAST(r.`legado_documentos_migrados` AS BINARY) <=> CAST(b.`legado_documentos_migrados` AS BINARY)) AND (CAST(r.`legado_migracao_hash` AS BINARY) <=> CAST(b.`legado_migracao_hash` AS BINARY)) AND (CAST(r.`concluida_em` AS BINARY) <=> CAST(b.`concluida_em` AS BINARY)) AND (CAST(r.`concluida_por` AS BINARY) <=> CAST(b.`concluida_por` AS BINARY)) AND (CAST(r.`concluida_por_snapshot` AS BINARY) <=> CAST(b.`concluida_por_snapshot` AS BINARY)) AND (CAST(r.`reaberta_em` AS BINARY) <=> CAST(b.`reaberta_em` AS BINARY)) AND (CAST(r.`reaberta_por` AS BINARY) <=> CAST(b.`reaberta_por` AS BINARY)) AND (CAST(r.`reaberta_por_snapshot` AS BINARY) <=> CAST(b.`reaberta_por_snapshot` AS BINARY)) AND (CAST(r.`motivo_reabertura` AS BINARY) <=> CAST(b.`motivo_reabertura` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: ciclos'; END IF;
    -- Preservacao falhou: tokens
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_tokens b LEFT JOIN avaliacao_turma_tokens_escrita r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(b.`ciclo_id` AS BINARY)) AND (CAST(r.`generation_uuid` AS BINARY) <=> CAST(b.`generation_uuid` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: tokens'; END IF;
    -- Preservacao falhou: turmas
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_turmas b LEFT JOIN turmas r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`codigo` AS BINARY) <=> CAST(b.`codigo` AS BINARY)) AND (CAST(r.`nome` AS BINARY) <=> CAST(b.`nome` AS BINARY)) AND (CAST(r.`turno` AS BINARY) <=> CAST(b.`turno` AS BINARY)) AND (CAST(r.`id_serie` AS BINARY) <=> CAST(b.`id_serie` AS BINARY)) AND (CAST(r.`id_escola` AS BINARY) <=> CAST(b.`id_escola` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: turmas'; END IF;
    -- Preservacao falhou: snapshots
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_snapshots b LEFT JOIN avaliacao_aluno_snapshots r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`evento_id` AS BINARY) <=> CAST(b.`evento_id` AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(CASE WHEN b.ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa) THEN NULL ELSE b.ciclo_id END AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(CASE WHEN b.turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) THEN NULL ELSE b.turma_avaliativa_id END AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(CASE WHEN b.turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) THEN NULL ELSE b.turma_origem_id END AS BINARY)) AND (CAST(r.`turma_destino_id` AS BINARY) <=> CAST(CASE WHEN b.turma_destino_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) THEN NULL ELSE b.turma_destino_id END AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`cgm` AS BINARY) <=> CAST(b.`cgm` AS BINARY)) AND (CAST(r.`tipo` AS BINARY) <=> CAST(b.`tipo` AS BINARY)) AND (CAST(r.`schema_version` AS BINARY) <=> CAST(b.`schema_version` AS BINARY)) AND (CAST(r.`payload` AS BINARY) <=> CAST(b.`payload` AS BINARY)) AND (CAST(r.`payload_hash` AS BINARY) <=> CAST(b.`payload_hash` AS BINARY)) AND (CAST(r.`total_pautas_esperadas` AS BINARY) <=> CAST(b.`total_pautas_esperadas` AS BINARY)) AND (CAST(r.`total_respostas` AS BINARY) <=> CAST(b.`total_respostas` AS BINARY)) AND (CAST(r.`total_informacoes` AS BINARY) <=> CAST(b.`total_informacoes` AS BINARY)) AND (CAST(r.`tamanho_bytes` AS BINARY) <=> CAST(b.`tamanho_bytes` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: snapshots'; END IF;
    -- Preservacao falhou: eventos
    IF (SELECT EXISTS(SELECT 1 FROM tmp_srm2_eventos b LEFT JOIN avaliacao_snapshot_eventos r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`idempotency_key` AS BINARY) <=> CAST(b.`idempotency_key` AS BINARY)) AND (CAST(r.`tipo` AS BINARY) <=> CAST(b.`tipo` AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(CASE WHEN b.ciclo_id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa) THEN NULL ELSE b.ciclo_id END AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(CASE WHEN b.turma_avaliativa_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) THEN NULL ELSE b.turma_avaliativa_id END AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(CASE WHEN b.turma_origem_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) THEN NULL ELSE b.turma_origem_id END AS BINARY)) AND (CAST(r.`turma_destino_id` AS BINARY) <=> CAST(CASE WHEN b.turma_destino_id IN (SELECT turma_origem_id FROM srm_20260909_mapa) THEN NULL ELSE b.turma_destino_id END AS BINARY)) AND (CAST(r.`versao` AS BINARY) <=> CAST(b.`versao` AS BINARY)) AND (CAST(r.`schema_version` AS BINARY) <=> CAST(b.`schema_version` AS BINARY)) AND (CAST(r.`criado_por` AS BINARY) <=> CAST(b.`criado_por` AS BINARY)) AND (CAST(r.`criado_por_snapshot` AS BINARY) <=> CAST(b.`criado_por_snapshot` AS BINARY)) AND (CAST(r.`motivo` AS BINARY) <=> CAST(b.`motivo` AS BINARY)) AND (CAST(r.`total_alunos` AS BINARY) <=> CAST(b.`total_alunos` AS BINARY)) AND (CAST(r.`total_respostas_esperadas` AS BINARY) <=> CAST(b.`total_respostas_esperadas` AS BINARY)) AND (CAST(r.`total_respostas_geradas` AS BINARY) <=> CAST(b.`total_respostas_geradas` AS BINARY)) AND (CAST(r.`tamanho_bytes` AS BINARY) <=> CAST(b.`tamanho_bytes` AS BINARY)) AND (CAST(r.`payload_hash_agregado` AS BINARY) <=> CAST(b.`payload_hash_agregado` AS BINARY)) AND (CAST(r.`publicado_em` AS BINARY) <=> CAST(b.`publicado_em` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao falhou: eventos'; END IF;
    -- Preservacao de logs falhou
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_exportacoes b LEFT JOIN avaliacao_exportacoes r ON r.id=b.id WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`escola_id` AS BINARY) <=> CAST(b.`escola_id` AS BINARY)) AND (CAST(r.`turma_id` AS BINARY) <=> CAST(NULL AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`user_id` AS BINARY) <=> CAST(b.`user_id` AS BINARY)) AND (CAST(r.`escopo` AS BINARY) <=> CAST(b.`escopo` AS BINARY)) AND (CAST(r.`formato` AS BINARY) <=> CAST(b.`formato` AS BINARY)) AND (CAST(r.`quantidade_alunos` AS BINARY) <=> CAST(b.`quantidade_alunos` AS BINARY)) AND (CAST(r.`quantidade_paginas` AS BINARY) <=> CAST(b.`quantidade_paginas` AS BINARY)) AND (CAST(r.`parametros` AS BINARY) <=> CAST(b.`parametros` AS BINARY)) AND (CAST(r.`exportado_em` AS BINARY) <=> CAST(b.`exportado_em` AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao de logs falhou'; END IF;
    -- Vinculo administrativo nao preservado
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_funcoes s JOIN srm_20260909_mapa m ON m.turma_origem_id=s.turma_id
        WHERE NOT EXISTS(SELECT 1 FROM servidor_funcao_turma n WHERE n.turma_id=m.turma_destino_id AND (n.servidor_funcao_administrativa_id<=>s.servidor_funcao_administrativa_id) AND (n.principal<=>s.principal) AND (n.status<=>s.status) AND (n.data_inicio<=>s.data_inicio) AND (n.data_fim<=>s.data_fim)))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Vinculo administrativo nao preservado'; END IF;
    -- Total global divergente: alunos
    IF (SELECT (SELECT COUNT(*) FROM alunos)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='alunos')-(SELECT COUNT(*) FROM srm2_20260909_alunos)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: alunos'; END IF;
    -- Total global divergente: turmas
    IF (SELECT (SELECT COUNT(*) FROM turmas)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='turmas')-(SELECT COUNT(*) FROM srm2_20260909_turmas)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: turmas'; END IF;
    -- Total global divergente: avaliacao_respostas_operacionais
    IF (SELECT (SELECT COUNT(*) FROM avaliacao_respostas_operacionais)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='avaliacao_respostas_operacionais')-0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: avaliacao_respostas_operacionais'; END IF;
    -- Total global divergente: avaliacao_informacoes_operacionais
    IF (SELECT (SELECT COUNT(*) FROM avaliacao_informacoes_operacionais)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='avaliacao_informacoes_operacionais')-0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: avaliacao_informacoes_operacionais'; END IF;
    -- Total global divergente: avaliacao_aluno_documentos
    IF (SELECT (SELECT COUNT(*) FROM avaliacao_aluno_documentos)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='avaliacao_aluno_documentos')-0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: avaliacao_aluno_documentos'; END IF;
    -- Total global divergente: avaliacao_aluno_snapshots
    IF (SELECT (SELECT COUNT(*) FROM avaliacao_aluno_snapshots)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='avaliacao_aluno_snapshots')-0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: avaliacao_aluno_snapshots'; END IF;
    -- Total global divergente: avaliacao_snapshot_eventos
    IF (SELECT (SELECT COUNT(*) FROM avaliacao_snapshot_eventos)<>(SELECT quantidade FROM tmp_srm2_totais WHERE tabela='avaliacao_snapshot_eventos')-0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total global divergente: avaliacao_snapshot_eventos'; END IF;
    -- Preservacao final da resposta residual falhou
    IF (SELECT EXISTS(SELECT 1 FROM srm2_20260909_respostas_relocadas b
        JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=b.ciclo_id JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        JOIN avaliacao_turma_tokens_escrita nt ON nt.ciclo_id=nc.id
        LEFT JOIN avaliacao_respostas_operacionais r ON r.id=b.id
        WHERE r.id IS NULL OR NOT((CAST(r.`id` AS BINARY) <=> CAST(b.`id` AS BINARY)) AND (CAST(r.`token_escrita_id` AS BINARY) <=> CAST(nt.id AS BINARY)) AND (CAST(r.`ciclo_id` AS BINARY) <=> CAST(nc.id AS BINARY)) AND (CAST(r.`avaliacao_id` AS BINARY) <=> CAST(b.`avaliacao_id` AS BINARY)) AND (CAST(r.`turma_avaliativa_id` AS BINARY) <=> CAST(nc.turma_avaliativa_id AS BINARY)) AND (CAST(r.`turma_origem_id` AS BINARY) <=> CAST(nc.turma_origem_id AS BINARY)) AND (CAST(r.`aluno_id` AS BINARY) <=> CAST(b.`aluno_id` AS BINARY)) AND (CAST(r.`pauta_id` AS BINARY) <=> CAST(b.`pauta_id` AS BINARY)) AND (CAST(r.`componente_curricular_id` AS BINARY) <=> CAST(b.`componente_curricular_id` AS BINARY)) AND (CAST(r.`professor_id` AS BINARY) <=> CAST(b.`professor_id` AS BINARY)) AND (CAST(r.`alternativa_id` AS BINARY) <=> CAST(b.`alternativa_id` AS BINARY)) AND (CAST(r.`observacao` AS BINARY) <=> CAST(b.`observacao` AS BINARY)) AND (CAST(r.`respondido_em` AS BINARY) <=> CAST(b.`respondido_em` AS BINARY)) AND (CAST(r.`version` AS BINARY) <=> CAST(b.version+1 AS BINARY)) AND (CAST(r.`created_at` AS BINARY) <=> CAST(b.`created_at` AS BINARY)) AND (CAST(r.`updated_at` AS BINARY) <=> CAST(b.`updated_at` AS BINARY))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao final da resposta residual falhou'; END IF;
    -- Limpeza incompleta
    IF (SELECT EXISTS(SELECT 1 FROM alunos WHERE id IN (SELECT id FROM srm2_20260909_alunos)) OR EXISTS(SELECT 1 FROM turmas WHERE id IN (SELECT turma_origem_id FROM srm_20260909_mapa)) OR EXISTS(SELECT 1 FROM avaliacao_turma_ciclos WHERE id IN (SELECT ciclo_origem_id FROM srm_20260909_ciclos_mapa))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Limpeza incompleta'; END IF;
    INSERT INTO srm2_20260909_execucao VALUES (1,UTC_TIMESTAMP(),
        (SELECT COUNT(*) FROM srm2_20260909_alunos),(SELECT COUNT(*) FROM srm2_20260909_turmas));
    COMMIT;
    DO RELEASE_LOCK(v_nome_lock);
    SELECT 'COMMIT realizado - limpeza SRM etapa 2' AS resultado,
        (SELECT COUNT(*) FROM srm2_20260909_alunos) AS remanejados_excluidos,
        (SELECT COUNT(*) FROM srm2_20260909_turmas) AS turmas_antigas_excluidas,
        (SELECT COUNT(*) FROM tmp_srm2_alunos) AS alunos_atuais_preservados,
        (SELECT COUNT(*) FROM tmp_srm2_respostas) AS respostas_atuais_preservadas,
        (SELECT COUNT(*) FROM srm2_20260909_documentos) AS documentos_realocados,
        (SELECT COUNT(*) FROM srm2_20260909_respostas_relocadas) AS respostas_residuais_realocadas;
END$$
DELIMITER ;
CALL limpar_srm_etapa2_20260909();
DROP PROCEDURE limpar_srm_etapa2_20260909;
