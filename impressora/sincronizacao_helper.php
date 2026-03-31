<?php
/**
 * Funcoes compartilhadas para sincronizacao de impressoras Epson.
 */

function limparTexto($texto)
{
    $texto = strip_tags((string) $texto);
    $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
    return trim($texto);
}

function normalizarTextoStatusImpressora($texto)
{
    $texto = limparTexto($texto);
    $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;
    $texto = trim($texto);
    $texto = preg_replace('/\.+$/', '', $texto) ?? $texto;
    return trim($texto);
}

function extrairStatusImpressora($html)
{
    $conteudo = (string) $html;
    if ($conteudo === '') {
        return 'Desconhecido';
    }

    // Restringe a busca para o bloco do "Estado da impressora".
    $padraoBloco = '/<legend[^>]*>\s*Estado\s+da\s+impressora\s*<\/legend>(.*?)(?:<\/fieldset>|<legend\b|$)/is';
    if (!preg_match($padraoBloco, $conteudo, $blocoMatch)) {
        return 'Desconhecido';
    }

    $blocoEstado = (string) ($blocoMatch[1] ?? '');

    if (preg_match('/<li\b[^>]*class=(["\'])[^"\']*\bvalue\b[^"\']*\1[^>]*>(.*?)<\/li>/is', $blocoEstado, $valorMatch)) {
        $status = normalizarTextoStatusImpressora($valorMatch[2] ?? '');
        if ($status !== '') {
            return $status;
        }
    }

    if (preg_match('/<div\b[^>]*class=(["\'])[^"\']*\bpreserve-white-space\b[^"\']*\1[^>]*>(.*?)<\/div>/is', $blocoEstado, $divMatch)) {
        $status = normalizarTextoStatusImpressora($divMatch[2] ?? '');
        if ($status !== '') {
            return $status;
        }
    }

    $textoBloco = normalizarTextoStatusImpressora($blocoEstado);
    if (preg_match('/\b(Dispon[ií]vel|Ocupado)\b/i', $textoBloco, $chaveMatch)) {
        return normalizarTextoStatusImpressora($chaveMatch[1]);
    }

    return 'Desconhecido';
}

function normalizarSiglaCor($valor)
{
    $mapa = [
        'BK' => 'BK',
        'K' => 'BK',
        'BLACK' => 'BK',
        'PBK' => 'BK',
        'C' => 'C',
        'CYAN' => 'C',
        'M' => 'M',
        'MAGENTA' => 'M',
        'Y' => 'Y',
        'YELLOW' => 'Y',
    ];

    $chave = strtoupper(trim(limparTexto((string) $valor)));
    return $mapa[$chave] ?? null;
}

function limitarPercentual($valor)
{
    $numero = (int) round((float) $valor);
    if ($numero < 0) {
        return 0;
    }

    if ($numero > 100) {
        return 100;
    }

    return $numero;
}

function extrairNivelNoBlocoGradient($blocoHtml)
{
    if (!preg_match('/linear-gradient\s*\((.*?)\)/si', (string) $blocoHtml, $gradienteMatch)) {
        return null;
    }

    if (!preg_match_all('/(\d{1,3}(?:\.\d+)?)\s*%/i', $gradienteMatch[1], $percentuais) || empty($percentuais[1])) {
        return null;
    }

    $valores = array_map('floatval', $percentuais[1]);

    // Formato mais comum da Epson: 0%, N%, N%, 100%.
    if (count($valores) >= 4 && $valores[0] <= 1 && $valores[count($valores) - 1] >= 99) {
        return limitarPercentual($valores[1]);
    }

    $intermediarios = array_values(array_filter(
        $valores,
        static fn(float $v): bool => $v > 0 && $v < 100
    ));

    if (!empty($intermediarios)) {
        return limitarPercentual(max($intermediarios));
    }

    return limitarPercentual($valores[count($valores) - 1]);
}

function extrairNivelPorCorViaGradiente($html, $siglaCanonica)
{
    $padraoTanque = '/<li\b[^>]*class=(["\'])[^"\']*\btank\b[^"\']*\1[^>]*>(.*?)<\/li>/si';
    if (!preg_match_all($padraoTanque, (string) $html, $blocos, PREG_SET_ORDER)) {
        return null;
    }

    foreach ($blocos as $bloco) {
        $htmlTanque = (string) ($bloco[0] ?? '');
        $conteudoTanque = (string) ($bloco[2] ?? '');

        if (!preg_match('/<div\b[^>]*class=(["\'])[^"\']*\bclrname\b[^"\']*\1[^>]*>(.*?)<\/div>/si', $conteudoTanque, $corMatch)) {
            continue;
        }

        $corBloco = normalizarSiglaCor($corMatch[2] ?? '');
        if ($corBloco !== $siglaCanonica) {
            continue;
        }

        $nivel = extrairNivelNoBlocoGradient($htmlTanque);
        if ($nivel !== null) {
            return $nivel;
        }
    }

    return null;
}

