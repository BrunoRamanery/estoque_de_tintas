<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/servicos/tintas_servico.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$mensagem = obter_mensagem_flash();

$filtros = [
    'busca' => trim((string) ($_GET['busca'] ?? '')),
    'modelo' => trim((string) ($_GET['modelo'] ?? '')),
    'cor' => trim((string) ($_GET['cor'] ?? '')),
    'status_compra' => trim((string) ($_GET['status_compra'] ?? '')),
    'status_validade' => trim((string) ($_GET['status_validade'] ?? '')),
];

try {
    $dadosDashboard = servico_tintas_obter_dashboard($conn, $filtros);
} catch (RuntimeException $erro) {
    error_log('Falha ao carregar dashboard de tintas: ' . $erro->getMessage());
    $dadosDashboard = [
        'filtro_ativo' => false,
        'modelos' => [],
        'opcoes_modelos' => [],
        'opcoes_cores' => [],
        'resumo' => [
            'vencidas' => 0,
            'vence_breve' => 0,
            'compra_urgente' => 0,
            'compra_breve' => 0,
        ],
        'lista_compras' => [],
        'total_modelos_encontrados' => 0,
        'quantidade_total_filtrada' => 0,
        'grafico_cores' => ['labels' => [], 'data' => []],
        'grafico_modelos' => ['labels' => [], 'data' => []],
        'grafico_alertas' => ['labels' => [], 'data' => []],
        'resumo_mastigado' => [],
    ];
    $mensagem = [
        'tipo' => 'erro',
        'texto' => 'Nao foi possivel carregar todos os dados do painel. Tente novamente.',
    ];
}

$conn->close();

