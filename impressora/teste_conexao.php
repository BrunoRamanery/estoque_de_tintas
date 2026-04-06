<?php
/**
 * Arquivo de teste de conexao/leitura.
 * Nao grava nada no banco.
 */
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';
require_once __DIR__ . '/../conexao.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    exit('ID invalido.');
}

$sql = 'SELECT id, nome, modelo, ip FROM impressoras WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$impressora = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$impressora) {
    exit('Impressora nao encontrada.');
}

/*
|--------------------------------------------------------------------------
| 1) Primeiro testa o frameset
|--------------------------------------------------------------------------
*/
$urlFrameset = 'https://' . $impressora['ip'] . '/PRESENTATION/ADVANCED/COMMON/TOP';

/*
|--------------------------------------------------------------------------
| 2) Agora acessa direto a pagina real com os dados da impressora
|--------------------------------------------------------------------------
*/
$urlReal = 'https://' . $impressora['ip'] . '/PRESENTATION/ADVANCED/INFO_PRTINFO/TOP';

function buscarPagina($url)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);

    $html = curl_exec($ch);
    $erro = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'html' => $html,
        'erro' => $erro,
        'http' => $httpCode,
    ];
}

$frameset = buscarPagina($urlFrameset);
$real = buscarPagina($urlReal);

echo '<h1>Teste da impressora: ' . htmlspecialchars($impressora['nome']) . '</h1>';

echo '<h2>1) Frameset</h2>';
echo '<p><strong>URL:</strong> ' . htmlspecialchars($urlFrameset) . '</p>';
echo '<p><strong>HTTP:</strong> ' . (int) $frameset['http'] . '</p>';

if ($frameset['erro']) {
    echo '<p><strong>Erro:</strong> ' . htmlspecialchars($frameset['erro']) . '</p>';
} else {
    echo '<pre style="white-space: pre-wrap; background:#f4f4f4; padding:15px; border:1px solid #ccc;">';
    echo htmlspecialchars(substr($frameset['html'], 0, 1500));
    echo '</pre>';
}

echo '<hr>';

echo '<h2>2) Pagina real de informacoes</h2>';
echo '<p><strong>URL:</strong> ' . htmlspecialchars($urlReal) . '</p>';
echo '<p><strong>HTTP:</strong> ' . (int) $real['http'] . '</p>';

if ($real['erro']) {
    echo '<p><strong>Erro:</strong> ' . htmlspecialchars($real['erro']) . '</p>';
    exit;
}

if (!$real['html']) {
    echo '<p>Nenhum conteudo retornado.</p>';
    exit;
}

echo '<h3>Primeiros 5000 caracteres do HTML real:</h3>';
echo '<pre style="white-space: pre-wrap; background:#f4f4f4; padding:15px; border:1px solid #ccc;">';
echo htmlspecialchars(substr($real['html'], 0, 5000));
echo '</pre>';
