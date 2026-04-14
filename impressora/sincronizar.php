<?php
/**
 * Sincroniza uma impressora especifica por ID.
 */
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/sincronizacao_helper.php';

$buscaRetorno = trim((string) ($_POST['retorno_busca'] ?? ''));
$montarUrlRetorno = static function (string $busca): string {
    $url = 'impressoras.php';
    if ($busca !== '') {
        $url .= '?' . http_build_query(['busca' => $busca]);
    }
    return $url;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para sincronizacao individual.');
    $conn->close();
    header('Location: ' . $montarUrlRetorno($buscaRetorno));
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    definir_mensagem_flash('erro', 'ID invalido para sincronizacao.');
    $conn->close();
    header('Location: ' . $montarUrlRetorno($buscaRetorno));
    exit;
}

$sql = 'SELECT id, nome, modelo, ip FROM impressoras WHERE id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    definir_mensagem_flash('erro', 'Falha ao preparar consulta da impressora.');
    $conn->close();
    header('Location: ' . $montarUrlRetorno($buscaRetorno));
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$impressora = $result->fetch_assoc();
$stmt->close();

if (!$impressora) {
    definir_mensagem_flash('erro', 'Impressora nao encontrada.');
    $conn->close();
    header('Location: ' . $montarUrlRetorno($buscaRetorno));
    exit;
}

$colunaUltimaAtualizacao = detectarColunaUltimaAtualizacao($conn);
$resultado = sincronizarImpressoraPorRegistro($conn, $impressora, $colunaUltimaAtualizacao);
$conn->close();

if (!empty($resultado['ok'])) {
    definir_mensagem_flash(
        'sucesso',
        'Sincronizacao concluida para ' . (string) ($resultado['nome'] ?? 'impressora') . '.'
    );
} elseif (!empty($resultado['parcial'])) {
    $detalhe = trim((string) ($resultado['erro'] ?? ''));
    definir_mensagem_flash(
        'erro',
        'Sincronizacao parcial de ' . (string) ($resultado['nome'] ?? 'impressora') . ($detalhe !== '' ? ': ' . $detalhe : '.')
    );
} else {
    $detalhe = trim((string) ($resultado['erro'] ?? ''));
    definir_mensagem_flash(
        'erro',
        'Falha na sincronizacao de ' . (string) ($resultado['nome'] ?? 'impressora') . ($detalhe !== '' ? ': ' . $detalhe : '.')
    );
}

header('Location: ' . $montarUrlRetorno($buscaRetorno));
exit;
