<?php
/**
 * Sincronizacao automatica de todas as impressoras via CLI.
 *
 * Execucao recomendada:
 * C:\xampp\php\php.exe C:\xampp\htdocs\estoque_de_tintas\impressora\sincronizar_todas_auto.php
 */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/sincronizacao_helper.php';

@set_time_limit(0);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script deve ser executado apenas via terminal (CLI).' . PHP_EOL);
}

function formatarPercentual(?int $valor): string
{
    return $valor !== null ? ((int) $valor) . '%' : '-';
}

function montarLinhaResultado(array $resultado): string
{
    $nome = trim((string) ($resultado['nome'] ?? ''));
    $ip = trim((string) ($resultado['ip'] ?? ''));
    $status = trim((string) ($resultado['status'] ?? 'Desconhecido'));

    $nomeTexto = $nome !== '' ? $nome : 'Sem nome';
    $ipTexto = $ip !== '' ? $ip : 'Sem IP';

    if (!empty($resultado['ok'])) {
        return sprintf(
            '[OK] %s - %s - Status: %s - BK:%s C:%s M:%s Y:%s',
            $nomeTexto,
            $ipTexto,
            $status,
            formatarPercentual($resultado['preto'] ?? null),
            formatarPercentual($resultado['ciano'] ?? null),
            formatarPercentual($resultado['magenta'] ?? null),
            formatarPercentual($resultado['amarelo'] ?? null)
        );
    }

    $erro = trim((string) ($resultado['erro'] ?? 'Falha desconhecida.'));
    return sprintf('[ERRO] %s - %s - %s', $nomeTexto, $ipTexto, $erro);
}

function prepararArquivoLog(string $diretorioLogs): ?string
{
    if (!is_dir($diretorioLogs) && !mkdir($diretorioLogs, 0775, true) && !is_dir($diretorioLogs)) {
        return null;
    }

    return rtrim($diretorioLogs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sincronizacao_automatica.log';
}

function escreverNoLog(?string $arquivoLog, string $linha): void
{
    if ($arquivoLog === null) {
        return;
    }

    file_put_contents($arquivoLog, $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$inicio = microtime(true);
$agora = date('Y-m-d H:i:s');
$arquivoLog = prepararArquivoLog(__DIR__ . '/../logs');

if ($arquivoLog === null) {
    echo '[AVISO] Nao foi possivel criar a pasta de logs. A execucao seguira sem gravacao em arquivo.' . PHP_EOL;
}

$cabecalho = str_repeat('=', 72);
echo $cabecalho . PHP_EOL;
echo 'Sincronizacao automatica iniciada em ' . $agora . PHP_EOL;
echo $cabecalho . PHP_EOL;

escreverNoLog($arquivoLog, $cabecalho);
escreverNoLog($arquivoLog, 'Sincronizacao automatica iniciada em ' . $agora);
escreverNoLog($arquivoLog, $cabecalho);

$consulta = $conn->query('SELECT id, nome, modelo, ip FROM impressoras ORDER BY nome ASC, id ASC');
if (!$consulta) {
    $mensagem = '[ERRO GERAL] Falha ao listar impressoras para sincronizacao.';
    echo $mensagem . PHP_EOL;
    escreverNoLog($arquivoLog, $mensagem);
    $conn->close();
    exit(1);
}

$impressoras = [];
while ($registro = $consulta->fetch_assoc()) {
    $impressoras[] = $registro;
}
$consulta->free();

$colunaUltimaAtualizacao = detectarColunaUltimaAtualizacao($conn);
$total = count($impressoras);
$sucesso = 0;
$falhas = 0;

foreach ($impressoras as $impressora) {
    try {
        // Em automacao, falha nao deve sobrescrever ultima_atualizacao valida.
        $resultado = sincronizarImpressoraPorRegistro($conn, $impressora, $colunaUltimaAtualizacao, false);
    } catch (Throwable $erro) {
        $idImpressora = (int) ($impressora['id'] ?? 0);
        if ($idImpressora > 0) {
            atualizarDadosImpressora($conn, $idImpressora, 'offline', null, null, null, null, null);
        }

        $resultado = [
            'id' => $idImpressora,
            'nome' => (string) ($impressora['nome'] ?? ''),
            'ip' => (string) ($impressora['ip'] ?? ''),
            'status' => 'offline',
            'preto' => null,
            'ciano' => null,
            'magenta' => null,
            'amarelo' => null,
            'ok' => false,
            'erro' => 'Erro interno: ' . $erro->getMessage(),
        ];
    }

    if (!empty($resultado['ok'])) {
        $sucesso++;
    } else {
        $falhas++;
    }

    $linha = montarLinhaResultado($resultado);
    echo $linha . PHP_EOL;
    escreverNoLog($arquivoLog, $linha);

    // Evita sobrecarga de conexoes seguidas na rede local.
    usleep(150000);
}

$duracaoSegundos = round(microtime(true) - $inicio, 2);
$rodape = str_repeat('-', 72);

echo $rodape . PHP_EOL;
echo 'Resumo final:' . PHP_EOL;
echo 'Total: ' . $total . PHP_EOL;
echo 'Sucesso: ' . $sucesso . PHP_EOL;
echo 'Falhas: ' . $falhas . PHP_EOL;
echo 'Duracao: ' . number_format($duracaoSegundos, 2, '.', '') . 's' . PHP_EOL;
echo $cabecalho . PHP_EOL;

escreverNoLog($arquivoLog, $rodape);
escreverNoLog($arquivoLog, 'Resumo final:');
escreverNoLog($arquivoLog, 'Total: ' . $total);
escreverNoLog($arquivoLog, 'Sucesso: ' . $sucesso);
escreverNoLog($arquivoLog, 'Falhas: ' . $falhas);
escreverNoLog($arquivoLog, 'Duracao: ' . number_format($duracaoSegundos, 2, '.', '') . 's');
escreverNoLog($arquivoLog, $cabecalho);

$conn->close();
exit(0);
