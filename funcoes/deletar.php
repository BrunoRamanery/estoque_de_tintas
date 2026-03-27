<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../app/repositorio_tintas.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

/**
 * Mantem o retorno para o mesmo contexto (modelo) quando existir.
 */
$retornoModelo = trim((string) ($_POST['retorno_modelo'] ?? $_GET['retorno_modelo'] ?? ''));
$redirectUrl = $retornoModelo !== ''
    ? '../detalhes.php?modelo=' . urlencode($retornoModelo)
    : '../index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para exclusao.');
    $conn->close();
    header('Location: ' . $redirectUrl);
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    definir_mensagem_flash('erro', 'ID invalido para exclusao.');
    $conn->close();
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $excluiu = excluir_tinta($conn, $id);
    definir_mensagem_flash($excluiu ? 'sucesso' : 'erro', $excluiu ? 'Registro excluido com sucesso.' : 'Erro ao excluir o registro.');
} catch (RuntimeException $erro) {
    error_log('Falha ao excluir tinta ID ' . $id . ': ' . $erro->getMessage());
    definir_mensagem_flash('erro', 'Erro interno ao excluir o registro.');
}

$conn->close();
header('Location: ' . $redirectUrl);
exit;
