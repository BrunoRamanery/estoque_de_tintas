<?php
/**
 * Servicos de tintas.
 *
 * Esta camada concentra regras de negocio e calculos.
 */

require_once __DIR__ . '/../repositorio/tintas_repositorio.php';

/**
 * Servicos e regras de negocio do sistema.
 *
 * Aqui ficam os calculos que montam os dados para a interface:
 * - resumo do dashboard
 * - lista de compras com status
 * - detalhes do modelo com alertas
 */

function montar_resumo_dashboard(array $registrosResumo): array
{
    $resumo = [
        'vencidas' => 0,
        'vence_breve' => 0,
        'compra_urgente' => 0,
        'compra_breve' => 0,
    ];

    foreach ($registrosResumo as $registro) {
        $statusValidade = obter_status_validade((int) $registro['mes'], (int) $registro['ano']);
        $statusCompra = obter_status_compra((int) $registro['quantidade']);

        if ($statusValidade['chave'] === 'vencida') {
            $resumo['vencidas']++;
        } elseif ($statusValidade['chave'] === 'proxima') {
            $resumo['vence_breve']++;
        }

        if ($statusCompra['chave'] === 'urgente') {
            $resumo['compra_urgente']++;
        } elseif ($statusCompra['chave'] === 'baixo') {
            $resumo['compra_breve']++;
        }
    }

    return $resumo;
}

function montar_lista_compras_com_status(array $itensCompras): array
{
    $itens = [];

    foreach ($itensCompras as $item) {
        $corValor = strtolower((string) $item['cor']);
        $item['status_compra'] = obter_status_compra((int) $item['quantidade_total']);
        $item['cor_classe'] = 'cor-' . $corValor;
        $item['dot_classe'] = 'dot-' . $corValor;
        $itens[] = $item;
    }

    return $itens;
}

function montar_detalhes_modelo(array $registros): array
{
    $resumo = [
        'vencidas' => 0,
        'proximas' => 0,
        'urgente' => 0,
        'baixo' => 0,
    ];

    $linhas = [];

    foreach ($registros as $row) {
        $statusValidade = obter_status_validade((int) $row['mes'], (int) $row['ano']);
        $statusCompra = obter_status_compra((int) $row['quantidade']);
        $corValor = strtolower((string) $row['cor']);

        if ($statusValidade['chave'] === 'vencida') {
            $resumo['vencidas']++;
        } elseif ($statusValidade['chave'] === 'proxima') {
            $resumo['proximas']++;
        }

        if ($statusCompra['chave'] === 'urgente') {
            $resumo['urgente']++;
        } elseif ($statusCompra['chave'] === 'baixo') {
            $resumo['baixo']++;
        }

        $row['validade_status'] = $statusValidade;
        $row['compra_status'] = $statusCompra;
        $row['cor_classe'] = 'cor-' . $corValor;
        $row['dot_classe'] = 'dot-' . $corValor;
        $row['linha_classe'] = obter_classe_prioridade_linha($statusValidade, $statusCompra);
        $linhas[] = $row;
    }

    return [
        'resumo' => $resumo,
        'linhas' => $linhas,
    ];
}

function listar_modelos_com_alertas(mysqli $conn, array $filtros = []): array
{
    $modelos = buscar_modelos_completo($conn, $filtros);

    foreach ($modelos as &$modelo) {
        $modelo['status'] = obter_status_compra((int) ($modelo['total'] ?? 0));
        $modelo['alertas'] = gerar_alertas_por_cor((array) ($modelo['cores'] ?? []));
        $modelo['total_alertas'] = count($modelo['alertas']);
    }
    unset($modelo);

    return $modelos;
}