$filtroAtivo = $dadosDashboard['filtro_ativo'];
$modelos = $dadosDashboard['modelos'];
$opcoesModelos = $dadosDashboard['opcoes_modelos'];
$opcoesCores = $dadosDashboard['opcoes_cores'];
$resumo = $dadosDashboard['resumo'];
$listaCompras = $dadosDashboard['lista_compras'];
$totalModelosEncontrados = $dadosDashboard['total_modelos_encontrados'];
$quantidadeTotalFiltrada = $dadosDashboard['quantidade_total_filtrada'];
$graficoCores = $dadosDashboard['grafico_cores'];
$graficoModelos = $dadosDashboard['grafico_modelos'];
$graficoAlertas = $dadosDashboard['grafico_alertas'];
$resumoMastigado = $dadosDashboard['resumo_mastigado'];
$tituloPagina = 'Controle de Tintas';
$caminhoCss = 'css/principal.css';
$jsonFlagsDashboard = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body>
    <?php require __DIR__ . '/includes/topo_sistema.php'; ?>

    <div class="container dashboard-clean">
        <div class="topo topo-dashboard-clean">
            <div class="titulo-bloco">
                <h1><i class="fa-solid fa-droplet"></i> Controle de Tintas Epson</h1>
                <p class="subtitulo">Uma visão rápida e simples para encontrar o que precisa, sem poluir a tela.</p>
            </div>

            <a class="botao" href="funcoes/cadastrar.php">
                <i class="fa-solid fa-plus"></i> Nova Tinta
            </a>
            <a href="impressora/impressoras.php" class="botao-menu">
                <i class="fa-solid fa-print"></i> Impressoras
            </a>
        </div>

        <?php require __DIR__ . '/includes/mensagem_flash.php'; ?>

        <section class="cards-resumo cards-resumo-clean">
            <div class="card-resumo card-vencida">
                <div class="icone-resumo"><i class="fa-solid fa-circle-xmark"></i></div>
                <div>
                    <strong><?= e($resumo['vencidas']) ?></strong>
                    <span>Tintas vencidas</span>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <strong><?= e($resumo['vence_breve']) ?></strong>
                    <span>Vencem em breve</span>
                </div>
            </div>

            <div class="card-resumo card-compra-urgente">
                <div class="icone-resumo"><i class="fa-solid fa-cart-shopping"></i></div>
                <div>
                    <strong><?= e($resumo['compra_urgente']) ?></strong>
                    <span>Compra urgente</span>
                </div>
            </div>

            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div>
                    <strong><?= e($quantidadeTotalFiltrada) ?></strong>
                    <span>Quantidade filtrada</span>
                </div>
            </div>
        </section>

        <section class="cards-menu">
            <div class="card-menu">
                <h3><i class="fa-solid fa-print"></i> Impressoras</h3>
                <p>Gerenciar impressoras e níveis de tinta</p>
                <a href="impressora/impressoras.php" class="btn-entrar">
                    <i class="fa-solid fa-arrow-right"></i> Acessar
                </a>
            </div>
        </section>

        <form method="GET" class="painel-filtros painel-filtros-index">
            <div class="campo-filtro campo-busca">
                <label for="busca">
                    <i class="fa-solid fa-magnifying-glass"></i> Pesquisa
                </label>
                <input
                    id="busca"
                    type="text"
                    name="busca"
                    placeholder="Pesquisar por impressora, modelo ou cor"
                    value="<?= e($filtros['busca']) ?>"
                >
            </div>

            <div class="campo-filtro">
                <label for="modelo">
                    <i class="fa-solid fa-box"></i> Modelo
                </label>
                <select id="modelo" name="modelo">
                    <option value="">Todos</option>
                    <?php foreach ($opcoesModelos as $modelo): ?>
                        <option value="<?= e($modelo) ?>" <?= $filtros['modelo'] === $modelo ? 'selected' : '' ?>>
                            <?= e($modelo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo-filtro">
                <label for="cor">
                    <i class="fa-solid fa-palette"></i> Cor
                </label>
                <select id="cor" name="cor">
                    <option value="">Todas</option>
                    <?php foreach ($opcoesCores as $cor): ?>
                        <option value="<?= e($cor) ?>" <?= $filtros['cor'] === $cor ? 'selected' : '' ?>>
                            <?= e(strtoupper($cor)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo-filtro">
                <label for="status_validade">
                    <i class="fa-solid fa-calendar-xmark"></i> Validade
                </label>
                <select id="status_validade" name="status_validade">
                    <option value="">Todas</option>
                    <option value="valida" <?= $filtros['status_validade'] === 'valida' ? 'selected' : '' ?>>Válida</option>
                    <option value="proxima" <?= $filtros['status_validade'] === 'proxima' ? 'selected' : '' ?>>Vence em breve</option>
                    <option value="vencida" <?= $filtros['status_validade'] === 'vencida' ? 'selected' : '' ?>>Vencida</option>
                </select>
            </div>

            <div class="campo-filtro">
                <label for="status_compra">
                    <i class="fa-solid fa-cart-shopping"></i> Compra
                </label>
                <select id="status_compra" name="status_compra">
                    <option value="">Todas</option>
                    <option value="ok" <?= $filtros['status_compra'] === 'ok' ? 'selected' : '' ?>>Estoque ok</option>
                    <option value="baixo" <?= $filtros['status_compra'] === 'baixo' ? 'selected' : '' ?>>Comprar em breve</option>
                    <option value="urgente" <?= $filtros['status_compra'] === 'urgente' ? 'selected' : '' ?>>Compra urgente</option>
                </select>
            </div>

            <div class="acoes-filtros">
                <button type="submit" class="botao botao-filtro">
                    <i class="fa-solid fa-filter"></i> Pesquisar
                </button>

                <a href="index.php" class="botao botao-filtro">
                    <i class="fa-solid fa-rotate-left"></i> Limpar
                </a>
            </div>
        </form>

        <section class="resumo-mastigado">
            <div class="resumo-mastigado__icone">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div>
                <h2>Leitura rápida</h2>
                <p>
                    <?php if (!empty($resumoMastigado)): ?>
                        <?= e(implode(' • ', $resumoMastigado)) ?>.
                    <?php else: ?>
                        Use os filtros para encontrar rapidamente o que você precisa.
                    <?php endif; ?>
                </p>
            </div>
        </section>

        <section class="graficos-dashboard">
            <article class="grafico-card grafico-card-donut">
                <div class="secao-titulo secao-titulo-clean">
                    <div>
                        <h2><i class="fa-solid fa-chart-pie"></i> Estoque por cor</h2>
                        <p>Mostra a distribuição das quantidades filtradas.</p>
                    </div>
                </div>
                <div class="grafico-canvas-wrap grafico-canvas-wrap-donut">
                    <canvas id="graficoCores"></canvas>
                </div>
            </article>

            <article class="grafico-card">
                <div class="secao-titulo secao-titulo-clean">
                    <div>
                        <h2><i class="fa-solid fa-chart-column"></i> Quantidade por modelo</h2>
                        <p>Top modelos com maior quantidade no filtro atual.</p>
                    </div>
                </div>
                <div class="grafico-canvas-wrap">
                    <canvas id="graficoModelos"></canvas>
                </div>
            </article>

            <article class="grafico-card grafico-card-wide">
                <div class="secao-titulo secao-titulo-clean">
                    <div>
                        <h2><i class="fa-solid fa-chart-bar"></i> Alertas por modelo</h2>
                        <p>Leitura visual do que pede atenção primeiro.</p>
                    </div>
                </div>
                <div class="grafico-canvas-wrap grafico-canvas-wrap-alertas">
                    <canvas id="graficoAlertas"></canvas>
                </div>
            </article>
        </section>

        <section class="bloco-detalhes bloco-resultado-filtro bloco-resultado-filtro-clean">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div>
                    <h2>Modelos</h2>
                    <p>
                        <?= e($totalModelosEncontrados) ?> modelo(s)
                        <?php if ($filtroAtivo): ?>
                            encontrados com os filtros aplicados.
                        <?php else: ?>
                            disponíveis no sistema.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </section>

        <?php if (!empty($modelos)): ?>
            <div class="grid-modelos grid-modelos-clean">
                <?php foreach ($modelos as $itemModelo): ?>
                    <a class="card-modelo card-modelo-clean" href="detalhes.php?modelo=<?= urlencode((string) $itemModelo['modelo']) ?>">
                        <div class="card-modelo-clean__topo">
                            <div>
                                <h2>Modelo <?= e($itemModelo['modelo']) ?></h2>
                                <p>Toque para ver os lotes, validades e ações.</p>
                            </div>

                            <span class="status-pill <?= e($itemModelo['status_modelo']['classe']) ?>">
                                <i class="fa-solid <?= e($itemModelo['status_modelo']['icone']) ?>"></i>
                                <?= e($itemModelo['status_modelo']['label']) ?>
                            </span>
                        </div>

                        <div class="card-modelo-clean__metricas">
                            <div class="mini-info">
                                <span class="mini-label">Quantidade</span>
                                <strong><?= e($itemModelo['total_quantidade'] ?? 0) ?></strong>
                            </div>
                            <div class="mini-info">
                                <span class="mini-label">Cores</span>
                                <strong><?= e($itemModelo['total_cores']) ?></strong>
                            </div>
                            <div class="mini-info">
                                <span class="mini-label">Menor validade</span>
                                <strong><?= e($itemModelo['menor_validade']) ?></strong>
                            </div>
                        </div>

                        <div class="card-modelo-clean__rodape">
                            <div class="tags-alerta-card">
                                <?php if (!empty($itemModelo['alertas_modelo'])): ?>
                                    <?php foreach (array_slice($itemModelo['alertas_modelo'], 0, 2) as $alerta): ?>
                                        <span class="tag-alerta <?= e($alerta['classe_tag']) ?>">
                                            <i class="fa-solid <?= e($alerta['icone']) ?>"></i>
                                            <?= e($alerta['texto']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="tag-alerta tag-azul">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Sem alertas críticos
                                    </span>
                                <?php endif; ?>
                            </div>

                            <span class="ver-detalhes">
                                Ver detalhes <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="estado-vazio">
                <i class="fa-solid fa-box-open"></i>
                <h2>Nenhum modelo encontrado</h2>
                <p>Tente ajustar a pesquisa ou limpar os filtros.</p>
            </div>
        <?php endif; ?>

        <div class="painel-compras painel-compras-clean">
            <div class="secao-titulo secao-titulo-clean">
                <div>
                    <h2><i class="fa-solid fa-cart-shopping"></i> Lista de compras sugerida</h2>
                    <p>Ordenada por prioridade para o usuário bater o olho e agir.</p>
                </div>
            </div>

            <?php if (!empty($listaCompras)): ?>
                <div class="tabela-wrapper">
                    <table>
                        <tr>
                            <th>Modelo</th>
                            <th>Cor</th>
                            <th>Quantidade total</th>
                            <th>Status</th>
                        </tr>
                        <?php foreach ($listaCompras as $item): ?>
                            <tr>
                                <td><?= e($item['modelo']) ?></td>
                                <td class="coluna-cor">
                                    <span class="cor-wrap">
                                        <span class="dot-cor <?= e($item['dot_classe']) ?>"></span>
                                        <span class="badge-cor <?= e($item['cor_classe']) ?>">
                                            <?= e(strtoupper($item['cor'])) ?>
                                        </span>
                                    </span>
                                </td>
                                <td><?= e($item['quantidade_total']) ?></td>
                                <td>
                                    <span class="status-pill <?= e($item['status_compra']['classe']) ?>">
                                        <i class="fa-solid <?= e($item['status_compra']['icone']) ?>"></i>
                                        <?= e($item['status_compra']['label']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else: ?>
                <div class="estado-vazio">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <h2>Nenhum item para exibir</h2>
                    <p>A lista de compras não possui itens com os filtros atuais.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const dadosGraficoCores = <?= json_encode($graficoCores, $jsonFlagsDashboard) ?>;
        const dadosGraficoModelos = <?= json_encode($graficoModelos, $jsonFlagsDashboard) ?>;
        const dadosGraficoAlertas = <?= json_encode($graficoAlertas, $jsonFlagsDashboard) ?>;

        const opcoesBase = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        color: '#334155',
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#e2e8f0',
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: true
                }
            }
        };

        const paleta = ['#2563eb', '#ec4899', '#f59e0b', '#111827', '#10b981', '#8b5cf6', '#f97316', '#06b6d4'];
        const paletaPorCor = {
            'C': '#06b6d4',
            'M': '#ec4899',
            'Y': '#facc15',
            'BK': '#111827',
            'K': '#111827',
            'BLACK': '#111827'
        };

        const desenharGraficoCores = () => {
            const canvas = document.getElementById('graficoCores');
            if (!canvas || !dadosGraficoCores.labels.length) {
                return;
            }

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: dadosGraficoCores.labels,
                    datasets: [{
                        data: dadosGraficoCores.data,
                        backgroundColor: dadosGraficoCores.labels.map((label, idx) => {
                            const chave = String(label || '').trim().toUpperCase();
                            return paletaPorCor[chave] ?? paleta[idx % paleta.length];
                        }),
                        borderColor: '#ffffff',
                        borderWidth: 4,
                        hoverOffset: 10
                    }]
                },
                options: {
                    ...opcoesBase,
                    cutout: '66%',
                    plugins: {
                        ...opcoesBase.plugins,
                        legend: {
                            position: 'bottom',
                            labels: {
                                ...opcoesBase.plugins.legend.labels,
                                padding: 18
                            }
                        }
                    }
                }
            });
        };

        const desenharGraficoModelos = () => {
            const canvas = document.getElementById('graficoModelos');
            if (!canvas || !dadosGraficoModelos.labels.length) {
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dadosGraficoModelos.labels,
                    datasets: [{
                        label: 'Quantidade',
                        data: dadosGraficoModelos.data,
                        backgroundColor: paleta,
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 42
                    }]
                },
                options: {
                    ...opcoesBase,
                    plugins: {
                        ...opcoesBase.plugins,
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: '#475569',
                                font: { weight: '600' }
                            },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#64748b'
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.15)'
                            }
                        }
                    }
                }
            });
        };

        const desenharGraficoAlertas = () => {
            const canvas = document.getElementById('graficoAlertas');
            if (!canvas || !dadosGraficoAlertas.labels.length) {
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dadosGraficoAlertas.labels,
                    datasets: [{
                        label: 'Modelos',
                        data: dadosGraficoAlertas.data,
                        backgroundColor: ['#ef4444', '#f59e0b', '#f97316', '#3b82f6'],
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 28
                    }]
                },
                options: {
                    ...opcoesBase,
                    indexAxis: 'y',
                    plugins: {
                        ...opcoesBase.plugins,
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#64748b'
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.15)'
                            }
                        },
                        y: {
                            ticks: {
                                color: '#334155',
                                font: { weight: '600' }
                            },
                            grid: { display: false }
                        }
                    }
                }
            });
        };

        desenharGraficoCores();
        desenharGraficoModelos();
        desenharGraficoAlertas();
    </script>
</body>
</html>

