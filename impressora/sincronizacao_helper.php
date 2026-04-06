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

function sincronizacaoDebugAtivo(?string $ip = null): bool
{
    $debugRaw = strtolower(trim((string) getenv('SYNC_DEBUG')));
    $debugHabilitado = in_array($debugRaw, ['1', 'true', 'on', 'yes'], true);
    if (!$debugHabilitado) {
        return false;
    }

    $filtroIp = trim((string) getenv('SYNC_DEBUG_IP'));
    if ($filtroIp === '' || $ip === null || trim($ip) === '') {
        return true;
    }

    $ipsPermitidos = array_values(array_filter(array_map('trim', explode(',', $filtroIp))));
    return in_array(trim($ip), $ipsPermitidos, true);
}

function sincronizacaoDebugSalvarHtml(): bool
{
    $valor = strtolower(trim((string) getenv('SYNC_DEBUG_SAVE_HTML')));
    return in_array($valor, ['1', 'true', 'on', 'yes'], true);
}

function sincronizacaoDiretorioDebug(): string
{
    return __DIR__ . '/../var/logs';
}

function sincronizacaoGravarDebug(bool $ativo, string $arquivo, string $linha): void
{
    if (!$ativo) {
        return;
    }

    $diretorio = dirname($arquivo);
    if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        return;
    }

    @file_put_contents($arquivo, $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function normalizarTextoParaBusca($texto): string
{
    $textoLimpo = limparTexto((string) $texto);
    if ($textoLimpo === '') {
        return '';
    }

    if (!preg_match('//u', $textoLimpo)) {
        $convertido = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $textoLimpo);
        if (is_string($convertido) && $convertido !== '') {
            $textoLimpo = $convertido;
        }
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $textoLimpo);
    if (is_string($ascii) && $ascii !== '') {
        $textoLimpo = $ascii;
    }

    // Remove artefatos da transliteracao, ex.: "n'umero", "impress~ao".
    $textoLimpo = str_replace(["'", '`', '´', '^', '~', '"'], '', $textoLimpo);

    $textoLimpo = strtolower($textoLimpo);
    $textoLimpo = preg_replace('/[^a-z0-9&\s]+/', ' ', $textoLimpo) ?? $textoLimpo;
    $textoLimpo = preg_replace('/\s+/', ' ', $textoLimpo) ?? $textoLimpo;
    return trim($textoLimpo);
}

function extrairInteiroDoHtml($html): ?int
{
    $texto = limparTexto((string) $html);
    if ($texto === '') {
        return null;
    }

    if (!preg_match('/(\d{1,12})/', $texto, $match)) {
        return null;
    }

    return (int) $match[1];
}

function identificarChaveContadorUso(string $rotuloNormalizado): ?string
{
    $rotulo = str_replace(['&nbsp;', '&amp;'], [' ', '&'], $rotuloNormalizado);
    $rotulo = preg_replace('/\s+/', ' ', $rotulo) ?? $rotulo;
    $rotulo = trim($rotulo);

    if (
        str_contains($rotulo, 'numero total de paginas a p&b')
        || str_contains($rotulo, 'numero total de paginas a pb')
        || str_contains($rotulo, 'numero total de paginas p&b')
        || str_contains($rotulo, 'numero total de paginas pb')
    ) {
        return 'pb';
    }

    if (str_contains($rotulo, 'numero total de paginas a cor') || str_contains($rotulo, 'numero total de paginas cor')) {
        return 'cor';
    }

    if (str_contains($rotulo, 'numero total de paginas')) {
        return 'total';
    }

    return null;
}

function extrairBlocoInformacoesUso(string $html): ?string
{
    if (!preg_match_all('/<fieldset\b[^>]*>(.*?)<\/fieldset>/is', $html, $fieldsets, PREG_SET_ORDER)) {
        return null;
    }

    foreach ($fieldsets as $fieldset) {
        $bloco = (string) ($fieldset[0] ?? '');
        if ($bloco === '') {
            continue;
        }

        if (!preg_match('/<legend\b[^>]*>(.*?)<\/legend>/is', $bloco, $legendMatch)) {
            continue;
        }

        $legendNormalizada = normalizarTextoParaBusca($legendMatch[1] ?? '');
        if (
            str_contains($legendNormalizada, 'informacoes da impressao')
            || str_contains($legendNormalizada, 'estado de utilizacao')
            || str_contains($legendNormalizada, 'informacoes de manutencao')
        ) {
            return $bloco;
        }
    }

    return null;
}

function extrairContadoresUsoPorRotulo(string $html): array
{
    $contadores = [
        'total' => null,
        'pb' => null,
        'cor' => null,
    ];

    if (!preg_match_all('/<dt\b[^>]*>(.*?)<\/dt>\s*<dd\b[^>]*>(.*?)<\/dd>/is', $html, $pares, PREG_SET_ORDER)) {
        return $contadores;
    }

    foreach ($pares as $par) {
        $rotuloNormalizado = normalizarTextoParaBusca((string) ($par[1] ?? ''));
        $chave = identificarChaveContadorUso($rotuloNormalizado);
        if ($chave === null || $contadores[$chave] !== null) {
            continue;
        }

        $valor = extrairInteiroDoHtml((string) ($par[2] ?? ''));
        if ($valor !== null) {
            $contadores[$chave] = $valor;
        }
    }

    return $contadores;
}

