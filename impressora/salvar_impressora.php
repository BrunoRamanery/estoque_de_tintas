<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../servicos/impressoras_servico.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para cadastro de impressora.');
    $conn->close();
    header('Location: cadastrar.php');
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$dados = [
    'nome' => trim((string) ($_POST['nome'] ?? '')),
    'modelo' => trim((string) ($_POST['modelo'] ?? '')),
    'ip' => trim((string) ($_POST['ip'] ?? '')),
    'localizacao' => trim((string) ($_POST['localizacao'] ?? '')),
    'observacao' => trim((string) ($_POST['observacao'] ?? '')),
];

$resultadoSalvar = null;
try {
    $resultadoSalvar = servico_impressoras_salvar($conn, $dados);
} catch (RuntimeException $erro) {
    $conn->close();
    $_SESSION['impressora_form_old'] = $dados;
    error_log('Falha ao salvar impressora: ' . $erro->getMessage());
    definir_mensagem_flash('erro', 'Erro ao cadastrar impressora.');
    header('Location: cadastrar.php');
    exit;
}

if (!($resultadoSalvar['ok'] ?? false)) {
    $_SESSION['impressora_form_old'] = $dados;
    definir_mensagem_flash('erro', implode(' ', (array) ($resultadoSalvar['erros'] ?? [])));
    $conn->close();
    header('Location: cadastrar.php');
    exit;
}

$conn->close();

unset($_SESSION['impressora_form_old']);
definir_mensagem_flash('sucesso', 'Impressora cadastrada com sucesso.');
header('Location: impressoras.php');
exit;