function extrairNivelPorCorViaImagem($html, $siglaCanonica)
{
    $mapaImagem = [
        'BK' => 'Ink_K',
        'C' => 'Ink_C',
        'M' => 'Ink_M',
        'Y' => 'Ink_Y',
    ];

    $tokenImagem = $mapaImagem[$siglaCanonica] ?? null;
    if ($tokenImagem === null) {
        return null;
    }

    $padraoImg = '/<img\b[^>]*src\s*=\s*(["\'])[^"\']*' . preg_quote($tokenImagem, '/') . '(?:\.[^"\']+)?[^"\']*\1[^>]*>/i';
    if (!preg_match($padraoImg, (string) $html, $imgMatch)) {
        return null;
    }

    $tagImg = (string) ($imgMatch[0] ?? '');

    if (preg_match('/\bheight\s*=\s*(["\']?)(\d{1,3}(?:\.\d+)?)\1/i', $tagImg, $alturaMatch)) {
        return limitarPercentual((((float) $alturaMatch[2]) / 36) * 100);
    }

    if (preg_match('/\bstyle\s*=\s*(["\'])[^"\']*height\s*:\s*(\d{1,3}(?:\.\d+)?)\s*px/i', $tagImg, $alturaStyleMatch)) {
        return limitarPercentual((((float) $alturaStyleMatch[2]) / 36) * 100);
    }

    return null;
}

function extrairNivelPorCor($html, $sigla)
{
    $siglaCanonica = normalizarSiglaCor($sigla);
    if ($siglaCanonica === null) {
        return null;
    }

    $nivelGradiente = extrairNivelPorCorViaGradiente($html, $siglaCanonica);
    if ($nivelGradiente !== null) {
        return $nivelGradiente;
    }

    return extrairNivelPorCorViaImagem($html, $siglaCanonica);
}

function detectarColunaUltimaAtualizacao(mysqli $conn): ?string
{
    $resultado = $conn->query("SHOW COLUMNS FROM impressoras LIKE 'ultima_atualizacao'");
    if (!$resultado) {
        return null;
    }

    $encontrou = $resultado->num_rows > 0;
    $resultado->free();

    return $encontrou ? 'ultima_atualizacao' : null;
}

function montarUrlInfoImpressora(string $ip, string $protocolo = 'https'): string
{
    $protocoloNormalizado = strtolower(trim($protocolo)) === 'http' ? 'http' : 'https';
    return $protocoloNormalizado . '://' . $ip . '/PRESENTATION/ADVANCED/INFO_PRTINFO/TOP';
}

function executarRequisicaoImpressora(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        // Base usada no sincronizar individual
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        // Ajustes para reduzir erro de socket no processamento em lote
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_PROXY => '',
    ]);

    $html = curl_exec($ch);
    $erro = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'url' => $url,
        'html' => $html === false ? null : (string) $html,
        'erro' => (string) $erro,
        'http' => (int) $httpCode,
    ];
}

function deveTentarFallbackHttp(array $resposta): bool
{
    if (($resposta['html'] ?? null) !== null && (int) ($resposta['http'] ?? 0) > 0) {
        return false;
    }

    $erro = strtolower((string) ($resposta['erro'] ?? ''));
    if ($erro === '') {
        return (int) ($resposta['http'] ?? 0) === 0;
    }

    return str_contains($erro, 'failed to connect')
        || str_contains($erro, 'timed out')
        || str_contains($erro, 'ssl')
        || str_contains($erro, 'bad access');
}