function servico_tintas_obter_detalhes_modelo(mysqli $conn, string $modelo, string $busca = ''): array
{
    $registros = repo_tintas_buscar_por_modelo($conn, $modelo, $busca);

    $tintas = [];
    $totalQuantidade = 0;
    $totalCompraUrgente = 0;
    $totalVenceBreve = 0;
    $resumoCor = [];

    foreach ($registros as $row) {
        $quantidade = (int) ($row['quantidade'] ?? 0);
        $statusCompra = obter_status_compra($quantidade);
        $statusValidade = obter_status_validade((int) ($row['mes'] ?? 0), (int) ($row['ano'] ?? 0));

        $row['status_compra'] = $statusCompra;
        $row['status_validade'] = $statusValidade;
        $tintas[] = $row;

        $totalQuantidade += $quantidade;

        if (($statusCompra['chave'] ?? '') === 'urgente') {
            $totalCompraUrgente++;
        }

        if (($statusValidade['chave'] ?? '') === 'proxima') {
            $totalVenceBreve++;
        }

        $cor = strtoupper(trim((string) ($row['cor'] ?? '')));
        if ($cor === '') {
            $cor = '-';
        }

        if (!isset($resumoCor[$cor])) {
            $resumoCor[$cor] = 0;
        }
        $resumoCor[$cor] += $quantidade;
    }

    ksort($resumoCor);

    return [
        'tintas' => $tintas,
        'total_quantidade' => $totalQuantidade,
        'total_compra_urgente' => $totalCompraUrgente,
        'total_vence_breve' => $totalVenceBreve,
        'resumo_cor' => $resumoCor,
    ];
}