function extrairContadoresUsoPorOrdem(string $html): array
{
    $contadores = [
        'total' => null,
        'pb' => null,
        'cor' => null,
    ];

    if (!preg_match_all('/<dd\b[^>]*>(.*?)<\/dd>/is', $html, $blocosDd, PREG_SET_ORDER)) {
        return $contadores;
    }

    $valores = [];
    foreach ($blocosDd as $blocoDd) {
        $valor = extrairInteiroDoHtml((string) ($blocoDd[1] ?? ''));
        if ($valor !== null) {
            $valores[] = $valor;
        }
    }

    if (isset($valores[0])) {
        $contadores['total'] = (int) $valores[0];
    }
    if (isset($valores[1])) {
        $contadores['pb'] = (int) $valores[1];
    }
    if (isset($valores[2])) {
        $contadores['cor'] = (int) $valores[2];
    }

    return $contadores;
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

    $textoBlocoNormalizado = normalizarTextoParaBusca($blocoEstado);
    if (str_contains($textoBlocoNormalizado, 'disponivel')) {
        return 'Disponivel';
    }

    if (str_contains($textoBlocoNormalizado, 'ocupado')) {
        return 'Ocupado';
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

function buscarUsoImpressora($ip)
{
    $ipNormalizado = trim((string) $ip);
    $url = "https://$ipNormalizado/PRESENTATION/ADVANCED/INFO_MENTINFO/TOP";
    $debugAtivo = sincronizacaoDebugAtivo($ipNormalizado);
    $nomeIpArquivo = preg_replace('/[^0-9A-Za-z._-]/', '_', $ipNormalizado) ?: 'sem_ip';
    $diretorioDebug = sincronizacaoDiretorioDebug();
    $arquivoLog = $diretorioDebug . '/debug_uso_' . $nomeIpArquivo . '.log';
    $arquivoHtml = $diretorioDebug . '/debug_uso_' . $nomeIpArquivo . '.html';

    $gravarDebug = static function (string $linha) use ($debugAtivo, $arquivoLog): void {
        sincronizacaoGravarDebug($debugAtivo, $arquivoLog, $linha);
    };

    if ($debugAtivo) {
        $gravarDebug(str_repeat('=', 70));
        $gravarDebug('Data/Hora: ' . date('Y-m-d H:i:s'));
        $gravarDebug('IP teste: ' . $ipNormalizado);
        $gravarDebug('URL: ' . $url);
    }

    if (!function_exists('curl_init')) {
        $gravarDebug('Acesso URL: NAO');
        $gravarDebug('Erro cURL: extensao cURL nao disponivel no PHP.');
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);

    $html = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $conteudoRetornado = $html === false || $html === null ? '' : (string) $html;
    $acessoOk = $conteudoRetornado !== '' && $erroCurl === '' && $httpCode >= 200 && $httpCode < 400;

    $gravarDebug('Acesso URL: ' . ($acessoOk ? 'SIM' : 'NAO'));
    $gravarDebug('HTTP code: ' . $httpCode);
    $gravarDebug('Erro cURL: ' . ($erroCurl !== '' ? $erroCurl : 'nenhum'));
    $gravarDebug('Retorno (500 primeiros chars):');
    $gravarDebug(substr($conteudoRetornado, 0, 500));

    if ($acessoOk && sincronizacaoDebugSalvarHtml()) {
        sincronizacaoGravarDebug(true, $arquivoHtml, $conteudoRetornado);
        $gravarDebug('HTML salvo em: ' . $arquivoHtml);
    }

    if (!$acessoOk) {
        return null;
    }

    $preencherFaltantes = static function (array $base, array $fallback): array {
        foreach (['total', 'pb', 'cor'] as $chave) {
            if ($base[$chave] === null && $fallback[$chave] !== null) {
                $base[$chave] = (int) $fallback[$chave];
            }
        }

        return $base;
    };

    $blocoPreferencial = extrairBlocoInformacoesUso($conteudoRetornado);
    $origem = $blocoPreferencial !== null ? 'fieldset' : 'html_completo';
    $contadores = extrairContadoresUsoPorRotulo($blocoPreferencial ?? $conteudoRetornado);

    if (in_array(null, $contadores, true) && $blocoPreferencial !== null) {
        $contadores = $preencherFaltantes($contadores, extrairContadoresUsoPorRotulo($conteudoRetornado));
        $origem .= '+fallback_html';
    }

    if (in_array(null, $contadores, true)) {
        $contadores = $preencherFaltantes($contadores, extrairContadoresUsoPorOrdem($blocoPreferencial ?? $conteudoRetornado));
        $origem .= '+fallback_ordem';
    }

    $gravarDebug('Origem dos contadores: ' . $origem);
    $gravarDebug('Contadores extraidos: ' . var_export($contadores, true));

    return [
        'total' => $contadores['total'] !== null ? (int) $contadores['total'] : null,
        'pb' => $contadores['pb'] !== null ? (int) $contadores['pb'] : null,
        'cor' => $contadores['cor'] !== null ? (int) $contadores['cor'] : null,
    ];
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

function sincronizarImpressoraPorRegistro(
    mysqli $conn,
    array $impressora,
    ?string $colunaUltimaAtualizacao = null,
    bool $atualizarUltimaAtualizacaoEmFalha = true
): array
{
    $id = (int) ($impressora['id'] ?? 0);
    $nome = trim((string) ($impressora['nome'] ?? ''));
    $ip = trim((string) ($impressora['ip'] ?? ''));
    $debugSyncAtivo = sincronizacaoDebugAtivo($ip);
    $nomeIpArquivo = preg_replace('/[^0-9A-Za-z._-]/', '_', $ip) ?: 'sem_ip';
    $arquivoDebugSync = sincronizacaoDiretorioDebug() . '/debug_fluxo_sync_' . $nomeIpArquivo . '.log';
    $gravarDebugSync = static function (string $linha) use ($debugSyncAtivo, $arquivoDebugSync): void {
        sincronizacaoGravarDebug($debugSyncAtivo, $arquivoDebugSync, $linha);
    };

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

    if ($debugSyncAtivo) {
        $gravarDebugSync(str_repeat('=', 72));
        $gravarDebugSync('Data/Hora: ' . date('Y-m-d H:i:s'));
        $gravarDebugSync('id da impressora: ' . $id);
        $gravarDebugSync('ip: ' . $ip);
    }

    if ($id <= 0) {
        $resultado['erro'] = 'ID da impressora invalido.';
        return $resultado;
    }

    $colunaUpdateFalha = $atualizarUltimaAtualizacaoEmFalha ? $colunaUltimaAtualizacao : null;

    if ($ip === '') {
        $update = atualizarDadosImpressora($conn, $id, 'offline', null, null, null, null, $colunaUpdateFalha);
        $resultado['erro'] = $update['ok'] ? 'IP vazio.' : $update['erro'];
        return $resultado;
    }

    $leitura = buscarHtmlInfoImpressora($ip);
    $falhouConexao = $leitura['erro'] !== '' || $leitura['html'] === null || $leitura['http'] !== 200;

    if ($falhouConexao) {
        $update = atualizarDadosImpressora($conn, $id, 'offline', null, null, null, null, $colunaUpdateFalha);
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

    $uso = buscarUsoImpressora($ip);
    $gravarDebugSync('retorno buscarUsoImpressora: ' . var_export($uso, true));
    if ($uso) {
        $sql = "UPDATE impressoras SET 
            paginas_total = ?, 
            paginas_pb = ?, 
            paginas_cor = ?";
        if ($colunaUltimaAtualizacao !== null && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $colunaUltimaAtualizacao)) {
            $sql .= ", " . $colunaUltimaAtualizacao . " = NOW()";
        }
        $sql .= ' WHERE id = ?';
        $gravarDebugSync('SQL usado no update das paginas: ' . preg_replace('/\s+/', ' ', trim($sql)));

        $stmt = $conn->prepare($sql);
        $gravarDebugSync('resultado prepare(): ' . ($stmt ? 'SUCESSO' : 'FALHOU'));
        if ($stmt) {
            $totalUso = isset($uso['total']) && is_numeric($uso['total']) ? (int) $uso['total'] : null;
            $pbUso = isset($uso['pb']) && is_numeric($uso['pb']) ? (int) $uso['pb'] : null;
            $corUso = isset($uso['cor']) && is_numeric($uso['cor']) ? (int) $uso['cor'] : null;

            $stmt->bind_param(
                'iiii',
                $totalUso,
                $pbUso,
                $corUso,
                $id
            );
            $okExecute = $stmt->execute();
            $gravarDebugSync('resultado execute(): ' . ($okExecute ? 'SUCESSO' : 'FALHOU'));
            $gravarDebugSync('erro statement: ' . ($stmt->error !== '' ? $stmt->error : 'nenhum'));
            $stmt->close();
        } else {
            $gravarDebugSync('erro statement: ' . ($conn->error !== '' ? $conn->error : 'prepare retornou false sem mensagem'));
        }
    } else {
        $gravarDebugSync('update de paginas ignorado porque retorno de uso foi nulo/vazio.');
    }

    $resultado['status'] = $status;
    $resultado['preto'] = $preto;
    $resultado['ciano'] = $ciano;
    $resultado['magenta'] = $magenta;
    $resultado['amarelo'] = $amarelo;
    $resultado['ok'] = true;
    return $resultado;
}

