-- Execute este arquivo ANTES de criar a chave UNIQUE.
-- Ele consolida registros antigos que tenham o mesmo modelo + cor + mes + ano.

START TRANSACTION;

CREATE TEMPORARY TABLE tinta_consolidada AS
SELECT
    MIN(id) AS id_base,
    TRIM(BOTH ' / ' FROM REPLACE(GROUP_CONCAT(DISTINCT NULLIF(TRIM(impressora), '') SEPARATOR ' / '), '  / ', ' / ')) AS impressora,
    modelo,
    cor,
    SUM(quantidade) AS quantidade,
    mes,
    ano
FROM tinta
GROUP BY modelo, cor, mes, ano;

DELETE FROM tinta;

INSERT INTO tinta (id, impressora, modelo, cor, quantidade, mes, ano)
SELECT
    id_base,
    COALESCE(impressora, ''),
    modelo,
    cor,
    quantidade,
    mes,
    ano
FROM tinta_consolidada
ORDER BY id_base;

DROP TEMPORARY TABLE tinta_consolidada;

COMMIT;

-- Depois de validar que os dados ficaram corretos, rode esta protecao:
-- ALTER TABLE tinta ADD UNIQUE KEY unica_tinta_modelo_cor_mes_ano (modelo, cor, mes, ano);
