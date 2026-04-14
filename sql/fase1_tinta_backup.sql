-- Fase 1 - Backup da tabela tinta antes da consolidacao.
-- Ajuste o nome da tabela de backup se precisar rodar mais de uma vez.

CREATE TABLE tinta_backup_fase1_20260413 AS
SELECT *
FROM tinta;