function servico_tintas_obter_dashboard(mysqli $conn, array $filtros): array
{
    $modelos = buscar_modelos_agrupados($conn, [
        'busca' => $filtros['busca'] ?? '',
        'modelo' => $filtros['modelo'] ?? '',
        'cor' => $filtros['cor'] ?? '',
    ]);

    $opcoesModelos = buscar_opcoes_modelos($conn);
    $opcoesCores = buscar_opcoes_cores($conn);
    $resumo = montar_resumo_dashboard(buscar_dados_resumo_tintas($conn));
    $listaCompras = montar_lista_compras_com_status(buscar_lista_compras($conn));
    $modelosComAlertas = listar_modelos_com_alertas($conn, [
        'busca' => $filtros['busca'] ?? '',
        'modelo' => $filtros['modelo'] ?? '',
        'cor' => $filtros['cor'] ?? '',
    ]);

    $mapaModelosComAlertas = [];
    foreach ($modelosComAlertas as $modeloComAlerta) {
        $mapaModelosComAlertas[(string) ($modeloComAlerta['modelo'] ?? '')] = $modeloComAlerta;
    }

    $filtroAtivo = ($filtros['busca'] ?? '') !== ''
        || ($filtros['modelo'] ?? '') !== ''
        || ($filtros['cor'] ?? '') !== ''
        || ($filtros['status_compra'] ?? '') !== ''
        || ($filtros['status_validade'] ?? '') !== '';

    foreach ($modelos as &$itemModelo) {
        $chaveModelo = (string) ($itemModelo['modelo'] ?? '');
        $dadosComAlerta = $mapaModelosComAlertas[$chaveModelo] ?? null;

        $itemModelo['status_modelo'] = obter_status_compra((int) ($itemModelo['total_quantidade'] ?? 0));
        $itemModelo['alertas_modelo'] = [];
        $itemModelo['total_alertas_modelo'] = 0;
        $itemModelo['total_alertas_criticos_modelo'] = 0;
        $itemModelo['tem_validade_vencida'] = false;
        $itemModelo['tem_validade_proxima'] = false;
        $itemModelo['tem_compra_urgente'] = false;
        $itemModelo['tem_compra_baixa'] = false;

        if (is_array($dadosComAlerta)) {
            if (!$filtroAtivo && isset($dadosComAlerta['status']) && is_array($dadosComAlerta['status'])) {
                $itemModelo['status_modelo'] = $dadosComAlerta['status'];
            }

            $alertasModelo = is_array($dadosComAlerta['alertas'] ?? null) ? $dadosComAlerta['alertas'] : [];

            if (($filtros['cor'] ?? '') !== '') {
                $corFiltro = mb_strtolower((string) $filtros['cor']);
                $alertasModelo = array_values(array_filter(
                    $alertasModelo,
                    static function (array $alerta) use ($corFiltro): bool {
                        return mb_strtolower((string) ($alerta['cor'] ?? '')) === $corFiltro;
                    }
                ));
            }

            foreach ($alertasModelo as $alerta) {
                $chave = (string) ($alerta['chave'] ?? (($alerta['status_validade']['chave'] ?? '') !== '' ? $alerta['status_validade']['chave'] : ($alerta['status_compra']['chave'] ?? '')));

                if (($alerta['status_validade']['chave'] ?? '') === 'vencida') {
                    $itemModelo['tem_validade_vencida'] = true;
                }

                if (($alerta['status_validade']['chave'] ?? '') === 'proxima') {
                    $itemModelo['tem_validade_proxima'] = true;
                }

                if (($alerta['status_compra']['chave'] ?? '') === 'urgente' || $chave === 'urgente') {
                    $itemModelo['tem_compra_urgente'] = true;
                }

                if (($alerta['status_compra']['chave'] ?? '') === 'baixo' || $chave === 'baixo') {
                    $itemModelo['tem_compra_baixa'] = true;
                }
            }

            $itemModelo['alertas_modelo'] = $alertasModelo;
            $itemModelo['total_alertas_modelo'] = count($alertasModelo);
            $itemModelo['total_alertas_criticos_modelo'] = count(array_filter(
                $alertasModelo,
                static fn(array $alerta): bool => (($alerta['peso'] ?? 0) >= 3)
            ));
        }
    }
    unset($itemModelo);

    $modelos = array_values(array_filter(
        $modelos,
        static function (array $itemModelo) use ($filtros): bool {
            if (($filtros['status_compra'] ?? '') !== '') {
                $statusCompra = (string) $filtros['status_compra'];

                if ($statusCompra === 'ok' && (string) ($itemModelo['status_modelo']['chave'] ?? '') !== 'ok') {
                    return false;
                }

                if ($statusCompra === 'baixo' && empty($itemModelo['tem_compra_baixa'])) {
                    return false;
                }

                if ($statusCompra === 'urgente' && empty($itemModelo['tem_compra_urgente'])) {
                    return false;
                }
            }

            if (($filtros['status_validade'] ?? '') !== '') {
                $statusValidade = (string) $filtros['status_validade'];

                if ($statusValidade === 'valida') {
                    if (!empty($itemModelo['tem_validade_vencida']) || !empty($itemModelo['tem_validade_proxima'])) {
                        return false;
                    }
                }

                if ($statusValidade === 'proxima' && empty($itemModelo['tem_validade_proxima'])) {
                    return false;
                }

                if ($statusValidade === 'vencida' && empty($itemModelo['tem_validade_vencida'])) {
                    return false;
                }
            }

            return true;
        }
    ));

    usort($modelos, static function (array $a, array $b): int {
        return ((int) ($b['total_quantidade'] ?? 0)) <=> ((int) ($a['total_quantidade'] ?? 0));
    });

    $modelosVisiveis = [];
    foreach ($modelos as $itemModeloVisivel) {
        $chaveModelo = (string) ($itemModeloVisivel['modelo'] ?? '');
        if ($chaveModelo !== '') {
            $modelosVisiveis[$chaveModelo] = true;
        }
    }

    $listaCompras = array_values(array_filter(
        $listaCompras,
        static function (array $item) use ($filtros, $modelosVisiveis): bool {
            $modeloItemOriginal = (string) ($item['modelo'] ?? '');
            if ($modeloItemOriginal === '' || !isset($modelosVisiveis[$modeloItemOriginal])) {
                return false;
            }

            if (($filtros['cor'] ?? '') !== '' && mb_strtolower((string) ($item['cor'] ?? '')) !== mb_strtolower((string) $filtros['cor'])) {
                return false;
            }

            if (($filtros['modelo'] ?? '') !== '' && $modeloItemOriginal !== (string) $filtros['modelo']) {
                return false;
            }

            return true;
        }
    ));

    usort($listaCompras, static function (array $a, array $b): int {
        $pesoA = ($a['status_compra']['chave'] ?? '') === 'urgente' ? 3 : (($a['status_compra']['chave'] ?? '') === 'baixo' ? 2 : 1);
        $pesoB = ($b['status_compra']['chave'] ?? '') === 'urgente' ? 3 : (($b['status_compra']['chave'] ?? '') === 'baixo' ? 2 : 1);

        if ($pesoB !== $pesoA) {
            return $pesoB <=> $pesoA;
        }

        return ((int) ($a['quantidade_total'] ?? 0)) <=> ((int) ($b['quantidade_total'] ?? 0));
    });

    $totalModelosEncontrados = count($modelos);
    $quantidadeTotalFiltrada = array_sum(array_map(static fn(array $item): int => (int) ($item['total_quantidade'] ?? 0), $modelos));

    $totalModelosVencidos = 0;
    $totalModelosProximos = 0;
    $totalModelosUrgentes = 0;
    $totalModelosBaixos = 0;

    foreach ($modelos as $itemModelo) {
        if (!empty($itemModelo['tem_validade_vencida'])) {
            $totalModelosVencidos++;
        }
        if (!empty($itemModelo['tem_validade_proxima'])) {
            $totalModelosProximos++;
        }
        if (!empty($itemModelo['tem_compra_urgente'])) {
            $totalModelosUrgentes++;
        }
        if (!empty($itemModelo['tem_compra_baixa'])) {
            $totalModelosBaixos++;
        }
    }

    $totaisPorCor = [];
    foreach ($listaCompras as $itemCompra) {
        $cor = strtoupper(trim((string) ($itemCompra['cor'] ?? '')));
        if ($cor === '') {
            continue;
        }
        if (!isset($totaisPorCor[$cor])) {
            $totaisPorCor[$cor] = 0;
        }
        $totaisPorCor[$cor] += (int) ($itemCompra['quantidade_total'] ?? 0);
    }
    ksort($totaisPorCor);

    $graficoCores = [
        'labels' => array_keys($totaisPorCor),
        'data' => array_values($totaisPorCor),
    ];

    $topModelosGrafico = array_slice($modelos, 0, 6);
    $graficoModelos = [
        'labels' => array_map(static fn(array $item): string => 'Modelo ' . (string) ($item['modelo'] ?? ''), $topModelosGrafico),
        'data' => array_map(static fn(array $item): int => (int) ($item['total_quantidade'] ?? 0), $topModelosGrafico),
    ];

    $graficoAlertas = [
        'labels' => ['Vencidos', 'Vence em breve', 'Compra urgente', 'Compra em breve'],
        'data' => [$totalModelosVencidos, $totalModelosProximos, $totalModelosUrgentes, $totalModelosBaixos],
    ];

    $resumoMastigado = [];
    if ($totalModelosEncontrados > 0) {
        $resumoMastigado[] = $totalModelosEncontrados . ' modelo(s) encontrado(s)';
    }
    if ($quantidadeTotalFiltrada > 0) {
        $resumoMastigado[] = $quantidadeTotalFiltrada . ' tinta(s) no total filtrado';
    }
    if ($totalModelosUrgentes > 0) {
        $resumoMastigado[] = $totalModelosUrgentes . ' modelo(s) com compra urgente';
    }
    if ($totalModelosVencidos > 0) {
        $resumoMastigado[] = $totalModelosVencidos . ' modelo(s) com item vencido';
    }

    return [
        'filtro_ativo' => $filtroAtivo,
        'modelos' => $modelos,
        'opcoes_modelos' => $opcoesModelos,
        'opcoes_cores' => $opcoesCores,
        'resumo' => $resumo,
        'lista_compras' => $listaCompras,
        'total_modelos_encontrados' => $totalModelosEncontrados,
        'quantidade_total_filtrada' => $quantidadeTotalFiltrada,
        'grafico_cores' => $graficoCores,
        'grafico_modelos' => $graficoModelos,
        'grafico_alertas' => $graficoAlertas,
        'resumo_mastigado' => $resumoMastigado,
    ];
}

function servico_tintas_processar_cadastro(mysqli $conn, array $dadosFormulario): array
{
    $erros = validar_dados_tinta($dadosFormulario);
    if (!empty($erros)) {
        return [
            'ok' => false,
            'errors' => $erros,
            'resultado' => null,
        ];
    }

    $dadosParseados = parsear_dados_tinta($dadosFormulario);
    $resultado = inserir_tinta($conn, $dadosFormulario, $dadosParseados);

    return [
        'ok' => (bool) ($resultado['ok'] ?? false),
        'errors' => [],
        'resultado' => $resultado,
    ];
}

function servico_tintas_processar_atualizacao(mysqli $conn, int $id, array $dadosFormulario): array
{
    $erros = validar_dados_tinta($dadosFormulario);
    if (!empty($erros)) {
        return [
            'ok' => false,
            'errors' => $erros,
            'resultado' => null,
        ];
    }

    $dadosParseados = parsear_dados_tinta($dadosFormulario);
    $resultado = atualizar_tinta($conn, $id, $dadosFormulario, $dadosParseados);

    return [
        'ok' => (bool) ($resultado['ok'] ?? false),
        'errors' => [],
        'resultado' => $resultado,
    ];
}

