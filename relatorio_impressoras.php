<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$filtros = [
    'mes_referencia' => trim((string) ($_GET['mes_referencia'] ?? '')),
    'semana_inicio' => trim((string) ($_GET['semana_inicio'] ?? '')),
    'tipo_leitura' => trim((string) ($_GET['tipo_leitura'] ?? 'total')),
    'tipo_cor' => trim((string) ($_GET['tipo_cor'] ?? 'geral')),
    'busca' => trim((string) ($_GET['busca'] ?? '')),
];

$normalizarSelecao = static function (string $valor, array $permitidos, string $padrao): string {
    return in_array($valor, $permitidos, true) ? $valor : $padrao;
};

$formatarNumero = static function ($valor): string {
    $numero = is_numeric($valor) ? (int) $valor : 0;
    return number_format($numero, 0, ',', '.');
};

$formatarDataHora = static function (?string $valor): string {
    $texto = trim((string) $valor);
    if ($texto === '') {
        return 'Sem dados';
    }

    $data = date_create($texto);
    if (!$data) {
        return $texto;
    }

    return $data->format('d/m/Y H:i');
};

$somarCamposHistorico = static function (array $linha, array $campos): ?int {
    $total = 0;
    $temDado = false;

    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $linha) || $linha[$campo] === null || $linha[$campo] === '') {
            continue;
        }

        $temDado = true;
        $total += (int) $linha[$campo];
    }

    return $temDado ? $total : null;
};

$somarCamposAtuais = static function (array $linha, array $campos): int {
    $total = 0;

    foreach ($campos as $campo) {
        $total += (int) ($linha[$campo] ?? 0);
    }

    return $total;
};

$detectarFormatoAtual = static function (array $impressora): array {
    $temA4 = (int) ($impressora['a4_total_atual'] ?? 0) > 0;
    $temA3 = (int) ($impressora['a3_total_atual'] ?? 0) > 0;

    if ($temA4 && $temA3) {
        return ['label' => 'A3 + A4', 'classe' => 'impressora-pill--misto'];
    }

    if ($temA3) {
        return ['label' => 'A3', 'classe' => 'impressora-pill--a3'];
    }

    if ($temA4) {
        return ['label' => 'A4', 'classe' => 'impressora-pill--a4'];
    }

    return ['label' => 'Sem leitura', 'classe' => 'impressora-pill--sync'];
};

$obterValorHistoricoFiltrado = static function (array $linha, string $tipoLeitura, string $tipoCor) use ($somarCamposHistorico): ?int {
    if ($tipoLeitura === 'total') {
        return match ($tipoCor) {
            'pb' => isset($linha['paginas_pb']) ? (int) $linha['paginas_pb'] : null,
            'cor' => isset($linha['paginas_cor']) ? (int) $linha['paginas_cor'] : null,
            default => isset($linha['paginas_total']) ? (int) $linha['paginas_total'] : null,
        };
    }

    $prefixo = $tipoLeitura === 'a3' ? 'a3_' : 'a4_';

    return match ($tipoCor) {
        'pb' => $somarCamposHistorico($linha, [$prefixo . 'pb_simples', $prefixo . 'pb_duplex']),
        'cor' => $somarCamposHistorico($linha, [$prefixo . 'cor_simples', $prefixo . 'cor_duplex']),
        default => $somarCamposHistorico($linha, [
            $prefixo . 'pb_simples',
            $prefixo . 'cor_simples',
            $prefixo . 'pb_duplex',
            $prefixo . 'cor_duplex',
        ]),
    };
};

$obterValorAtualFiltrado = static function (array $impressora, string $tipoLeitura, string $tipoCor) use ($somarCamposAtuais): int {
    if ($tipoLeitura === 'total') {
        return match ($tipoCor) {
            'pb' => (int) ($impressora['paginas_pb'] ?? 0),
            'cor' => (int) ($impressora['paginas_cor'] ?? 0),
            default => (int) ($impressora['paginas_total'] ?? 0),
        };
    }

    $prefixo = $tipoLeitura === 'a3' ? 'a3_' : 'a4_';

    return match ($tipoCor) {
        'pb' => $somarCamposAtuais($impressora, [$prefixo . 'pb_simples', $prefixo . 'pb_duplex']),
        'cor' => $somarCamposAtuais($impressora, [$prefixo . 'cor_simples', $prefixo . 'cor_duplex']),
        default => $somarCamposAtuais($impressora, [
            $prefixo . 'pb_simples',
            $prefixo . 'cor_simples',
            $prefixo . 'pb_duplex',
            $prefixo . 'cor_duplex',
        ]),
    };
};

