-- Fase 1 - Consolidacao de dados antigos da tabela tinta.
-- Regra final: um registro por modelo + cor + mes + ano.
-- A quantidade e somada e o campo impressora e mantido apenas como compatibilidade.

START TRANSACTION;

CREATE TEMPORARY TABLE tinta_consolidada AS
SELECT
    MIN(id) AS id_base,
    NULLIF(
        TRIM(
            BOTH ' / ' FROM GROUP_CONCAT(
                DISTINCT NULLIF(TRIM(COALESCE(impressora, '')), '')
                ORDER BY TRIM(COALESCE(impressora, ''))
                SEPARATOR ' / '
            )
        ),
        ''
    ) AS impressora,
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
