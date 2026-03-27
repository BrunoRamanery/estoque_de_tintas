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

$chaveRateLimit = 'login_rate_limit';
$janelaSegundos = 300;
$maxTentativas = 5;
$agora = time();

$estadoRateLimit = $_SESSION[$chaveRateLimit] ?? null;
if (!is_array($estadoRateLimit)) {
    $estadoRateLimit = [
        'inicio_janela' => $agora,
        'falhas' => 0,
        'bloqueado_ate' => 0,
    ];
}

if ((int) ($estadoRateLimit['inicio_janela'] ?? 0) + $janelaSegundos <= $agora) {
    $estadoRateLimit = [
        'inicio_janela' => $agora,
        'falhas' => 0,
        'bloqueado_ate' => 0,
    ];
}

if ((int) ($estadoRateLimit['bloqueado_ate'] ?? 0) > $agora) {
    $conn->close();
    header('Location: login.php?erro=limite');
    exit;
}

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

$redirecionarFalha = static function (array $estado) use ($chaveRateLimit, $agora): void {
    $_SESSION[$chaveRateLimit] = $estado;

    $estaBloqueado = (int) ($estado['bloqueado_ate'] ?? 0) > $agora;
    header('Location: login.php?erro=' . ($estaBloqueado ? 'limite' : '1'));
    exit;
};

$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    $conn->close();
    $estadoRateLimit = $registrarFalha($estadoRateLimit, $agora, $janelaSegundos, $maxTentativas);
    $redirecionarFalha($estadoRateLimit);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $conn->close();
    $estadoRateLimit = $registrarFalha($estadoRateLimit, $agora, $janelaSegundos, $maxTentativas);
    $redirecionarFalha($estadoRateLimit);
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
    unset($_SESSION[$chaveRateLimit]);

    $conn->close();
    header('Location: ../index.php');
    exit;
}

$conn->close();
usleep(350000);
$estadoRateLimit = $registrarFalha($estadoRateLimit, $agora, $janelaSegundos, $maxTentativas);
$redirecionarFalha($estadoRateLimit);