$obterResumoAtual = static function (array $impressora, string $tipoLeitura) use ($formatarNumero, $somarCamposAtuais): string {
    if ($tipoLeitura === 'total') {
        return 'PB ' . $formatarNumero($impressora['paginas_pb'] ?? 0) . ' | Cor ' . $formatarNumero($impressora['paginas_cor'] ?? 0);
    }

    $prefixo = $tipoLeitura === 'a3' ? 'a3_' : 'a4_';
    $pbAtual = $somarCamposAtuais($impressora, [$prefixo . 'pb_simples', $prefixo . 'pb_duplex']);
    $corAtual = $somarCamposAtuais($impressora, [$prefixo . 'cor_simples', $prefixo . 'cor_duplex']);

    return 'PB ' . $formatarNumero($pbAtual) . ' | Cor ' . $formatarNumero($corAtual);
};

$criarOpcoesSemanaMes = static function (DateTimeImmutable $inicioMes, DateTimeImmutable $fimMes): array {
    $opcoes = [];
    $cursor = $inicioMes->modify('-' . (((int) $inicioMes->format('N')) - 1) . ' days')->setTime(0, 0, 0);

    while ($cursor <= $fimMes) {
        $inicioSemanaCompleta = $cursor;
        $fimSemanaCompleta = $cursor->modify('+6 days')->setTime(23, 59, 59);

        if ($fimSemanaCompleta < $inicioMes) {
            $cursor = $cursor->modify('+7 days');
            continue;
        }

        $inicioRecorte = $inicioSemanaCompleta < $inicioMes ? $inicioMes : $inicioSemanaCompleta;
        $fimRecorte = $fimSemanaCompleta > $fimMes ? $fimMes : $fimSemanaCompleta;

        $opcoes[] = [
            'valor' => $inicioSemanaCompleta->format('Y-m-d'),
            'inicio' => $inicioRecorte,
            'fim' => $fimRecorte,
            'label' => 'Semana ' . $inicioSemanaCompleta->format('W') . ' | ' . $inicioRecorte->format('d/m') . ' a ' . $fimRecorte->format('d/m/Y'),
        ];

        $cursor = $cursor->modify('+7 days');
    }

    return $opcoes;
};

$metaTipoLeitura = [
    'total' => ['label' => 'Total de paginas', 'descricao' => 'Leitura geral do contador'],
    'a4' => ['label' => 'Uso A4', 'descricao' => 'Leitura da linha A4/Letter'],
    'a3' => ['label' => 'Uso A3', 'descricao' => 'Leitura da linha A3/Ledger'],
];

$metaTipoCor = [
    'geral' => 'Geral',
    'pb' => 'Preto e branco',
    'cor' => 'Colorido',
];

$mesPadrao = (new DateTimeImmutable('now'))->modify('first day of this month')->setTime(0, 0, 0);
$filtros['mes_referencia'] = $filtros['mes_referencia'] !== '' ? $filtros['mes_referencia'] : $mesPadrao->format('Y-m');
$filtros['tipo_leitura'] = $normalizarSelecao($filtros['tipo_leitura'], ['total', 'a4', 'a3'], 'total');
$filtros['tipo_cor'] = $normalizarSelecao($filtros['tipo_cor'], ['geral', 'pb', 'cor'], 'geral');

$mesReferencia = DateTimeImmutable::createFromFormat('!Y-m', $filtros['mes_referencia']);
if (!$mesReferencia instanceof DateTimeImmutable) {
    $mesReferencia = $mesPadrao;
    $filtros['mes_referencia'] = $mesPadrao->format('Y-m');
}

$inicioMes = $mesReferencia->modify('first day of this month')->setTime(0, 0, 0);
$fimMes = $mesReferencia->modify('last day of this month')->setTime(23, 59, 59);
$opcoesSemana = $criarOpcoesSemanaMes($inicioMes, $fimMes);

