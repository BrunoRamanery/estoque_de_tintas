<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';

/**
 * Redireciona para a tela de cadastro preservando o tipo de erro.
 */
$redirecionarCadastro = static function (string $erro = ''): void {
    $url = 'cadastro_usuario.php';
    if ($erro !== '') {
        $url .= '?erro=' . urlencode($erro);
    }

    header('Location: ' . $url);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para cadastro de usuario.');
    $conn->close();
    $redirecionarCadastro();
}

$resultadoTotal = $conn->query('SELECT COUNT(*) AS total FROM usuarios');
if (!$resultadoTotal) {
    error_log('Falha ao validar total de usuarios: ' . $conn->error);
    $conn->close();
    $redirecionarCadastro('sistema');
}

$linhaTotal = $resultadoTotal->fetch_assoc() ?: ['total' => 0];
$resultadoTotal->free();
$cadastroInicial = (int) ($linhaTotal['total'] ?? 0) === 0;

if (!$cadastroInicial) {
    require_once __DIR__ . '/verificar_admin.php';
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');
$confirmar = (string) ($_POST['confirmar_senha'] ?? '');
$nivel = $cadastroInicial ? 'admin' : 'comum';

if ($nome === '' || $email === '' || $senha === '' || $confirmar === '') {
    $conn->close();
    $redirecionarCadastro('dados');
}

if (mb_strlen($nome) > 120) {
    $conn->close();
    $redirecionarCadastro('dados');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $conn->close();
    $redirecionarCadastro('email');
}

if (mb_strlen($email) > 150) {
    $conn->close();
    $redirecionarCadastro('email');
}

if (mb_strlen($senha) < 8) {
    $conn->close();
    $redirecionarCadastro('senha_fraca');
}

if (mb_strlen($senha) > 255) {
    $conn->close();
    $redirecionarCadastro('dados');
}

if ($senha !== $confirmar) {
    $conn->close();
    $redirecionarCadastro('senhas');
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);
if ($senhaHash === false) {
    error_log('Falha ao gerar hash de senha no cadastro de usuario.');
    $conn->close();
    $redirecionarCadastro('sistema');
}

$sqlCheck = 'SELECT id FROM usuarios WHERE email = ? LIMIT 1';
$stmt = $conn->prepare($sqlCheck);
if (!$stmt) {
    error_log('Falha ao preparar validacao de email: ' . $conn->error);
    $conn->close();
    $redirecionarCadastro('sistema');
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();
    $redirecionarCadastro('email');
}

$stmt->close();

$sql = 'INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Falha ao preparar insert de usuario: ' . $conn->error);
    $conn->close();
    $redirecionarCadastro('sistema');
}

$stmt->bind_param('ssss', $nome, $email, $senhaHash, $nivel);
$ok = $stmt->execute();
$erroExecucao = $stmt->error;
$stmt->close();
$conn->close();

if (!$ok) {
    error_log('Falha ao cadastrar usuario: ' . $erroExecucao);
    $redirecionarCadastro('sistema');
}

if ($cadastroInicial) {
    header('Location: login.php?cadastro=sucesso');
    exit;
}

definir_mensagem_flash('sucesso', 'Usuario cadastrado com sucesso.');
header('Location: ../index.php');
exit;
