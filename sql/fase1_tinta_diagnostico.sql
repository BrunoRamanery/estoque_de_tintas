-- Fase 1 - Diagnostico da migracao de tintas para estoque consolidado.
-- Objetivo: identificar conflitos na regra nova (modelo + cor + mes + ano)
-- e localizar registros com impressora vazia/NULL antes da alteracao.

SELECT
    modelo,
    cor,
    mes,
    ano,
    COUNT(*) AS qtd_registros,
    SUM(quantidade) AS quantidade_total,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS ids_envolvidos,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(impressora), ''), '(vazio)') ORDER BY id SEPARATOR ' | ') AS impressoras_envolvidas
FROM tinta
GROUP BY modelo, cor, mes, ano
HAVING COUNT(*) > 1
ORDER BY modelo, cor, ano, mes;

SELECT
    COUNT(*) AS total_registros,
    SUM(CASE WHEN TRIM(COALESCE(impressora, '')) = '' THEN 1 ELSE 0 END) AS registros_com_impressora_vazia
FROM tinta;