$semanaSelecionada = null;
foreach ($opcoesSemana as $opcaoSemana) {
    if ($opcaoSemana['valor'] === $filtros['semana_inicio']) {
        $semanaSelecionada = $opcaoSemana;
        break;
    }
}

if ($semanaSelecionada !== null) {
    $periodoInicio = $semanaSelecionada['inicio'];
    $periodoFim = $semanaSelecionada['fim'];
    $periodoLabel = $semanaSelecionada['label'];
} else {
    $filtros['semana_inicio'] = '';
    $periodoInicio = $inicioMes;
    $periodoFim = $fimMes;
    $periodoLabel = 'Mes inteiro | ' . $inicioMes->format('d/m') . ' a ' . $fimMes->format('d/m/Y');
}

$consultaImpressoras = $conn->query(
    'SELECT
        id,
        nome,
        modelo,
        paginas_total,
        paginas_pb,
        paginas_cor,
        a4_pb_simples,
        a4_cor_simples,
        a4_pb_duplex,
        a4_cor_duplex,
        a3_pb_simples,
        a3_cor_simples,
        a3_pb_duplex,
        a3_cor_duplex,
        ultima_atualizacao
     FROM impressoras
     ORDER BY nome ASC'
);

$impressoras = [];
if ($consultaImpressoras instanceof mysqli_result) {
    while ($linha = $consultaImpressoras->fetch_assoc()) {
        $impressora = [
            'id' => (int) ($linha['id'] ?? 0),
            'nome' => trim((string) ($linha['nome'] ?? '')),
            'modelo' => trim((string) ($linha['modelo'] ?? '')),
            'paginas_total' => (int) ($linha['paginas_total'] ?? 0),
            'paginas_pb' => (int) ($linha['paginas_pb'] ?? 0),
            'paginas_cor' => (int) ($linha['paginas_cor'] ?? 0),
            'a4_pb_simples' => (int) ($linha['a4_pb_simples'] ?? 0),
            'a4_cor_simples' => (int) ($linha['a4_cor_simples'] ?? 0),
            'a4_pb_duplex' => (int) ($linha['a4_pb_duplex'] ?? 0),
            'a4_cor_duplex' => (int) ($linha['a4_cor_duplex'] ?? 0),
            'a3_pb_simples' => (int) ($linha['a3_pb_simples'] ?? 0),
            'a3_cor_simples' => (int) ($linha['a3_cor_simples'] ?? 0),
            'a3_pb_duplex' => (int) ($linha['a3_pb_duplex'] ?? 0),
            'a3_cor_duplex' => (int) ($linha['a3_cor_duplex'] ?? 0),
            'ultima_atualizacao' => trim((string) ($linha['ultima_atualizacao'] ?? '')),
        ];

        $impressora['a4_total_atual'] = $somarCamposAtuais($impressora, ['a4_pb_simples', 'a4_cor_simples', 'a4_pb_duplex', 'a4_cor_duplex']);
        $impressora['a3_total_atual'] = $somarCamposAtuais($impressora, ['a3_pb_simples', 'a3_cor_simples', 'a3_pb_duplex', 'a3_cor_duplex']);
        $impressora['formato_atual'] = $detectarFormatoAtual($impressora);
        $impressora['valor_atual'] = $obterValorAtualFiltrado($impressora, $filtros['tipo_leitura'], $filtros['tipo_cor']);
        $impressora['resumo_atual'] = $obterResumoAtual($impressora, $filtros['tipo_leitura']);
        $impressora['link_detalhes'] = 'impressora/detalhes.php?id=' . (int) $impressora['id'];
        $impressora['consumo_periodo'] = 0;

        $impressoras[(int) $impressora['id']] = $impressora;
    }

    $consultaImpressoras->free();
}

$historicoPorImpressora = [];
$stmtHistorico = $conn->prepare(
    'SELECT
        impressora_id,
        paginas_total,
        paginas_pb,
        paginas_cor,
        a4_pb_simples,
        a4_cor_simples,
        a4_pb_duplex,
        a4_cor_duplex,
        a3_pb_simples,
        a3_cor_simples,
        a3_pb_duplex,
        a3_cor_duplex,
        data_hora,
        id
     FROM historico_impressoras
     WHERE data_hora >= ? AND data_hora <= ?
     ORDER BY impressora_id ASC, data_hora ASC, id ASC'
);

