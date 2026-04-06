<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para login.');
    $conn->close();
    header('Location: login.php');
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$janelaSegundos = 300;
$maxTentativas = 5;
$agora = time();
$ipCliente = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');
$emailNormalizado = strtolower($email !== '' ? $email : 'sem_email');

$diretorioRateLimit = __DIR__ . '/../var/login_rate_limit';
if (!is_dir($diretorioRateLimit) && !mkdir($diretorioRateLimit, 0775, true) && !is_dir($diretorioRateLimit)) {
    error_log('Nao foi possivel criar diretorio de rate limit de login: ' . $diretorioRateLimit);
}

$montarArquivoRateLimit = static function (string $chave) use ($diretorioRateLimit): string {
    return rtrim($diretorioRateLimit, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $chave) . '.json';
};

$carregarEstadoRateLimit = static function (string $arquivo, int $agoraAtual): array {
    $estadoPadrao = [
        'inicio_janela' => $agoraAtual,
        'falhas' => 0,
        'bloqueado_ate' => 0,
    ];

    if (!is_file($arquivo)) {
        return $estadoPadrao;
    }

    $conteudo = @file_get_contents($arquivo);
    if ($conteudo === false || trim($conteudo) === '') {
        return $estadoPadrao;
    }

    $estado = json_decode($conteudo, true);
    if (!is_array($estado)) {
        return $estadoPadrao;
    }

    return [
        'inicio_janela' => (int) ($estado['inicio_janela'] ?? $agoraAtual),
        'falhas' => (int) ($estado['falhas'] ?? 0),
        'bloqueado_ate' => (int) ($estado['bloqueado_ate'] ?? 0),
    ];
};

$salvarEstadoRateLimit = static function (string $arquivo, array $estado): void {
    $payload = json_encode([
        'inicio_janela' => (int) ($estado['inicio_janela'] ?? 0),
        'falhas' => (int) ($estado['falhas'] ?? 0),
        'bloqueado_ate' => (int) ($estado['bloqueado_ate'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);

    if (!is_string($payload)) {
        return;
    }

    @file_put_contents($arquivo, $payload, LOCK_EX);
};

$limparEstadoRateLimit = static function (string $arquivo): void {
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
};

$normalizarEstadoRateLimit = static function (array $estado, int $agoraAtual, int $janela): array {
    if ((int) ($estado['inicio_janela'] ?? 0) + $janela <= $agoraAtual) {
        return [
            'inicio_janela' => $agoraAtual,
            'falhas' => 0,
            'bloqueado_ate' => 0,
        ];
    }

    return $estado;
};

$registrarFalha = static function (array $estado, int $agoraAtual, int $janela, int $max): array {
    if ((int) ($estado['inicio_janela'] ?? 0) + $janela <= $agoraAtual) {
        $estado['inicio_janela'] = $agoraAtual;
        $estado['falhas'] = 0;
        $estado['bloqueado_ate'] = 0;
    }

    $estado['falhas'] = (int) ($estado['falhas'] ?? 0) + 1;
    if ((int) $estado['falhas'] >= $max) {
        $estado['bloqueado_ate'] = $agoraAtual + $janela;
    }

    return $estado;
};

$rateLimitRegistros = [
    [
        'arquivo' => $montarArquivoRateLimit('ip:' . $ipCliente),
        'estado' => null,
    ],
    [
        'arquivo' => $montarArquivoRateLimit('ip_email:' . $ipCliente . '|' . $emailNormalizado),
        'estado' => null,
    ],
];

foreach ($rateLimitRegistros as $indice => $registro) {
    $estado = $carregarEstadoRateLimit((string) $registro['arquivo'], $agora);
    $rateLimitRegistros[$indice]['estado'] = $normalizarEstadoRateLimit($estado, $agora, $janelaSegundos);
}

$haBloqueioAtivo = static function (array $registros, int $agoraAtual): bool {
    foreach ($registros as $registro) {
        $estado = is_array($registro['estado'] ?? null) ? $registro['estado'] : [];
        if ((int) ($estado['bloqueado_ate'] ?? 0) > $agoraAtual) {
            return true;
        }
    }

    return false;
};

$persistirRateLimit = static function (array $registros) use ($salvarEstadoRateLimit): void {
    foreach ($registros as $registro) {
        $arquivo = (string) ($registro['arquivo'] ?? '');
        $estado = is_array($registro['estado'] ?? null) ? $registro['estado'] : null;
        if ($arquivo !== '' && $estado !== null) {
            $salvarEstadoRateLimit($arquivo, $estado);
        }
    }
};

$limparRateLimit = static function (array $registros) use ($limparEstadoRateLimit): void {
    foreach ($registros as $registro) {
        $arquivo = (string) ($registro['arquivo'] ?? '');
        if ($arquivo !== '') {
            $limparEstadoRateLimit($arquivo);
        }
    }
};

$registrarFalhaEmTodos = static function (array $registros, int $agoraAtual, int $janela, int $max) use ($registrarFalha): array {
    foreach ($registros as $indice => $registro) {
        $estadoAtual = is_array($registro['estado'] ?? null) ? $registro['estado'] : [
            'inicio_janela' => $agoraAtual,
            'falhas' => 0,
            'bloqueado_ate' => 0,
        ];
        $registros[$indice]['estado'] = $registrarFalha($estadoAtual, $agoraAtual, $janela, $max);
    }

    return $registros;
};

$redirecionarFalha = static function (array $registros) use ($persistirRateLimit, $haBloqueioAtivo, $agora): void {
    $persistirRateLimit($registros);
    $estaBloqueado = $haBloqueioAtivo($registros, $agora);
    header('Location: login.php?erro=' . ($estaBloqueado ? 'limite' : '1'));
    exit;
};

if ($haBloqueioAtivo($rateLimitRegistros, $agora)) {
    $conn->close();
    header('Location: login.php?erro=limite');
    exit;
}

if ($email === '' || $senha === '') {
    $conn->close();
    $rateLimitRegistros = $registrarFalhaEmTodos($rateLimitRegistros, $agora, $janelaSegundos, $maxTentativas);
    $redirecionarFalha($rateLimitRegistros);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $conn->close();
    $rateLimitRegistros = $registrarFalhaEmTodos($rateLimitRegistros, $agora, $janelaSegundos, $maxTentativas);
    $redirecionarFalha($rateLimitRegistros);
}

$sql = 'SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Falha ao preparar consulta de login: ' . $conn->error);
    $conn->close();
    header('Location: login.php?erro=sistema');
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (is_array($user) && password_verify($senha, (string) ($user['senha'] ?? ''))) {
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $user['id'];
    $_SESSION['usuario_nome'] = (string) ($user['nome'] ?? '');
    $_SESSION['usuario_nivel'] = (string) ($user['nivel'] ?? 'comum');
    $limparRateLimit($rateLimitRegistros);

    $conn->close();
    header('Location: ../index.php');
    exit;
}

$conn->close();
usleep(350000);
$rateLimitRegistros = $registrarFalhaEmTodos($rateLimitRegistros, $agora, $janelaSegundos, $maxTentativas);
$redirecionarFalha($rateLimitRegistros);
