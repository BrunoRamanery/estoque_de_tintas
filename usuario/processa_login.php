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

$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    header('Location: login.php?erro=1');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    header('Location: login.php?erro=1');
    exit;
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

    $conn->close();
    header('Location: ../index.php');
    exit;
}

$conn->close();
header('Location: login.php?erro=1');
exit;
