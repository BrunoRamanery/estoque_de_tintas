<?php
/**
 * Sincroniza uma impressora especifica por ID.
 */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/sincronizacao_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    $conn->close();
    exit('ID invalido.');
}

$sql = 'SELECT id, nome, modelo, ip FROM impressoras WHERE id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $conn->close();
    exit('Falha ao preparar consulta da impressora.');
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$impressora = $result->fetch_assoc();
$stmt->close();

if (!$impressora) {
    $conn->close();
    exit('Impressora nao encontrada.');
}

$colunaUltimaAtualizacao = detectarColunaUltimaAtualizacao($conn);
$resultado = sincronizarImpressoraPorRegistro($conn, $impressora, $colunaUltimaAtualizacao);
$conn->close();

echo '<h1>Sincronizacao concluida</h1>';
echo '<p><strong>Impressora:</strong> ' . htmlspecialchars((string) ($resultado['nome'] ?? '')) . '</p>';
echo '<p><strong>IP:</strong> ' . htmlspecialchars((string) ($resultado['ip'] ?? '')) . '</p>';
echo '<p><strong>Status:</strong> ' . htmlspecialchars((string) ($resultado['status'] ?? 'Desconhecido')) . '</p>';
echo '<p><strong>Preto:</strong> ' . ($resultado['preto'] !== null ? ((int) $resultado['preto']) . '%' : 'Nao encontrado') . '</p>';
echo '<p><strong>Ciano:</strong> ' . ($resultado['ciano'] !== null ? ((int) $resultado['ciano']) . '%' : 'Nao encontrado') . '</p>';
echo '<p><strong>Magenta:</strong> ' . ($resultado['magenta'] !== null ? ((int) $resultado['magenta']) . '%' : 'Nao encontrado') . '</p>';
echo '<p><strong>Amarelo:</strong> ' . ($resultado['amarelo'] !== null ? ((int) $resultado['amarelo']) . '%' : 'Nao encontrado') . '</p>';
echo '<p><strong>Resultado:</strong> ' . ($resultado['ok'] ? 'OK' : 'ERRO') . '</p>';

if (!$resultado['ok'] && $resultado['erro'] !== '') {
    echo '<p><strong>Detalhe:</strong> ' . htmlspecialchars((string) $resultado['erro']) . '</p>';
}
