<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../servicos/impressoras_servico.php';

$buscaRetorno = trim((string) ($_POST['busca'] ?? $_GET['busca'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para exclusao de impressora.');
    $conn->close();
    $url = 'impressoras.php';
    if ($buscaRetorno !== '') {
        $url .= '?' . http_build_query(['busca' => $buscaRetorno]);
    }
    header('Location: ' . $url);
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    $conn->close();
    definir_mensagem_flash('erro', 'ID de impressora invalido.');
    header('Location: impressoras.php');
    exit;
}

try {
    $resultadoExclusao = servico_impressoras_excluir($conn, (int) $id);
} catch (RuntimeException $erro) {
    $conn->close();
    error_log('Falha ao excluir impressora ID ' . $id . ': ' . $erro->getMessage());
    definir_mensagem_flash('erro', 'Erro ao excluir impressora.');
    $url = 'impressoras.php';
    if ($buscaRetorno !== '') {
        $url .= '?' . http_build_query(['busca' => $buscaRetorno]);
    }
    header('Location: ' . $url);
    exit;
}

$conn->close();

if (!($resultadoExclusao['ok'] ?? false)) {
    definir_mensagem_flash('erro', implode(' ', (array) ($resultadoExclusao['erros'] ?? [])));
} else {
    definir_mensagem_flash('sucesso', 'Impressora excluida com sucesso.');
}

$redirectUrl = 'impressoras.php';
if ($buscaRetorno !== '') {
    $redirectUrl .= '?' . http_build_query(['busca' => $buscaRetorno]);
}
header('Location: ' . $redirectUrl);
exit;