if ($stmtHistorico) {
    $inicioPeriodoSql = $periodoInicio->format('Y-m-d H:i:s');
    $fimPeriodoSql = $periodoFim->format('Y-m-d H:i:s');
    $stmtHistorico->bind_param('ss', $inicioPeriodoSql, $fimPeriodoSql);

    if ($stmtHistorico->execute()) {
        $resultadoHistorico = $stmtHistorico->get_result();
        if ($resultadoHistorico instanceof mysqli_result) {
            while ($linhaHistorico = $resultadoHistorico->fetch_assoc()) {
                $impressoraId = (int) ($linhaHistorico['impressora_id'] ?? 0);
                if ($impressoraId <= 0 || !isset($impressoras[$impressoraId])) {
                    continue;
                }

                $valorHistorico = $obterValorHistoricoFiltrado($linhaHistorico, $filtros['tipo_leitura'], $filtros['tipo_cor']);
                if ($valorHistorico === null) {
                    continue;
                }

                if (!isset($historicoPorImpressora[$impressoraId])) {
                    $historicoPorImpressora[$impressoraId] = [
                        'primeiro' => $valorHistorico,
                        'ultimo' => $valorHistorico,
                        'quantidade' => 1,
                    ];
                    continue;
                }

                $historicoPorImpressora[$impressoraId]['ultimo'] = $valorHistorico;
                $historicoPorImpressora[$impressoraId]['quantidade']++;
            }

            $resultadoHistorico->free();
        }
    }

    $stmtHistorico->close();
}

foreach ($impressoras as $impressoraId => $impressora) {
    $dadosHistorico = $historicoPorImpressora[$impressoraId] ?? null;
    if ($dadosHistorico && (int) ($dadosHistorico['quantidade'] ?? 0) >= 2) {
        $impressoras[$impressoraId]['consumo_periodo'] = (int) ($dadosHistorico['ultimo'] ?? 0) - (int) ($dadosHistorico['primeiro'] ?? 0);
    }
}

$buscaTexto = strtolower($filtros['busca']);
$linhasTabela = array_values(array_filter(
    $impressoras,
    static function (array $impressora) use ($buscaTexto): bool {
        if ($buscaTexto === '') {
            return true;
        }

        $nome = strtolower(trim((string) ($impressora['nome'] ?? '')));
        $modelo = strtolower(trim((string) ($impressora['modelo'] ?? '')));

        return str_contains($nome, $buscaTexto) || str_contains($modelo, $buscaTexto);
    }
));

usort($linhasTabela, static function (array $a, array $b): int {
    $consumoA = (int) ($a['consumo_periodo'] ?? 0);
    $consumoB = (int) ($b['consumo_periodo'] ?? 0);
    if ($consumoA !== $consumoB) {
        return $consumoB <=> $consumoA;
    }

    $atualA = (int) ($a['valor_atual'] ?? 0);
    $atualB = (int) ($b['valor_atual'] ?? 0);
    if ($atualA !== $atualB) {
        return $atualB <=> $atualA;
    }

    return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
});

$totaisPagina = [
    'consumo_periodo' => 0,
    'valor_atual' => 0,
    'com_consumo' => 0,
];

foreach ($linhasTabela as $linhaTabela) {
    $totaisPagina['consumo_periodo'] += (int) ($linhaTabela['consumo_periodo'] ?? 0);
    $totaisPagina['valor_atual'] += (int) ($linhaTabela['valor_atual'] ?? 0);
    if ((int) ($linhaTabela['consumo_periodo'] ?? 0) > 0) {
        $totaisPagina['com_consumo']++;
    }
}

$conn->close();