function buscarHtmlInfoImpressora(string $ip): array
{
    if (!function_exists('curl_init')) {
        return [
            'url' => montarUrlInfoImpressora($ip, 'https'),
            'html' => null,
            'erro' => 'Extensao cURL nao disponivel no PHP.',
            'http' => 0,
        ];
    }

    $tentativaHttps = executarRequisicaoImpressora(montarUrlInfoImpressora($ip, 'https'));
    if (($tentativaHttps['erro'] ?? '') === '' && ($tentativaHttps['html'] ?? null) !== null) {
        return $tentativaHttps;
    }

    if (!deveTentarFallbackHttp($tentativaHttps)) {
        return $tentativaHttps;
    }

    $tentativaHttp = executarRequisicaoImpressora(montarUrlInfoImpressora($ip, 'http'));
    if (($tentativaHttp['erro'] ?? '') === '' && ($tentativaHttp['html'] ?? null) !== null) {
        return $tentativaHttp;
    }

    $erroHttps = trim((string) ($tentativaHttps['erro'] ?? ''));
    $erroHttp = trim((string) ($tentativaHttp['erro'] ?? ''));
    if ($erroHttps !== '' && $erroHttp !== '') {
        $tentativaHttp['erro'] = 'HTTPS: ' . $erroHttps . ' | HTTP: ' . $erroHttp;
    } elseif ($erroHttps !== '' && $erroHttp === '') {
        $tentativaHttp['erro'] = 'HTTPS: ' . $erroHttps;
    }

    return $tentativaHttp;
}

function atualizarDadosImpressora(
    mysqli $conn,
    int $id,
    string $status,
    ?int $preto,
    ?int $ciano,
    ?int $magenta,
    ?int $amarelo,
    ?string $colunaUltimaAtualizacao = null
): array {
    $sql = "UPDATE impressoras
            SET status_impressora = ?,
                tinta_preto = ?,
                tinta_ciano = ?,
                tinta_magenta = ?,
                tinta_amarelo = ?";

    if ($colunaUltimaAtualizacao !== null && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $colunaUltimaAtualizacao)) {
        $sql .= ", " . $colunaUltimaAtualizacao . " = NOW()";
    }

    $sql .= ' WHERE id = ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'erro' => 'Falha ao preparar update da impressora.'];
    }

    $stmt->bind_param('siiiii', $status, $preto, $ciano, $magenta, $amarelo, $id);
    $ok = $stmt->execute();
    $erro = $stmt->error;
    $stmt->close();

    return [
        'ok' => (bool) $ok,
        'erro' => $ok ? '' : ('Falha ao executar update: ' . $erro),
    ];
}

function sincronizarImpressoraPorRegistro(mysqli $conn, array $impressora, ?string $colunaUltimaAtualizacao = null): array
{
    $id = (int) ($impressora['id'] ?? 0);
    $nome = trim((string) ($impressora['nome'] ?? ''));
    $ip = trim((string) ($impressora['ip'] ?? ''));

    $resultado = [
        'id' => $id,
        'nome' => $nome,
        'ip' => $ip,
        'status' => 'offline',
        'preto' => null,
        'ciano' => null,
        'magenta' => null,
        'amarelo' => null,
        'ok' => false,
        'erro' => '',
    ];

    if ($id <= 0) {
        $resultado['erro'] = 'ID da impressora invalido.';
        return $resultado;
    }

    if ($ip === '') {
        $update = atualizarDadosImpressora($conn, $id, 'offline', null, null, null, null, $colunaUltimaAtualizacao);
        $resultado['erro'] = $update['ok'] ? 'IP vazio.' : $update['erro'];
        return $resultado;
    }

    $leitura = buscarHtmlInfoImpressora($ip);
    $falhouConexao = $leitura['erro'] !== '' || $leitura['html'] === null || $leitura['http'] !== 200;

    if ($falhouConexao) {
        $update = atualizarDadosImpressora($conn, $id, 'offline', null, null, null, null, $colunaUltimaAtualizacao);
        if (!$update['ok']) {
            $resultado['erro'] = $update['erro'];
            return $resultado;
        }

        $mensagemErro = $leitura['erro'] !== '' ? $leitura['erro'] : ('HTTP ' . (int) $leitura['http']);
        $resultado['erro'] = 'Falha ao acessar impressora: ' . $mensagemErro;
        return $resultado;
    }

    $html = (string) $leitura['html'];
    $status = extrairStatusImpressora($html);
    $preto = extrairNivelPorCor($html, 'BK');
    $ciano = extrairNivelPorCor($html, 'C');
    $magenta = extrairNivelPorCor($html, 'M');
    $amarelo = extrairNivelPorCor($html, 'Y');

    $update = atualizarDadosImpressora(
        $conn,
        $id,
        $status,
        $preto,
        $ciano,
        $magenta,
        $amarelo,
        $colunaUltimaAtualizacao
    );

    if (!$update['ok']) {
        $resultado['erro'] = $update['erro'];
        return $resultado;
    }

    $resultado['status'] = $status;
    $resultado['preto'] = $preto;
    $resultado['ciano'] = $ciano;
    $resultado['magenta'] = $magenta;
    $resultado['amarelo'] = $amarelo;
    $resultado['ok'] = true;
    return $resultado;
}
