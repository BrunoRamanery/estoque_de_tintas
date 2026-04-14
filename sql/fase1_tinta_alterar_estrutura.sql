-- Fase 1 - Ajuste estrutural da tabela tinta para a nova regra de negocio.
-- Ordem esperada:
-- 1. Rodar diagnostico
-- 2. Rodar backup
-- 3. Rodar consolidacao
-- 4. Rodar este ALTER TABLE

ALTER TABLE tinta
    DROP INDEX unica_tinta,
    MODIFY impressora VARCHAR(100) NULL,
    ADD UNIQUE KEY unica_tinta_modelo_cor_mes_ano (modelo, cor, mes, ano);