$tituloPagina = 'Tabela por Impressora';
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = '';
        $paginaAtual = 'relatorios';
        $paginaTitulo = 'Tabela por impressora';
        $paginaDescricao = 'Analise clicavel por mes, semana, tipo e cor';
        require __DIR__ . '/includes/topo_sistema.php';
    ?>

    <div class="container dashboard-clean relatorio-grade-pagina">
        <section class="relatorios-hero">
            <div class="relatorios-hero__conteudo">
                <span class="relatorios-hero__eyebrow">Tabela dedicada</span>
                <h1>Analise por impressora</h1>
                <p>Esta pagina foi criada so para a grade detalhada. Aqui voce escolhe o mes, refina pela semana, decide o tipo de leitura e foca em PB, cor ou geral sem poluir o relatorio principal.</p>

                <div class="relatorios-hero__chips">
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-calendar"></i>
                        <?= e($periodoLabel) ?>
                    </span>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-layer-group"></i>
                        <?= e($metaTipoLeitura[$filtros['tipo_leitura']]['label']) ?>
                    </span>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-palette"></i>
                        <?= e($metaTipoCor[$filtros['tipo_cor']]) ?>
                    </span>
                </div>
            </div>

            <div class="relatorios-hero__painel">
                <span class="relatorios-hero__rotulo">Navegacao</span>
                <strong><?= e($formatarNumero(count($linhasTabela))) ?> impressoras</strong>
                <small>As linhas da tabela sao clicaveis e abrem os detalhes completos da impressora.</small>

                <div class="relatorios-hero__mini-graficos">
                    <a href="relatorios.php" class="btn-voltar">
                        <i class="fa-solid fa-arrow-left"></i>
                        Voltar para relatorios
                    </a>
                </div>
            </div>
        </section>

        <section class="cards-resumo cards-resumo-clean relatorio-grade-resumo">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <strong><?= e($formatarNumero($totaisPagina['consumo_periodo'])) ?></strong>
                    <span>Consumo no periodo</span>
                    <small>Soma do filtro atual em todas as impressoras listadas.</small>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-gauge-high"></i></div>
                <div>
                    <strong><?= e($formatarNumero($totaisPagina['valor_atual'])) ?></strong>
                    <span>Leitura atual</span>
                    <small>Estado atual do contador escolhido no filtro.</small>
                </div>
            </div>

            <div class="card-resumo card-compra-urgente">
                <div class="icone-resumo"><i class="fa-solid fa-bolt"></i></div>
                <div>
                    <strong><?= e($formatarNumero($totaisPagina['com_consumo'])) ?></strong>
                    <span>Com consumo</span>
                    <small>Impressoras com pelo menos duas coletas validas no periodo.</small>
                </div>
            </div>

            <div class="card-resumo card-vencida">
                <div class="icone-resumo"><i class="fa-solid fa-table-columns"></i></div>
                <div>
                    <strong><?= e($metaTipoCor[$filtros['tipo_cor']]) ?></strong>
                    <span>Visao aplicada</span>
                    <small><?= e($metaTipoLeitura[$filtros['tipo_leitura']]['descricao']) ?></small>
                </div>
            </div>
        </section>

        <form method="GET" class="painel-filtros painel-filtros-relatorio-grade">
            <div class="campo-filtro">
                <label for="mes_referencia">
                    <i class="fa-solid fa-calendar"></i> Mes
                </label>
                <input type="month" id="mes_referencia" name="mes_referencia" value="<?= e($filtros['mes_referencia']) ?>">
            </div>

            <div class="campo-filtro">
                <label for="semana_inicio">
                    <i class="fa-solid fa-calendar-week"></i> Semana do mes
                </label>
                <select id="semana_inicio" name="semana_inicio">
                    <option value="">Mes inteiro</option>
                    <?php foreach ($opcoesSemana as $opcaoSemana): ?>
                        <option value="<?= e($opcaoSemana['valor']) ?>" <?= $filtros['semana_inicio'] === $opcaoSemana['valor'] ? 'selected' : '' ?>>
                            <?= e($opcaoSemana['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo-filtro">
                <label for="tipo_leitura">
                    <i class="fa-solid fa-clone"></i> Tipo
                </label>
                <select id="tipo_leitura" name="tipo_leitura">
                    <option value="total" <?= $filtros['tipo_leitura'] === 'total' ? 'selected' : '' ?>>Total</option>
                    <option value="a4" <?= $filtros['tipo_leitura'] === 'a4' ? 'selected' : '' ?>>A4</option>
                    <option value="a3" <?= $filtros['tipo_leitura'] === 'a3' ? 'selected' : '' ?>>A3</option>
                </select>
            </div>

            <div class="campo-filtro">
                <label for="tipo_cor">
                    <i class="fa-solid fa-circle-half-stroke"></i> Cor
                </label>
                <select id="tipo_cor" name="tipo_cor">
                    <option value="geral" <?= $filtros['tipo_cor'] === 'geral' ? 'selected' : '' ?>>Geral</option>
                    <option value="pb" <?= $filtros['tipo_cor'] === 'pb' ? 'selected' : '' ?>>Preto e branco</option>
                    <option value="cor" <?= $filtros['tipo_cor'] === 'cor' ? 'selected' : '' ?>>Colorida</option>
                </select>
            </div>

            <div class="campo-filtro">
                <label for="busca">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar
                </label>
                <input type="text" id="busca" name="busca" value="<?= e($filtros['busca']) ?>" placeholder="Nome ou modelo">
            </div>

            <div class="acoes-filtros">
                <button type="submit" class="botao botao-filtro">
                    <i class="fa-solid fa-filter"></i>
                    Aplicar
                </button>
                <a href="relatorio_impressoras.php" class="botao botao-filtro">
                    <i class="fa-solid fa-rotate-left"></i>
                    Limpar
                </a>
            </div>
        </form>

        <section class="bloco-detalhes relatorios-secao">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-table"></i>
                </div>
                <div>
                    <h2>Tabela clicavel por impressora</h2>
                    <p>Cada linha abre os detalhes da impressora. O valor exibido muda conforme o filtro de mes, semana, tipo de leitura e cor.</p>
                </div>
            </div>

            <div class="tabela-wrapper tabela-wrapper-relatorios">
                <table class="tabela-relatorios tabela-relatorios--grade">
                    <thead>
                        <tr>
                            <th>Impressora</th>
                            <th>Formato atual</th>
                            <th>Periodo</th>
                            <th>Consumo no periodo</th>
                            <th>Leitura atual</th>
                            <th>Ultima atualizacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($linhasTabela)): ?>
                            <?php foreach ($linhasTabela as $linhaTabela): ?>
                                <tr class="linha-relatorio-clicavel" data-href="<?= e($linhaTabela['link_detalhes']) ?>" tabindex="0" role="link">
                                    <td>
                                        <a href="<?= e($linhaTabela['link_detalhes']) ?>" class="linha-relatorio-link">
                                            <strong><?= e($linhaTabela['nome']) ?></strong>
                                            <span><?= e($linhaTabela['modelo'] !== '' ? $linhaTabela['modelo'] : 'Sem modelo') ?></span>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="impressora-pill <?= e($linhaTabela['formato_atual']['classe']) ?>">
                                            <?= e($linhaTabela['formato_atual']['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="tabela-resumo-periodo">
                                            <strong><?= e($periodoLabel) ?></strong>
                                            <span><?= e($metaTipoLeitura[$filtros['tipo_leitura']]['label']) ?> | <?= e($metaTipoCor[$filtros['tipo_cor']]) ?></span>
                                        </div>
                                    </td>
                                    <td><strong class="destaque-mes"><?= e($formatarNumero($linhaTabela['consumo_periodo'])) ?></strong></td>
                                    <td>
                                        <div class="tabela-resumo-periodo">
                                            <strong><?= e($formatarNumero($linhaTabela['valor_atual'])) ?></strong>
                                            <span><?= e($linhaTabela['resumo_atual']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= e($formatarDataHora($linhaTabela['ultima_atualizacao'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="vazio">Nenhuma impressora encontrada com os filtros atuais.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const campoMes = document.getElementById('mes_referencia');
            if (campoMes) {
                campoMes.addEventListener('change', function () {
                    const campoSemana = document.getElementById('semana_inicio');
                    if (campoSemana) {
                        campoSemana.value = '';
                    }
                    this.form.submit();
                });
            }

            document.querySelectorAll('.linha-relatorio-clicavel').forEach((linha) => {
                const href = linha.getAttribute('data-href');
                if (!href) {
                    return;
                }

                linha.addEventListener('click', (evento) => {
                    if (evento.target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }

                    window.location.href = href;
                });

                linha.addEventListener('keydown', (evento) => {
                    if (evento.key === 'Enter' || evento.key === ' ') {
                        evento.preventDefault();
                        window.location.href = href;
                    }
                });
            });
        }());
    </script>
</body>
</html>
