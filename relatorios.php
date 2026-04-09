<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$mensagem = obter_mensagem_flash();
$impressoras = [];
$totais = [
    'paginas_total' => 0,
    'paginas_pb' => 0,
    'paginas_cor' => 0,
];
$ultimaAtualizacaoRecente = null;
$consumoPorPeriodo = [
    'dia' => [],
    'semana' => [],
    'mes' => [],
];
$relatorioPeriodo = [
    'dia' => [],
    'semana' => [],
    'mes' => [],
];
$periodosRelatorio = [];

$consumoZero = static function (): array {
    return [
        'paginas_total' => 0,
        'paginas_pb' => 0,
        'paginas_cor' => 0,
    ];
};

$calcularConsumoHistoricoPorPeriodo = static function (mysqli $conn, string $inicio, string $fim) use ($consumoZero): array {
    $sql = 'SELECT
                impressora_id,
                paginas_total,
                paginas_pb,
                paginas_cor,
                data_hora,
                id
            FROM historico_impressoras
            WHERE data_hora >= ? AND data_hora <= ?
            ORDER BY impressora_id ASC, data_hora ASC, id ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Falha ao preparar consulta de historico por periodo: ' . $conn->error);
        return [];
    }

    $stmt->bind_param('ss', $inicio, $fim);
    if (!$stmt->execute()) {
        error_log('Falha ao executar consulta de historico por periodo: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();
    if (!($resultado instanceof mysqli_result)) {
        $stmt->close();
        return [];
    }

    $primeiroUltimo = [];

    while ($linha = $resultado->fetch_assoc()) {
        $impressoraId = (int) ($linha['impressora_id'] ?? 0);
        if ($impressoraId <= 0) {
            continue;
        }

        $paginasTotal = isset($linha['paginas_total']) ? (int) $linha['paginas_total'] : null;
        $paginasPb = isset($linha['paginas_pb']) ? (int) $linha['paginas_pb'] : null;
        $paginasCor = isset($linha['paginas_cor']) ? (int) $linha['paginas_cor'] : null;

        if (!isset($primeiroUltimo[$impressoraId])) {
            $primeiroUltimo[$impressoraId] = [
                'primeiro_total' => null,
                'ultimo_total' => null,
                'qtd_total' => 0,
                'primeiro_pb' => null,
                'ultimo_pb' => null,
                'qtd_pb' => 0,
                'primeiro_cor' => null,
                'ultimo_cor' => null,
                'qtd_cor' => 0,
            ];
        }

        if ($paginasTotal !== null) {
            if ($primeiroUltimo[$impressoraId]['qtd_total'] === 0) {
                $primeiroUltimo[$impressoraId]['primeiro_total'] = $paginasTotal;
            }
            $primeiroUltimo[$impressoraId]['ultimo_total'] = $paginasTotal;
            $primeiroUltimo[$impressoraId]['qtd_total']++;
        }

        if ($paginasPb !== null) {
            if ($primeiroUltimo[$impressoraId]['qtd_pb'] === 0) {
                $primeiroUltimo[$impressoraId]['primeiro_pb'] = $paginasPb;
            }
            $primeiroUltimo[$impressoraId]['ultimo_pb'] = $paginasPb;
            $primeiroUltimo[$impressoraId]['qtd_pb']++;
        }

        if ($paginasCor !== null) {
            if ($primeiroUltimo[$impressoraId]['qtd_cor'] === 0) {
                $primeiroUltimo[$impressoraId]['primeiro_cor'] = $paginasCor;
            }
            $primeiroUltimo[$impressoraId]['ultimo_cor'] = $paginasCor;
            $primeiroUltimo[$impressoraId]['qtd_cor']++;
        }
    }

    $resultado->free();
    $stmt->close();

    $consumoPorImpressora = [];
    foreach ($primeiroUltimo as $impressoraId => $dados) {
        $consumo = $consumoZero();

        if ($dados['qtd_total'] >= 2 && $dados['primeiro_total'] !== null && $dados['ultimo_total'] !== null) {
            $consumo['paginas_total'] = (int) $dados['ultimo_total'] - (int) $dados['primeiro_total'];
        }

        if ($dados['qtd_pb'] >= 2 && $dados['primeiro_pb'] !== null && $dados['ultimo_pb'] !== null) {
            $consumo['paginas_pb'] = (int) $dados['ultimo_pb'] - (int) $dados['primeiro_pb'];
        }

        if ($dados['qtd_cor'] >= 2 && $dados['primeiro_cor'] !== null && $dados['ultimo_cor'] !== null) {
            $consumo['paginas_cor'] = (int) $dados['ultimo_cor'] - (int) $dados['primeiro_cor'];
        }

        $consumoPorImpressora[(int) $impressoraId] = $consumo;
    }

    return $consumoPorImpressora;
};

$formatarDataHora = static function (?string $valor): string {
    $valorNormalizado = trim((string) $valor);
    if ($valorNormalizado === '') {
        return 'Sem dados';
    }

    $data = date_create($valorNormalizado);
    if (!$data) {
        return $valorNormalizado;
    }

    return $data->format('d/m/Y H:i');
};

$consulta = $conn->query(
    'SELECT
        id,
        nome,
        paginas_total,
        paginas_pb,
        paginas_cor,
        a4_pb_simples,
        a4_cor_simples,
        a4_pb_duplex,
        a4_cor_duplex,
        ultima_atualizacao
     FROM impressoras
     ORDER BY nome ASC'
);

if ($consulta instanceof mysqli_result) {
    while ($linha = $consulta->fetch_assoc()) {
        $impressoraId = (int) ($linha['id'] ?? 0);
        $paginasTotal = (int) ($linha['paginas_total'] ?? 0);
        $paginasPb = (int) ($linha['paginas_pb'] ?? 0);
        $paginasCor = (int) ($linha['paginas_cor'] ?? 0);
        $ultimaAtualizacao = trim((string) ($linha['ultima_atualizacao'] ?? ''));

        $impressoras[] = [
            'id' => $impressoraId,
            'nome' => trim((string) ($linha['nome'] ?? '')),
            'paginas_total' => $paginasTotal,
            'paginas_pb' => $paginasPb,
            'paginas_cor' => $paginasCor,
            'a4_pb_simples' => (int) ($linha['a4_pb_simples'] ?? 0),
            'a4_cor_simples' => (int) ($linha['a4_cor_simples'] ?? 0),
            'a4_pb_duplex' => (int) ($linha['a4_pb_duplex'] ?? 0),
            'a4_cor_duplex' => (int) ($linha['a4_cor_duplex'] ?? 0),
            'ultima_atualizacao' => $ultimaAtualizacao,
        ];

        $totais['paginas_total'] += $paginasTotal;
        $totais['paginas_pb'] += $paginasPb;
        $totais['paginas_cor'] += $paginasCor;

        if ($ultimaAtualizacao !== '' && ($ultimaAtualizacaoRecente === null || $ultimaAtualizacao > $ultimaAtualizacaoRecente)) {
            $ultimaAtualizacaoRecente = $ultimaAtualizacao;
        }
    }

    $consulta->free();
} else {
    error_log('Falha ao carregar relatorios de impressoras: ' . $conn->error);
    $mensagem = [
        'tipo' => 'erro',
        'texto' => 'Nao foi possivel carregar os dados de relatorio no momento.',
    ];
}

$agora = new DateTimeImmutable('now');
$inicioDia = $agora->setTime(0, 0, 0);
$fimDia = $agora->setTime(23, 59, 59);

$indiceDiaSemana = (int) $agora->format('N');
$inicioSemana = $agora->setTime(0, 0, 0)->modify('-' . ($indiceDiaSemana - 1) . ' days');
$fimSemana = $inicioSemana->modify('+6 days')->setTime(23, 59, 59);

$inicioMes = $agora->modify('first day of this month')->setTime(0, 0, 0);
$fimMes = $agora->modify('last day of this month')->setTime(23, 59, 59);

$periodosRelatorio = [
    'dia' => [
        'inicio' => $inicioDia->format('Y-m-d H:i:s'),
        'fim' => $fimDia->format('Y-m-d H:i:s'),
    ],
    'semana' => [
        'inicio' => $inicioSemana->format('Y-m-d H:i:s'),
        'fim' => $fimSemana->format('Y-m-d H:i:s'),
    ],
    'mes' => [
        'inicio' => $inicioMes->format('Y-m-d H:i:s'),
        'fim' => $fimMes->format('Y-m-d H:i:s'),
    ],
];

$consumoPorPeriodo = [
    'dia' => $calcularConsumoHistoricoPorPeriodo($conn, $periodosRelatorio['dia']['inicio'], $periodosRelatorio['dia']['fim']),
    'semana' => $calcularConsumoHistoricoPorPeriodo($conn, $periodosRelatorio['semana']['inicio'], $periodosRelatorio['semana']['fim']),
    'mes' => $calcularConsumoHistoricoPorPeriodo($conn, $periodosRelatorio['mes']['inicio'], $periodosRelatorio['mes']['fim']),
];

foreach ($impressoras as &$impressora) {
    $impressoraId = (int) ($impressora['id'] ?? 0);
    $nomeImpressora = trim((string) ($impressora['nome'] ?? ''));

    $consumoDia = $consumoPorPeriodo['dia'][$impressoraId] ?? $consumoZero();
    $consumoSemana = $consumoPorPeriodo['semana'][$impressoraId] ?? $consumoZero();
    $consumoMes = $consumoPorPeriodo['mes'][$impressoraId] ?? $consumoZero();

    $impressora['consumo_periodo'] = [
        'dia' => $consumoDia,
        'semana' => $consumoSemana,
        'mes' => $consumoMes,
    ];

    $relatorioPeriodo['dia'][] = array_merge(['impressora_id' => $impressoraId, 'nome' => $nomeImpressora], $consumoDia);
    $relatorioPeriodo['semana'][] = array_merge(['impressora_id' => $impressoraId, 'nome' => $nomeImpressora], $consumoSemana);
    $relatorioPeriodo['mes'][] = array_merge(['impressora_id' => $impressoraId, 'nome' => $nomeImpressora], $consumoMes);
}
unset($impressora);

$totaisConsumoPeriodo = [
    'dia' => $consumoZero(),
    'semana' => $consumoZero(),
    'mes' => $consumoZero(),
];

foreach (['dia', 'semana', 'mes'] as $periodo) {
    foreach ($relatorioPeriodo[$periodo] as $linhaPeriodo) {
        $totaisConsumoPeriodo[$periodo]['paginas_total'] += (int) ($linhaPeriodo['paginas_total'] ?? 0);
        $totaisConsumoPeriodo[$periodo]['paginas_pb'] += (int) ($linhaPeriodo['paginas_pb'] ?? 0);
        $totaisConsumoPeriodo[$periodo]['paginas_cor'] += (int) ($linhaPeriodo['paginas_cor'] ?? 0);
    }
}

$conn->close();

$tituloPagina = 'Relatorios';
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "";
        $paginaAtual = "relatorios";
        $paginaTitulo = "Relatorios";
        $paginaDescricao = "Leitura visual de paginas e uso A4 das impressoras";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean">
        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-chart-column"></i>
                </div>
                <div>
                    <h2>Relatorios de impressoras</h2>
                    <p>Painel consolidado com totais gerais, consumo por periodo e uso A4.</p>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/includes/mensagem_flash.php'; ?>

        <section class="cards-resumo cards-resumo-clean">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <strong><?= $totais['paginas_total'] ?></strong>
                    <span>Total geral de paginas</span>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-print"></i></div>
                <div>
                    <strong><?= $totais['paginas_pb'] ?></strong>
                    <span>Total geral PB</span>
                </div>
            </div>

            <div class="card-resumo card-compra-urgente">
                <div class="icone-resumo"><i class="fa-solid fa-palette"></i></div>
                <div>
                    <strong><?= $totais['paginas_cor'] ?></strong>
                    <span>Total geral Cor</span>
                </div>
            </div>

            <div class="card-resumo card-vencida">
                <div class="icone-resumo"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <strong><?= e($formatarDataHora($ultimaAtualizacaoRecente)) ?></strong>
                    <span>Ultima atualizacao</span>
                </div>
            </div>
        </section>

        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <h2>Consumo por periodo</h2>
                    <p>Totais calculados pelo historico: ultimo contador menos primeiro contador do periodo.</p>
                </div>
            </div>
        </section>

        <section class="cards-resumo cards-resumo-clean">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-sun"></i></div>
                <div>
                    <strong><?= (int) $totaisConsumoPeriodo['dia']['paginas_total'] ?></strong>
                    <span>Consumo do dia</span>
                    <small>PB: <?= (int) $totaisConsumoPeriodo['dia']['paginas_pb'] ?> | Cor: <?= (int) $totaisConsumoPeriodo['dia']['paginas_cor'] ?></small>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-calendar-week"></i></div>
                <div>
                    <strong><?= (int) $totaisConsumoPeriodo['semana']['paginas_total'] ?></strong>
                    <span>Consumo da semana</span>
                    <small>PB: <?= (int) $totaisConsumoPeriodo['semana']['paginas_pb'] ?> | Cor: <?= (int) $totaisConsumoPeriodo['semana']['paginas_cor'] ?></small>
                </div>
            </div>

            <div class="card-resumo card-compra-urgente">
                <div class="icone-resumo"><i class="fa-solid fa-calendar"></i></div>
                <div>
                    <strong><?= (int) $totaisConsumoPeriodo['mes']['paginas_total'] ?></strong>
                    <span>Consumo do mes</span>
                    <small>PB: <?= (int) $totaisConsumoPeriodo['mes']['paginas_pb'] ?> | Cor: <?= (int) $totaisConsumoPeriodo['mes']['paginas_cor'] ?></small>
                </div>
            </div>
        </section>

        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-table"></i>
                </div>
                <div>
                    <h2>Consumo por impressora</h2>
                    <p>Dia: total, PB e Cor. Semana e mes: total consumido por periodo.</p>
                </div>
            </div>

            <div class="tabela-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Impressora</th>
                            <th>Total do dia</th>
                            <th>PB do dia</th>
                            <th>Cor do dia</th>
                            <th>Total da semana</th>
                            <th>Total do mes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($impressoras)): ?>
                            <?php foreach ($impressoras as $impressora): ?>
                                <?php $consumoImpressora = $impressora['consumo_periodo'] ?? ['dia' => $consumoZero(), 'semana' => $consumoZero(), 'mes' => $consumoZero()]; ?>
                                <tr>
                                    <td><?= e($impressora['nome']) ?></td>
                                    <td><?= (int) ($consumoImpressora['dia']['paginas_total'] ?? 0) ?></td>
                                    <td><?= (int) ($consumoImpressora['dia']['paginas_pb'] ?? 0) ?></td>
                                    <td><?= (int) ($consumoImpressora['dia']['paginas_cor'] ?? 0) ?></td>
                                    <td><?= (int) ($consumoImpressora['semana']['paginas_total'] ?? 0) ?></td>
                                    <td><?= (int) ($consumoImpressora['mes']['paginas_total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="vazio">Nenhuma impressora encontrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-copy"></i>
                </div>
                <div>
                    <h2>Uso A4</h2>
                    <p>Contadores da linha A4/Letter separados por simplex e duplex.</p>
                </div>
            </div>

            <div class="tabela-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Impressora</th>
                            <th>A4 PB simples</th>
                            <th>A4 Cor simples</th>
                            <th>A4 PB duplex</th>
                            <th>A4 Cor duplex</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($impressoras)): ?>
                            <?php foreach ($impressoras as $impressora): ?>
                                <tr>
                                    <td><?= e($impressora['nome']) ?></td>
                                    <td><?= (int) $impressora['a4_pb_simples'] ?></td>
                                    <td><?= (int) $impressora['a4_cor_simples'] ?></td>
                                    <td><?= (int) $impressora['a4_pb_duplex'] ?></td>
                                    <td><?= (int) $impressora['a4_cor_duplex'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="vazio">Nenhum dado de uso A4 encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
