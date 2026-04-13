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
$filtrosRelatorio = [
    'data_dia' => trim((string) ($_GET['data_dia'] ?? '')),
    'data_semana' => trim((string) ($_GET['data_semana'] ?? '')),
    'data_mes' => trim((string) ($_GET['data_mes'] ?? '')),
];

$consumoZero = static function (): array {
    return [
        'paginas_total' => 0,
        'paginas_pb' => 0,
        'paginas_cor' => 0,
        'a4_total' => 0,
        'a3_total' => 0,
    ];
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

$calcularConsumoHistoricoPorPeriodo = static function (mysqli $conn, string $inicio, string $fim) use ($consumoZero, $somarCamposHistorico): array {
    $sql = 'SELECT
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
        $a4Total = $somarCamposHistorico($linha, ['a4_pb_simples', 'a4_cor_simples', 'a4_pb_duplex', 'a4_cor_duplex']);
        $a3Total = $somarCamposHistorico($linha, ['a3_pb_simples', 'a3_cor_simples', 'a3_pb_duplex', 'a3_cor_duplex']);

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
                'primeiro_a4' => null,
                'ultimo_a4' => null,
                'qtd_a4' => 0,
                'primeiro_a3' => null,
                'ultimo_a3' => null,
                'qtd_a3' => 0,
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

        if ($a4Total !== null) {
            if ($primeiroUltimo[$impressoraId]['qtd_a4'] === 0) {
                $primeiroUltimo[$impressoraId]['primeiro_a4'] = $a4Total;
            }
            $primeiroUltimo[$impressoraId]['ultimo_a4'] = $a4Total;
            $primeiroUltimo[$impressoraId]['qtd_a4']++;
        }

        if ($a3Total !== null) {
            if ($primeiroUltimo[$impressoraId]['qtd_a3'] === 0) {
                $primeiroUltimo[$impressoraId]['primeiro_a3'] = $a3Total;
            }
            $primeiroUltimo[$impressoraId]['ultimo_a3'] = $a3Total;
            $primeiroUltimo[$impressoraId]['qtd_a3']++;
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

        if ($dados['qtd_a4'] >= 2 && $dados['primeiro_a4'] !== null && $dados['ultimo_a4'] !== null) {
            $consumo['a4_total'] = (int) $dados['ultimo_a4'] - (int) $dados['primeiro_a4'];
        }

        if ($dados['qtd_a3'] >= 2 && $dados['primeiro_a3'] !== null && $dados['ultimo_a3'] !== null) {
            $consumo['a3_total'] = (int) $dados['ultimo_a3'] - (int) $dados['primeiro_a3'];
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

$formatarNumero = static function ($valor): string {
    $numero = is_numeric($valor) ? (int) $valor : 0;
    return number_format($numero, 0, ',', '.');
};

$formatarIntervaloPeriodo = static function (string $inicio, string $fim): string {
    $dataInicio = date_create($inicio);
    $dataFim = date_create($fim);

    if (!$dataInicio || !$dataFim) {
        return '';
    }

    if ($dataInicio->format('Y-m-d') === $dataFim->format('Y-m-d')) {
        return $dataInicio->format('d/m/Y');
    }

    if ($dataInicio->format('Y-m') === $dataFim->format('Y-m')) {
        return $dataInicio->format('d/m') . ' a ' . $dataFim->format('d/m/Y');
    }

    return $dataInicio->format('d/m/Y') . ' a ' . $dataFim->format('d/m/Y');
};

$calcularPercentual = static function (int $parte, int $total): int {
    if ($parte <= 0 || $total <= 0) {
        return 0;
    }

    return (int) round(($parte / $total) * 100);
};

$calcularLarguraPercentual = static function (int $parte, int $total): float {
    if ($parte <= 0 || $total <= 0) {
        return 0.0;
    }

    return round(($parte / $total) * 100, 2);
};

$agora = new DateTimeImmutable('now');
$indiceDiaSemanaAtual = (int) $agora->format('N');
$inicioDiaPadrao = $agora->setTime(0, 0, 0);
$inicioSemanaPadrao = $agora->setTime(0, 0, 0)->modify('-' . ($indiceDiaSemanaAtual - 1) . ' days');
$inicioMesPadrao = $agora->modify('first day of this month')->setTime(0, 0, 0);

$filtrosRelatorio['data_dia'] = $filtrosRelatorio['data_dia'] !== '' ? $filtrosRelatorio['data_dia'] : $inicioDiaPadrao->format('Y-m-d');
$filtrosRelatorio['data_semana'] = $filtrosRelatorio['data_semana'] !== '' ? $filtrosRelatorio['data_semana'] : $agora->format('o-\WW');
$filtrosRelatorio['data_mes'] = $filtrosRelatorio['data_mes'] !== '' ? $filtrosRelatorio['data_mes'] : $agora->format('Y-m');

$criarReferenciaDia = static function (string $valor, DateTimeImmutable $padrao): DateTimeImmutable {
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $data instanceof DateTimeImmutable ? $data->setTime(0, 0, 0) : $padrao;
};

$criarReferenciaSemana = static function (string $valor, DateTimeImmutable $padrao): DateTimeImmutable {
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $valor, $partes)) {
        return $padrao;
    }

    $ano = (int) $partes[1];
    $semana = (int) $partes[2];
    if ($semana < 1 || $semana > 53) {
        return $padrao;
    }

    return (new DateTimeImmutable('now'))->setISODate($ano, $semana)->setTime(0, 0, 0);
};

$criarReferenciaMes = static function (string $valor, DateTimeImmutable $padrao): DateTimeImmutable {
    $data = DateTimeImmutable::createFromFormat('!Y-m', $valor);
    return $data instanceof DateTimeImmutable ? $data->setTime(0, 0, 0) : $padrao;
};

$detectarFormatoAtual = static function (array $impressora): array {
    $temA4 = (int) ($impressora['a4_total_atual'] ?? 0) > 0;
    $temA3 = (int) ($impressora['a3_total_atual'] ?? 0) > 0;

    if ($temA4 && $temA3) {
        return ['label' => 'A3 + A4', 'classe' => 'impressora-pill--misto', 'chave' => 'misto'];
    }

    if ($temA3) {
        return ['label' => 'A3', 'classe' => 'impressora-pill--a3', 'chave' => 'a3'];
    }

    if ($temA4) {
        return ['label' => 'A4', 'classe' => 'impressora-pill--a4', 'chave' => 'a4'];
    }

    return ['label' => 'Sem leitura', 'classe' => 'impressora-pill--sync', 'chave' => 'sem_leitura'];
};

$consulta = $conn->query(
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

if ($consulta instanceof mysqli_result) {
    while ($linha = $consulta->fetch_assoc()) {
        $impressoraId = (int) ($linha['id'] ?? 0);
        $paginasTotal = (int) ($linha['paginas_total'] ?? 0);
        $paginasPb = (int) ($linha['paginas_pb'] ?? 0);
        $paginasCor = (int) ($linha['paginas_cor'] ?? 0);
        $ultimaAtualizacao = trim((string) ($linha['ultima_atualizacao'] ?? ''));
        $a4PbSimples = (int) ($linha['a4_pb_simples'] ?? 0);
        $a4CorSimples = (int) ($linha['a4_cor_simples'] ?? 0);
        $a4PbDuplex = (int) ($linha['a4_pb_duplex'] ?? 0);
        $a4CorDuplex = (int) ($linha['a4_cor_duplex'] ?? 0);
        $a3PbSimples = (int) ($linha['a3_pb_simples'] ?? 0);
        $a3CorSimples = (int) ($linha['a3_cor_simples'] ?? 0);
        $a3PbDuplex = (int) ($linha['a3_pb_duplex'] ?? 0);
        $a3CorDuplex = (int) ($linha['a3_cor_duplex'] ?? 0);

        $impressoras[] = [
            'id' => $impressoraId,
            'nome' => trim((string) ($linha['nome'] ?? '')),
            'modelo' => trim((string) ($linha['modelo'] ?? '')),
            'paginas_total' => $paginasTotal,
            'paginas_pb' => $paginasPb,
            'paginas_cor' => $paginasCor,
            'a4_pb_simples' => $a4PbSimples,
            'a4_cor_simples' => $a4CorSimples,
            'a4_pb_duplex' => $a4PbDuplex,
            'a4_cor_duplex' => $a4CorDuplex,
            'a3_pb_simples' => $a3PbSimples,
            'a3_cor_simples' => $a3CorSimples,
            'a3_pb_duplex' => $a3PbDuplex,
            'a3_cor_duplex' => $a3CorDuplex,
            'a4_total_atual' => $somarCamposAtuais($linha, ['a4_pb_simples', 'a4_cor_simples', 'a4_pb_duplex', 'a4_cor_duplex']),
            'a3_total_atual' => $somarCamposAtuais($linha, ['a3_pb_simples', 'a3_cor_simples', 'a3_pb_duplex', 'a3_cor_duplex']),
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

$referenciaDia = $criarReferenciaDia($filtrosRelatorio['data_dia'], $inicioDiaPadrao);
$referenciaSemana = $criarReferenciaSemana($filtrosRelatorio['data_semana'], $inicioSemanaPadrao);
$referenciaMes = $criarReferenciaMes($filtrosRelatorio['data_mes'], $inicioMesPadrao);

$inicioDia = $referenciaDia->setTime(0, 0, 0);
$fimDia = $referenciaDia->setTime(23, 59, 59);

$inicioSemana = $referenciaSemana->setTime(0, 0, 0);
$fimSemana = $referenciaSemana->modify('+6 days')->setTime(23, 59, 59);

$inicioMes = $referenciaMes->modify('first day of this month')->setTime(0, 0, 0);
$fimMes = $referenciaMes->modify('last day of this month')->setTime(23, 59, 59);

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
    $impressora['formato_atual'] = $detectarFormatoAtual($impressora);

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
        $totaisConsumoPeriodo[$periodo]['a4_total'] += (int) ($linhaPeriodo['a4_total'] ?? 0);
        $totaisConsumoPeriodo[$periodo]['a3_total'] += (int) ($linhaPeriodo['a3_total'] ?? 0);
    }
}

$contagemAtivaPeriodo = [
    'dia' => 0,
    'semana' => 0,
    'mes' => 0,
];

foreach ($impressoras as $impressora) {
    foreach (['dia', 'semana', 'mes'] as $periodo) {
        $consumoAtual = (int) ($impressora['consumo_periodo'][$periodo]['paginas_total'] ?? 0);
        if ($consumoAtual > 0) {
            $contagemAtivaPeriodo[$periodo]++;
        }
    }
}

$maiorTotalComparativo = max(
    1,
    (int) ($totaisConsumoPeriodo['dia']['paginas_total'] ?? 0),
    (int) ($totaisConsumoPeriodo['semana']['paginas_total'] ?? 0),
    (int) ($totaisConsumoPeriodo['mes']['paginas_total'] ?? 0)
);

$metaPeriodos = [
    'dia' => [
        'titulo' => 'Dia',
        'descricao' => 'Consumo do dia selecionado',
        'icone' => 'fa-solid fa-sun',
        'classe' => 'periodo-card--dia',
    ],
    'semana' => [
        'titulo' => 'Semana',
        'descricao' => 'Acumulado da semana selecionada',
        'icone' => 'fa-solid fa-calendar-week',
        'classe' => 'periodo-card--semana',
    ],
    'mes' => [
        'titulo' => 'Mes',
        'descricao' => 'Acumulado do mes selecionado',
        'icone' => 'fa-solid fa-calendar',
        'classe' => 'periodo-card--mes',
    ],
];

$periodosVisuais = [];
foreach ($metaPeriodos as $chave => $meta) {
    $totalPeriodo = (int) ($totaisConsumoPeriodo[$chave]['paginas_total'] ?? 0);
    $pbPeriodo = (int) ($totaisConsumoPeriodo[$chave]['paginas_pb'] ?? 0);
    $corPeriodo = (int) ($totaisConsumoPeriodo[$chave]['paginas_cor'] ?? 0);
    $a4Periodo = (int) ($totaisConsumoPeriodo[$chave]['a4_total'] ?? 0);
    $a3Periodo = (int) ($totaisConsumoPeriodo[$chave]['a3_total'] ?? 0);
    $baseTamanhoPeriodo = $a4Periodo + $a3Periodo;
    $larguraTotal = 0.0;

    if ($totalPeriodo > 0 && $maiorTotalComparativo > 0) {
        $larguraTotal = max(8.0, round(($totalPeriodo / $maiorTotalComparativo) * 100, 2));
    }

    $periodosVisuais[$chave] = [
        'titulo' => $meta['titulo'],
        'descricao' => $meta['descricao'],
        'icone' => $meta['icone'],
        'classe' => $meta['classe'],
        'intervalo' => $formatarIntervaloPeriodo(
            (string) ($periodosRelatorio[$chave]['inicio'] ?? ''),
            (string) ($periodosRelatorio[$chave]['fim'] ?? '')
        ),
        'total' => $totalPeriodo,
        'pb' => $pbPeriodo,
        'cor' => $corPeriodo,
        'a4' => $a4Periodo,
        'a3' => $a3Periodo,
        'largura_total' => $larguraTotal,
        'largura_pb' => $calcularLarguraPercentual($pbPeriodo, $totalPeriodo),
        'largura_cor' => $calcularLarguraPercentual($corPeriodo, $totalPeriodo),
        'largura_a4' => $calcularLarguraPercentual($a4Periodo, $baseTamanhoPeriodo),
        'largura_a3' => $calcularLarguraPercentual($a3Periodo, $baseTamanhoPeriodo),
        'percentual_pb' => $calcularPercentual($pbPeriodo, $totalPeriodo),
        'percentual_cor' => $calcularPercentual($corPeriodo, $totalPeriodo),
        'percentual_a4' => $calcularPercentual($a4Periodo, $baseTamanhoPeriodo),
        'percentual_a3' => $calcularPercentual($a3Periodo, $baseTamanhoPeriodo),
        'impressoras_ativas' => (int) ($contagemAtivaPeriodo[$chave] ?? 0),
    ];
}

$periodoDestaque = $periodosVisuais['mes'];
foreach ($periodosVisuais as $periodoVisual) {
    if ((int) $periodoVisual['total'] > (int) $periodoDestaque['total']) {
        $periodoDestaque = $periodoVisual;
    }
}

$impressorasOrdenadas = $impressoras;
usort($impressorasOrdenadas, static function (array $a, array $b): int {
    $mesA = (int) ($a['consumo_periodo']['mes']['paginas_total'] ?? 0);
    $mesB = (int) ($b['consumo_periodo']['mes']['paginas_total'] ?? 0);
    if ($mesA !== $mesB) {
        return $mesB <=> $mesA;
    }

    $semanaA = (int) ($a['consumo_periodo']['semana']['paginas_total'] ?? 0);
    $semanaB = (int) ($b['consumo_periodo']['semana']['paginas_total'] ?? 0);
    if ($semanaA !== $semanaB) {
        return $semanaB <=> $semanaA;
    }

    $diaA = (int) ($a['consumo_periodo']['dia']['paginas_total'] ?? 0);
    $diaB = (int) ($b['consumo_periodo']['dia']['paginas_total'] ?? 0);
    if ($diaA !== $diaB) {
        return $diaB <=> $diaA;
    }

    return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
});

$rankingMes = array_values(array_filter(
    $impressorasOrdenadas,
    static fn(array $impressora): bool => (int) ($impressora['consumo_periodo']['mes']['paginas_total'] ?? 0) > 0
));
$rankingMes = array_slice($rankingMes, 0, 5);

$maiorConsumoRankingMes = 1;
foreach ($rankingMes as $itemRanking) {
    $consumoMesRanking = (int) ($itemRanking['consumo_periodo']['mes']['paginas_total'] ?? 0);
    if ($consumoMesRanking > $maiorConsumoRankingMes) {
        $maiorConsumoRankingMes = $consumoMesRanking;
    }
}

$linkTabelaDetalhada = 'relatorio_impressoras.php?mes_referencia=' . rawurlencode($referenciaMes->format('Y-m'));

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
        $paginaDescricao = "Leitura visual de paginas, A3, A4 e historico por periodo";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean relatorios-pagina">
        <section class="relatorios-hero">
            <div class="relatorios-hero__conteudo">
                <span class="relatorios-hero__eyebrow">Leitura rapida</span>
                <h1>Relatorios de impressoras</h1>
                <p>Visual mais claro para entender o parque de impressao, o ritmo de consumo e quais impressoras puxaram mais paginas no periodo.</p>

                <div class="relatorios-hero__chips">
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-print"></i>
                        <?= e($formatarNumero(count($impressoras))) ?> impressoras monitoradas
                    </span>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-bolt"></i>
                        <?= e($formatarNumero($contagemAtivaPeriodo['mes'])) ?> com consumo no mes selecionado
                    </span>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-clock"></i>
                        Atualizado em <?= e($formatarDataHora($ultimaAtualizacaoRecente)) ?>
                    </span>
                </div>
            </div>

            <div class="relatorios-hero__painel">
                <span class="relatorios-hero__rotulo">Maior volume no periodo</span>
                <strong><?= e($periodoDestaque['titulo']) ?></strong>
                <small><?= e($formatarNumero($periodoDestaque['total'])) ?> paginas registradas</small>

                <div class="relatorios-hero__mini-graficos">
                    <?php foreach ($periodosVisuais as $periodoVisual): ?>
                        <div class="mini-comparativo">
                            <div class="mini-comparativo__topo">
                                <span><?= e($periodoVisual['titulo']) ?></span>
                                <strong><?= e($formatarNumero($periodoVisual['total'])) ?></strong>
                            </div>
                            <div class="grafico-pilha">
                                <div class="grafico-pilha__preenchimento" style="width: <?= e((string) $periodoVisual['largura_total']) ?>%">
                                    <span class="grafico-pilha__pb" style="width: <?= e((string) $periodoVisual['largura_pb']) ?>%"></span>
                                    <span class="grafico-pilha__cor" style="width: <?= e((string) $periodoVisual['largura_cor']) ?>%"></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/includes/mensagem_flash.php'; ?>

        <section class="cards-resumo cards-resumo-clean relatorios-resumo-atual">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <strong><?= e($formatarNumero($totais['paginas_total'])) ?></strong>
                    <span>Total geral de paginas</span>
                    <small>Soma do contador atual de todas as impressoras</small>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-print"></i></div>
                <div>
                    <strong><?= e($formatarNumero($totais['paginas_pb'])) ?></strong>
                    <span>Total geral PB</span>
                    <small>Paginas preto e branco no estado atual</small>
                </div>
            </div>

            <div class="card-resumo card-compra-urgente">
                <div class="icone-resumo"><i class="fa-solid fa-palette"></i></div>
                <div>
                    <strong><?= e($formatarNumero($totais['paginas_cor'])) ?></strong>
                    <span>Total geral Cor</span>
                    <small>Paginas coloridas no estado atual</small>
                </div>
            </div>

            <div class="card-resumo card-vencida">
                <div class="icone-resumo"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <strong><?= e($formatarDataHora($ultimaAtualizacaoRecente)) ?></strong>
                    <span>Ultima atualizacao</span>
                    <small>Horario da coleta mais recente</small>
                </div>
            </div>
        </section>

        <section class="resumo-mastigado resumo-mastigado-compacto">
            <div class="resumo-mastigado__icone">
                <i class="fa-solid fa-circle-info"></i>
            </div>
            <div>
                <h2>Como ler este relatorio</h2>
                <p>Consumo do periodo = ultimo contador do periodo menos o primeiro. Agora voce pode escolher dia, semana e mes passados. Quando nao existem duas coletas validas, o sistema mostra 0 para evitar leitura incorreta.</p>
            </div>
        </section>

        <form method="GET" class="painel-filtros painel-filtros-relatorios">
            <div class="campo-filtro">
                <label for="data_dia">
                    <i class="fa-solid fa-sun"></i> Dia
                </label>
                <input type="date" id="data_dia" name="data_dia" value="<?= e($filtrosRelatorio['data_dia']) ?>">
            </div>

            <div class="campo-filtro">
                <label for="data_semana">
                    <i class="fa-solid fa-calendar-week"></i> Semana
                </label>
                <input type="week" id="data_semana" name="data_semana" value="<?= e($filtrosRelatorio['data_semana']) ?>">
            </div>

            <div class="campo-filtro">
                <label for="data_mes">
                    <i class="fa-solid fa-calendar"></i> Mes
                </label>
                <input type="month" id="data_mes" name="data_mes" value="<?= e($filtrosRelatorio['data_mes']) ?>">
            </div>

            <div class="acoes-filtros">
                <button type="submit" class="botao botao-filtro">
                    <i class="fa-solid fa-filter"></i> Aplicar
                </button>
                <a href="relatorios.php" class="botao botao-filtro">
                    <i class="fa-solid fa-rotate-left"></i> Limpar
                </a>
            </div>
        </form>

        <section class="relatorios-periodos">
            <?php foreach ($periodosVisuais as $periodoVisual): ?>
                <article class="periodo-card <?= e($periodoVisual['classe']) ?>">
                    <div class="periodo-card__topo">
                        <div class="periodo-card__icone">
                            <i class="<?= e($periodoVisual['icone']) ?>"></i>
                        </div>
                        <div>
                            <h2><?= e($periodoVisual['titulo']) ?></h2>
                            <p><?= e($periodoVisual['intervalo']) ?></p>
                        </div>
                    </div>

                    <div class="periodo-card__numero"><?= e($formatarNumero($periodoVisual['total'])) ?></div>
                    <span class="periodo-card__texto">paginas no periodo</span>

                    <div class="grafico-pilha grafico-pilha--grande">
                        <div class="grafico-pilha__preenchimento" style="width: <?= e((string) $periodoVisual['largura_total']) ?>%">
                            <span class="grafico-pilha__pb" style="width: <?= e((string) $periodoVisual['largura_pb']) ?>%"></span>
                            <span class="grafico-pilha__cor" style="width: <?= e((string) $periodoVisual['largura_cor']) ?>%"></span>
                        </div>
                    </div>

                    <div class="periodo-card__metricas">
                        <div>
                            <span>PB</span>
                            <strong><?= e($formatarNumero($periodoVisual['pb'])) ?></strong>
                        </div>
                        <div>
                            <span>Cor</span>
                            <strong><?= e($formatarNumero($periodoVisual['cor'])) ?></strong>
                        </div>
                        <div>
                            <span>A4</span>
                            <strong><?= e($formatarNumero($periodoVisual['a4'])) ?></strong>
                        </div>
                        <div>
                            <span>A3</span>
                            <strong><?= e($formatarNumero($periodoVisual['a3'])) ?></strong>
                        </div>
                        <div>
                            <span>Impressoras ativas</span>
                            <strong><?= e($formatarNumero($periodoVisual['impressoras_ativas'])) ?></strong>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="graficos-dashboard relatorios-graficos">
            <article class="grafico-card">
                <div class="secao-titulo-clean">
                    <h2>Comparativo entre periodos</h2>
                    <p>Agora o comparativo separa volume geral, distribuicao PB/Cor e distribuicao A4/A3 para cada periodo.</p>
                </div>

                <div class="comparativo-periodos">
                    <?php foreach ($periodosVisuais as $periodoVisual): ?>
                        <div class="comparativo-periodos__linha">
                            <div class="comparativo-periodos__cabecalho">
                                <div>
                                    <strong><?= e($periodoVisual['titulo']) ?></strong>
                                    <span><?= e($periodoVisual['intervalo']) ?></span>
                                </div>
                                <div class="comparativo-periodos__valor-bloco">
                                    <span>Total do periodo</span>
                                    <strong class="comparativo-periodos__valor"><?= e($formatarNumero($periodoVisual['total'])) ?></strong>
                                </div>
                            </div>

                            <div class="comparativo-periodos__escala">
                                <div class="comparativo-periodos__escala-topo">
                                    <span>Volume relativo entre os periodos</span>
                                    <strong><?= e((string) round((float) $periodoVisual['largura_total'])) ?>%</strong>
                                </div>

                                <div class="grafico-pilha grafico-pilha--medio">
                                    <div class="grafico-pilha__preenchimento" style="width: <?= e((string) $periodoVisual['largura_total']) ?>%">
                                        <span class="grafico-pilha__pb" style="width: <?= e((string) $periodoVisual['largura_pb']) ?>%"></span>
                                        <span class="grafico-pilha__cor" style="width: <?= e((string) $periodoVisual['largura_cor']) ?>%"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="comparativo-periodos__divisao">
                                <div class="comparativo-subgrafico">
                                    <div class="comparativo-subgrafico__topo">
                                        <span>PB x Cor</span>
                                        <strong><?= e((string) $periodoVisual['percentual_pb']) ?>% / <?= e((string) $periodoVisual['percentual_cor']) ?>%</strong>
                                    </div>

                                    <div class="grafico-pilha grafico-pilha--compacta">
                                        <div class="grafico-pilha__preenchimento" style="width: 100%">
                                            <span class="grafico-pilha__pb" style="width: <?= e((string) $periodoVisual['largura_pb']) ?>%"></span>
                                            <span class="grafico-pilha__cor" style="width: <?= e((string) $periodoVisual['largura_cor']) ?>%"></span>
                                        </div>
                                    </div>

                                    <div class="grafico-legenda grafico-legenda--compacta">
                                        <span><i class="fa-solid fa-square grafico-legenda__pb"></i> PB: <?= e($formatarNumero($periodoVisual['pb'])) ?></span>
                                        <span><i class="fa-solid fa-square grafico-legenda__cor"></i> Cor: <?= e($formatarNumero($periodoVisual['cor'])) ?></span>
                                    </div>
                                </div>

                                <div class="comparativo-subgrafico">
                                    <div class="comparativo-subgrafico__topo">
                                        <span>A4 x A3</span>
                                        <strong><?= e((string) $periodoVisual['percentual_a4']) ?>% / <?= e((string) $periodoVisual['percentual_a3']) ?>%</strong>
                                    </div>

                                    <div class="grafico-pilha grafico-pilha--compacta">
                                        <div class="grafico-pilha__preenchimento" style="width: 100%">
                                            <span class="grafico-pilha__a4" style="width: <?= e((string) $periodoVisual['largura_a4']) ?>%"></span>
                                            <span class="grafico-pilha__a3" style="width: <?= e((string) $periodoVisual['largura_a3']) ?>%"></span>
                                        </div>
                                    </div>

                                    <div class="grafico-legenda grafico-legenda--compacta">
                                        <span><i class="fa-solid fa-square grafico-legenda__a4"></i> A4: <?= e($formatarNumero($periodoVisual['a4'])) ?></span>
                                        <span><i class="fa-solid fa-square grafico-legenda__a3"></i> A3: <?= e($formatarNumero($periodoVisual['a3'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="grafico-card">
                <div class="secao-titulo-clean">
                    <h2>Maiores consumos do mes selecionado</h2>
                    <p>Ranking com total do mes e composicao do consumo em PB/Cor e A4/A3 para <?= e($periodosVisuais['mes']['intervalo']) ?>.</p>
                </div>

                <?php if (!empty($rankingMes)): ?>
                    <div class="ranking-lista">
                        <?php foreach ($rankingMes as $indiceRanking => $itemRanking): ?>
                            <?php
                                $consumoMesRanking = (int) ($itemRanking['consumo_periodo']['mes']['paginas_total'] ?? 0);
                                $consumoSemanaRanking = (int) ($itemRanking['consumo_periodo']['semana']['paginas_total'] ?? 0);
                                $consumoDiaRanking = (int) ($itemRanking['consumo_periodo']['dia']['paginas_total'] ?? 0);
                                $consumoPbRanking = (int) ($itemRanking['consumo_periodo']['mes']['paginas_pb'] ?? 0);
                                $consumoCorRanking = (int) ($itemRanking['consumo_periodo']['mes']['paginas_cor'] ?? 0);
                                $consumoA4Ranking = (int) ($itemRanking['consumo_periodo']['mes']['a4_total'] ?? 0);
                                $consumoA3Ranking = (int) ($itemRanking['consumo_periodo']['mes']['a3_total'] ?? 0);
                                $baseFormatoRanking = $consumoA4Ranking + $consumoA3Ranking;
                                $larguraRanking = $consumoMesRanking > 0 ? max(10.0, round(($consumoMesRanking / $maiorConsumoRankingMes) * 100, 2)) : 0.0;
                                $larguraPbRanking = $calcularLarguraPercentual($consumoPbRanking, $consumoMesRanking);
                                $larguraCorRanking = $calcularLarguraPercentual($consumoCorRanking, $consumoMesRanking);
                                $larguraA4Ranking = $calcularLarguraPercentual($consumoA4Ranking, $baseFormatoRanking);
                                $larguraA3Ranking = $calcularLarguraPercentual($consumoA3Ranking, $baseFormatoRanking);
                                $percentualPbRanking = $calcularPercentual($consumoPbRanking, $consumoMesRanking);
                                $percentualCorRanking = $calcularPercentual($consumoCorRanking, $consumoMesRanking);
                                $percentualA4Ranking = $calcularPercentual($consumoA4Ranking, $baseFormatoRanking);
                                $percentualA3Ranking = $calcularPercentual($consumoA3Ranking, $baseFormatoRanking);
                            ?>
                            <div class="ranking-item">
                                <div class="ranking-item__topo">
                                    <div class="ranking-item__titulo">
                                        <span class="ranking-item__posicao">#<?= e((string) ($indiceRanking + 1)) ?></span>
                                        <div>
                                            <strong><?= e($itemRanking['nome']) ?></strong>
                                            <span><?= e($itemRanking['modelo'] !== '' ? $itemRanking['modelo'] : 'Sem modelo informado') ?></span>
                                        </div>
                                    </div>
                                    <div class="ranking-item__valor-bloco">
                                        <span class="ranking-item__valor-label">Mes</span>
                                        <span class="ranking-item__valor"><?= e($formatarNumero($consumoMesRanking)) ?></span>
                                    </div>
                                </div>

                                <div class="ranking-item__barra">
                                    <span style="width: <?= e((string) $larguraRanking) ?>%"></span>
                                </div>

                                <div class="ranking-item__metricas">
                                    <div class="ranking-item__metrica">
                                        <span>Semana</span>
                                        <strong><?= e($formatarNumero($consumoSemanaRanking)) ?></strong>
                                    </div>
                                    <div class="ranking-item__metrica">
                                        <span>Dia</span>
                                        <strong><?= e($formatarNumero($consumoDiaRanking)) ?></strong>
                                    </div>
                                    <div class="ranking-item__metrica">
                                        <span>PB</span>
                                        <strong><?= e($formatarNumero($consumoPbRanking)) ?></strong>
                                    </div>
                                    <div class="ranking-item__metrica">
                                        <span>Cor</span>
                                        <strong><?= e($formatarNumero($consumoCorRanking)) ?></strong>
                                    </div>
                                </div>

                                <div class="ranking-item__subgraficos">
                                    <div class="ranking-item__subgrafico">
                                        <div class="ranking-item__subtopo">
                                            <span>PB x Cor no mes</span>
                                            <strong><?= e((string) $percentualPbRanking) ?>% / <?= e((string) $percentualCorRanking) ?>%</strong>
                                        </div>

                                        <div class="grafico-pilha grafico-pilha--compacta">
                                            <div class="grafico-pilha__preenchimento" style="width: 100%">
                                                <span class="grafico-pilha__pb" style="width: <?= e((string) $larguraPbRanking) ?>%"></span>
                                                <span class="grafico-pilha__cor" style="width: <?= e((string) $larguraCorRanking) ?>%"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ranking-item__subgrafico">
                                        <div class="ranking-item__subtopo">
                                            <span>A4 x A3 no mes</span>
                                            <strong><?= e((string) $percentualA4Ranking) ?>% / <?= e((string) $percentualA3Ranking) ?>%</strong>
                                        </div>

                                        <div class="grafico-pilha grafico-pilha--compacta">
                                            <div class="grafico-pilha__preenchimento" style="width: 100%">
                                                <span class="grafico-pilha__a4" style="width: <?= e((string) $larguraA4Ranking) ?>%"></span>
                                                <span class="grafico-pilha__a3" style="width: <?= e((string) $larguraA3Ranking) ?>%"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="estado-vazio relatorios-vazio">
                        <i class="fa-solid fa-chart-simple"></i>
                        <h2>Sem consumo no mes</h2>
                        <p>Ainda nao existem coletas suficientes para destacar impressoras no mes selecionado.</p>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="bloco-detalhes relatorios-secao">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </div>
                <div>
                    <h2>Tabela detalhada em pagina propria</h2>
                    <p>A grade clicavel saiu desta tela. Agora ela fica em uma pagina separada, com filtros proprios para mes, semana do mes, total/A4/A3 e PB ou colorido.</p>
                </div>
            </div>

            <div class="atalho-grade-detalhada">
                <div class="atalho-grade-detalhada__texto">
                    <span class="atalho-grade-detalhada__eyebrow">Nova pagina</span>
                    <strong>Abrir grade clicavel por impressora</strong>
                    <p>Leve para uma area dedicada a tabela detalhada, com leitura filtrada por mes, semana do mes, tipo de pagina e cor.</p>
                </div>

                <div class="atalho-grade-detalhada__acoes">
                    <a href="<?= e($linkTabelaDetalhada) ?>" class="botao botao-filtro">
                        <i class="fa-solid fa-table-list"></i>
                        Abrir tabela detalhada
                    </a>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-calendar-days"></i>
                        Mes inicial: <?= e($referenciaMes->format('m/Y')) ?>
                    </span>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
