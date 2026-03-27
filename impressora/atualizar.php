<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../servicos/impressoras_servico.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para atualizacao de impressora.');
    $conn->close();
    header('Location: impressoras.php');
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$buscaRetorno = trim((string) ($_POST['busca'] ?? ''));

if ($id === false || $id === null || $id <= 0) {
    $conn->close();
    definir_mensagem_flash('erro', 'ID de impressora invalido.');
    header('Location: impressoras.php');
    exit;
}

$dados = [
    'nome' => trim((string) ($_POST['nome'] ?? '')),
    'modelo' => trim((string) ($_POST['modelo'] ?? '')),
    'ip' => trim((string) ($_POST['ip'] ?? '')),
    'localizacao' => trim((string) ($_POST['localizacao'] ?? '')),
    'observacao' => trim((string) ($_POST['observacao'] ?? '')),
];

$chaveFormOld = 'impressora_form_old_editar_' . (int) $id;

try {
    $resultadoAtualizacao = servico_impressoras_atualizar($conn, (int) $id, $dados);
} catch (RuntimeException $erro) {
    $conn->close();
    $_SESSION[$chaveFormOld] = $dados;
    error_log('Falha ao atualizar impressora ID ' . $id . ': ' . $erro->getMessage());
    definir_mensagem_flash('erro', 'Erro ao atualizar impressora.');
    $query = ['id' => (int) $id];
    if ($buscaRetorno !== '') {
        $query['busca'] = $buscaRetorno;
    }
    header('Location: editar.php?' . http_build_query($query));
    exit;
}

$conn->close();

if (!($resultadoAtualizacao['ok'] ?? false)) {
    $_SESSION[$chaveFormOld] = $dados;
    definir_mensagem_flash('erro', implode(' ', (array) ($resultadoAtualizacao['erros'] ?? [])));
    $query = ['id' => (int) $id];
    if ($buscaRetorno !== '') {
        $query['busca'] = $buscaRetorno;
    }
    header('Location: editar.php?' . http_build_query($query));
    exit;
}

unset($_SESSION[$chaveFormOld]);
definir_mensagem_flash('sucesso', 'Impressora atualizada com sucesso.');
$query = ['id' => (int) $id];
if ($buscaRetorno !== '') {
    $query['busca'] = $buscaRetorno;
}
header('Location: detalhes.php?' . http_build_query($query));
exit;
