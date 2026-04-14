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
    return __DIR__ . '/../logs';
}

function sincronizacaoGravarLinha(string $arquivo, string $linha): void
{
    $diretorio = dirname($arquivo);
    if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        return;
    }

    @file_put_contents($arquivo, $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function sincronizacaoGravarDebug(bool $ativo, string $arquivo, string $linha): void
{
    if (!$ativo) {
        return;
    }

    sincronizacaoGravarLinha($arquivo, $linha);
}

function sincronizacaoNormalizarNomeArquivo(string $valor, string $fallback = 'sem_ip'): string
{
    $normalizado = preg_replace('/[^0-9A-Za-z._-]/', '_', trim($valor)) ?? '';
    $normalizado = trim($normalizado, '._-');
    return $normalizado !== '' ? $normalizado : $fallback;
}

function sincronizacaoCriarLogger(string $arquivo): callable
{
    return static function (string $linha) use ($arquivo): void {
        sincronizacaoGravarLinha($arquivo, $linha);
    };
}

function sincronizacaoBoolTexto(bool $valor): string
{
    return $valor ? 'SIM' : 'NAO';
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

function extrairBlocoPorLegenda(string $html, array $legendasAceitas): ?string
{
    if (!preg_match_all('/<fieldset\b[^>]*>(.*?)<\/fieldset>/is', $html, $fieldsets, PREG_SET_ORDER)) {
        return null;
    }

    $legendasNormalizadas = array_map('normalizarTextoParaBusca', $legendasAceitas);

    foreach ($fieldsets as $fieldset) {
        $bloco = (string) ($fieldset[0] ?? '');
        if ($bloco === '') {
            continue;
        }

        if (!preg_match('/<legend\b[^>]*>(.*?)<\/legend>/is', $bloco, $legendMatch)) {
            continue;
        }

        $legendNormalizada = normalizarTextoParaBusca($legendMatch[1] ?? '');
        foreach ($legendasNormalizadas as $legendaAceita) {
            if ($legendaAceita !== '' && str_contains($legendNormalizada, $legendaAceita)) {
                return $bloco;
            }
        }
    }

    return null;
}

function extrairBlocoInformacoesUso(string $html): ?string
{
    return extrairBlocoPorLegenda($html, [
        'informacoes da impressao',
        'estado de utilizacao',
        'informacoes de manutencao',
        'print information',
        'maintenance information',
    ]);
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

function extrairCelulasTabela(string $linhaHtml): array
{
    if (!preg_match_all('/<(?:th|td)\b[^>]*>(.*?)<\/(?:th|td)>/is', $linhaHtml, $celulas)) {
        return [];
    }

    return $celulas[1] ?? [];
}

function cabecalhoTabelaParaMapa(array $celulasHtml): array
{
    $mapa = [
        'pb_simples' => null,
        'cor_simples' => null,
        'pb_duplex' => null,
        'cor_duplex' => null,
    ];

    foreach ($celulasHtml as $indice => $celulaHtml) {
        $texto = normalizarTextoParaBusca($celulaHtml);
        if ($texto === '') {
            continue;
        }

        $temPb = str_contains($texto, 'pb')
            || str_contains($texto, 'p&b')
            || str_contains($texto, 'preto')
            || str_contains($texto, 'mono');
        $temCor = str_contains($texto, 'cor') || str_contains($texto, 'color');
        $temSimples = str_contains($texto, 'simplex')
            || str_contains($texto, 'simples')
            || str_contains($texto, 'simple');
        $temDuplex = str_contains($texto, 'duplex')
            || str_contains($texto, 'dupla')
            || str_contains($texto, 'double');

        if ($temPb && $temSimples) {
            $mapa['pb_simples'] = $indice;
        }
        if ($temCor && $temSimples) {
            $mapa['cor_simples'] = $indice;
        }
        if ($temPb && $temDuplex) {
            $mapa['pb_duplex'] = $indice;
        }
        if ($temCor && $temDuplex) {
            $mapa['cor_duplex'] = $indice;
        }
    }

    return $mapa;
}

function linhaEhA4Letter(array $celulasHtml): bool
{
    if (empty($celulasHtml)) {
        return false;
    }

    $primeira = normalizarTextoParaBusca($celulasHtml[0]);
    if ($primeira === '') {
        return false;
    }

    return (str_contains($primeira, 'a4') && str_contains($primeira, 'letter'))
        || str_contains($primeira, 'a4/letter');
}

function linhaEhA3Ledger(array $celulasHtml): bool
{
    if (empty($celulasHtml)) {
        return false;
    }

    $primeira = normalizarTextoParaBusca($celulasHtml[0]);
    if ($primeira === '') {
        return false;
    }

    return (str_contains($primeira, 'a3') && str_contains($primeira, 'ledger'))
        || str_contains($primeira, 'a3/ledger');
}

function extrairA4LetterOrdenadoPorTamanho(string $html, ?callable $logger = null): array
{
    $resultado = [
        'a4_pb_simples' => null,
        'a4_cor_simples' => null,
        'a4_pb_duplex' => null,
        'a4_cor_duplex' => null,
    ];

    if (!preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $html, $tabelas, PREG_SET_ORDER)) {
        return $resultado;
    }

    foreach ($tabelas as $tabelaMatch) {
        $tabelaHtml = (string) ($tabelaMatch[0] ?? '');
        if ($tabelaHtml === '') {
            continue;
        }

        if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tabelaHtml, $linhas, PREG_SET_ORDER)) {
            continue;
        }

        $mapaCabecalho = null;
        foreach ($linhas as $linhaMatch) {
            $linhaHtml = (string) ($linhaMatch[0] ?? '');
            if ($linhaHtml === '') {
                continue;
            }

            $celulas = extrairCelulasTabela($linhaHtml);
            if (empty($celulas)) {
                continue;
            }

            if ($mapaCabecalho === null && preg_match('/<th\b/i', $linhaHtml)) {
                $mapaCabecalho = cabecalhoTabelaParaMapa($celulas);
                continue;
            }

            if (!linhaEhA4Letter($celulas)) {
                continue;
            }

            $valores = [];
            foreach ($celulas as $indice => $celulaHtml) {
                if ($indice === 0) {
                    continue;
                }
                $valores[$indice] = extrairInteiroDoHtml($celulaHtml);
            }

            $mapaCompleto = is_array($mapaCabecalho)
                && $mapaCabecalho['pb_simples'] !== null
                && $mapaCabecalho['cor_simples'] !== null
                && $mapaCabecalho['pb_duplex'] !== null
                && $mapaCabecalho['cor_duplex'] !== null;

            if ($mapaCompleto) {
                $resultado['a4_pb_simples'] = $valores[$mapaCabecalho['pb_simples']] ?? null;
                $resultado['a4_cor_simples'] = $valores[$mapaCabecalho['cor_simples']] ?? null;
                $resultado['a4_pb_duplex'] = $valores[$mapaCabecalho['pb_duplex']] ?? null;
                $resultado['a4_cor_duplex'] = $valores[$mapaCabecalho['cor_duplex']] ?? null;
            } else {
                $valoresOrdenados = array_values($valores);
                $resultado['a4_pb_simples'] = $valoresOrdenados[0] ?? null;
                $resultado['a4_cor_simples'] = $valoresOrdenados[1] ?? null;
                $resultado['a4_pb_duplex'] = $valoresOrdenados[2] ?? null;
                $resultado['a4_cor_duplex'] = $valoresOrdenados[3] ?? null;
            }

            if ($logger) {
                $logger('Linha A4/Letter encontrada: ' . var_export($resultado, true));
            }

            return $resultado;
        }
    }

    if ($logger) {
        $logger('Linha A4/Letter nao encontrada.');
    }

    return $resultado;
}

function extrairA3LedgerOrdenadoPorTamanho(string $html, ?callable $logger = null): array
{
    $resultado = [
        'a3_pb_simples' => null,
        'a3_cor_simples' => null,
        'a3_pb_duplex' => null,
        'a3_cor_duplex' => null,
    ];

    if (!preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $html, $tabelas, PREG_SET_ORDER)) {
        return $resultado;
    }

    foreach ($tabelas as $tabelaMatch) {
        $tabelaHtml = (string) ($tabelaMatch[0] ?? '');
        if ($tabelaHtml === '') {
            continue;
        }

        if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tabelaHtml, $linhas, PREG_SET_ORDER)) {
            continue;
        }

        $mapaCabecalho = null;
        foreach ($linhas as $linhaMatch) {
            $linhaHtml = (string) ($linhaMatch[0] ?? '');
            if ($linhaHtml === '') {
                continue;
            }

            $celulas = extrairCelulasTabela($linhaHtml);
            if (empty($celulas)) {
                continue;
            }

            if ($mapaCabecalho === null && preg_match('/<th\b/i', $linhaHtml)) {
                $mapaCabecalho = cabecalhoTabelaParaMapa($celulas);
                continue;
            }

            if (!linhaEhA3Ledger($celulas)) {
                continue;
            }

            $valores = [];
            foreach ($celulas as $indice => $celulaHtml) {
                if ($indice === 0) {
                    continue;
                }
                $valores[$indice] = extrairInteiroDoHtml($celulaHtml);
            }

            $mapaCompleto = is_array($mapaCabecalho)
                && $mapaCabecalho['pb_simples'] !== null
                && $mapaCabecalho['cor_simples'] !== null
                && $mapaCabecalho['pb_duplex'] !== null
                && $mapaCabecalho['cor_duplex'] !== null;

            if ($mapaCompleto) {
                $resultado['a3_pb_simples'] = $valores[$mapaCabecalho['pb_simples']] ?? null;
                $resultado['a3_cor_simples'] = $valores[$mapaCabecalho['cor_simples']] ?? null;
                $resultado['a3_pb_duplex'] = $valores[$mapaCabecalho['pb_duplex']] ?? null;
                $resultado['a3_cor_duplex'] = $valores[$mapaCabecalho['cor_duplex']] ?? null;
            } else {
                $valoresOrdenados = array_values($valores);
                $resultado['a3_pb_simples'] = $valoresOrdenados[0] ?? null;
                $resultado['a3_cor_simples'] = $valoresOrdenados[1] ?? null;
                $resultado['a3_pb_duplex'] = $valoresOrdenados[2] ?? null;
                $resultado['a3_cor_duplex'] = $valoresOrdenados[3] ?? null;
            }

            if ($logger) {
                $logger('Linha A3/Ledger encontrada: ' . var_export($resultado, true));
            }

            return $resultado;
        }
    }

    if ($logger) {
        $logger('Linha A3/Ledger nao encontrada.');
    }

    return $resultado;
}

function normalizarTextoStatusImpressora($texto)
{
    $texto = limparTexto($texto);
    $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;
    $texto = trim($texto);
    $texto = preg_replace('/\.+$/', '', $texto) ?? $texto;
    return trim($texto);
}

function normalizarStatusImpressora($status): string
{
    $status = normalizarTextoStatusImpressora($status);
    if ($status === '') {
        return '';
    }

    $normalizado = normalizarTextoParaBusca($status);
    if (str_contains($normalizado, 'disponivel') || str_contains($normalizado, 'ready')) {
        return 'Disponível';
    }

    if (str_contains($normalizado, 'ocupado') || str_contains($normalizado, 'busy')) {
        return 'Ocupado';
    }

    if (str_contains($normalizado, 'offline')) {
        return 'offline';
    }

    return $status;
}

function extrairStatusImpressora($html)
{
    $conteudo = (string) $html;
    if ($conteudo === '') {
        return 'Desconhecido';
    }

    $blocoEstado = extrairBlocoPorLegenda($conteudo, [
        'estado da impressora',
        'printer status',
    ]);
    if ($blocoEstado === null) {
        return 'Desconhecido';
    }

    if (preg_match('/<li\b[^>]*class=(["\'])[^"\']*\bvalue\b[^"\']*\1[^>]*>(.*?)<\/li>/is', $blocoEstado, $valorMatch)) {
        $status = normalizarStatusImpressora($valorMatch[2] ?? '');
        if ($status !== '') {
            return $status;
        }
    }

    if (preg_match('/<div\b[^>]*class=(["\'])[^"\']*\bpreserve-white-space\b[^"\']*\1[^>]*>(.*?)<\/div>/is', $blocoEstado, $divMatch)) {
        $status = normalizarStatusImpressora($divMatch[2] ?? '');
        if ($status !== '') {
            return $status;
        }
    }

    $textoBlocoNormalizado = normalizarTextoParaBusca($blocoEstado);
    if (str_contains($textoBlocoNormalizado, 'disponivel') || str_contains($textoBlocoNormalizado, 'ready')) {
        return 'Disponível';
    }

    if (str_contains($textoBlocoNormalizado, 'ocupado') || str_contains($textoBlocoNormalizado, 'busy')) {
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

function sincronizacaoRotasPorContexto(string $contexto): array
{
    if ($contexto === 'uso') {
        return ['/PRESENTATION/ADVANCED/INFO_MENTINFO/TOP'];
    }

    return ['/PRESENTATION/ADVANCED/INFO_PRTINFO/TOP'];
}

function montarUrlImpressora(string $ip, string $rota, string $protocolo = 'https'): string
{
    $ipNormalizado = trim($ip);
    $rotaNormalizada = '/' . ltrim(trim($rota), '/');
    $protocoloNormalizado = strtolower(trim($protocolo)) === 'http' ? 'http' : 'https';
    return $protocoloNormalizado . '://' . $ipNormalizado . $rotaNormalizada;
}

function montarUrlInfoImpressora(string $ip, string $protocolo = 'https'): string
{
    return montarUrlImpressora($ip, '/PRESENTATION/ADVANCED/INFO_PRTINFO/TOP', $protocolo);
}

function executarRequisicaoImpressora(string $url): array
{
    if (!function_exists('curl_init')) {
        return [
            'url' => $url,
            'url_final' => $url,
            'html' => null,
            'erro' => 'Extensao cURL nao disponivel no PHP.',
            'errno' => 0,
            'http' => 0,
            'redirects' => 0,
            'content_type' => '',
            'tempo_total' => 0.0,
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_PROXY => '',
        CURLOPT_ENCODING => '',
    ]);

    $html = curl_exec($ch);
    $erro = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $urlFinal = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $redirects = (int) curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $tempoTotal = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    return [
        'url' => $url,
        'url_final' => $urlFinal !== '' ? $urlFinal : $url,
        'html' => $html === false ? null : (string) $html,
        'erro' => (string) $erro,
        'errno' => (int) $errno,
        'http' => $httpCode,
        'redirects' => $redirects,
        'content_type' => $contentType,
        'tempo_total' => $tempoTotal,
    ];
}

function sincronizacaoExplicarErroCurl(array $resposta): string
{
    $erro = trim((string) ($resposta['erro'] ?? ''));
    $erroNormalizado = strtolower($erro);
    if ($erroNormalizado === '') {
        return '';
    }

    if (str_contains($erroNormalizado, 'timed out')) {
        return 'Timeout na conexao com a impressora.';
    }

    if (str_contains($erroNormalizado, 'ssl')) {
        return 'Falha SSL ao acessar a impressora.';
    }

    if (str_contains($erroNormalizado, 'failed to connect') || str_contains($erroNormalizado, 'bad access')) {
        return 'Falha de conexao com a impressora.';
    }

    return $erro;
}

function sincronizacaoRespostaEhLogin(string $html): bool
{
    $conteudo = (string) $html;
    $normalizado = normalizarTextoParaBusca($conteudo);

    if (preg_match('/type\s*=\s*["\']password["\']/i', $conteudo)) {
        return true;
    }

    return (str_contains($normalizado, 'administrator password') || str_contains($normalizado, 'admin password'))
        || (str_contains($normalizado, 'login') && str_contains($normalizado, 'password'))
        || (str_contains($normalizado, 'senha') && str_contains($normalizado, 'administrador'))
        || (str_contains($normalizado, 'authentication') && str_contains($normalizado, 'password'));
}

function sincronizacaoRespostaEhFrameset(string $html): bool
{
    $conteudo = strtolower((string) $html);

    return str_contains($conteudo, '<frameset')
        || preg_match('/<frame\b/i', $conteudo)
        || (str_contains($conteudo, 'function link') && str_contains($conteudo, 'settimeout("link()'));
}

function sincronizacaoMarcadoresValidos(string $html, string $contexto): bool
{
    $conteudo = (string) $html;
    $normalizado = normalizarTextoParaBusca($conteudo);

    if ($contexto === 'uso') {
        return str_contains($normalizado, 'numero total de paginas')
            || str_contains($normalizado, 'informacoes da impressao')
            || str_contains($normalizado, 'estado de utilizacao')
            || str_contains($normalizado, 'numero de paginas ordenadas por tamanho')
            || str_contains($normalizado, 'a4 letter')
            || str_contains($normalizado, 'a3 ledger');
    }

    return str_contains($normalizado, 'estado da impressora')
        || str_contains($normalizado, 'printer status')
        || str_contains($normalizado, 'informacoes da impressora')
        || preg_match('/\btank\b/i', $conteudo)
        || str_contains($conteudo, 'Ink_K')
        || str_contains($conteudo, 'Ink_C')
        || str_contains($conteudo, 'Ink_M')
        || str_contains($conteudo, 'Ink_Y');
}

function sincronizacaoClassificarRespostaHtml(string $html, string $contexto): array
{
    $conteudo = trim((string) $html);
    if ($conteudo === '') {
        return ['ok' => false, 'tipo' => 'vazio', 'motivo' => 'Resposta vazia.'];
    }

    if (sincronizacaoRespostaEhLogin($conteudo)) {
        return ['ok' => false, 'tipo' => 'login', 'motivo' => 'Tela de senha/login detectada.'];
    }

    if (sincronizacaoRespostaEhFrameset($conteudo)) {
        return ['ok' => false, 'tipo' => 'frameset', 'motivo' => 'Frameset ou redirecionamento intermediario detectado.'];
    }

    if (!sincronizacaoMarcadoresValidos($conteudo, $contexto)) {
        return ['ok' => false, 'tipo' => 'invalida', 'motivo' => 'Resposta HTML nao corresponde a pagina esperada.'];
    }

    return ['ok' => true, 'tipo' => 'ok', 'motivo' => 'Resposta valida.'];
}

function buscarPaginaImpressora(string $ip, array $rotas, string $contexto, ?callable $logger = null, ?string $arquivoHtml = null, bool $salvarHtml = false): array
{
    $ipNormalizado = trim($ip);
    $resultadoFalha = [
        'ok' => false,
        'contexto' => $contexto,
        'html' => null,
        'url' => null,
        'url_final' => null,
        'protocolo' => null,
        'fallback_http' => false,
        'http' => 0,
        'motivo_falha' => 'Falha desconhecida.',
        'tipo_falha' => 'desconhecida',
        'tentativas' => [],
    ];

    if ($ipNormalizado === '') {
        $resultadoFalha['motivo_falha'] = 'IP vazio.';
        $resultadoFalha['tipo_falha'] = 'ip_vazio';
        return $resultadoFalha;
    }

    if (!function_exists('curl_init')) {
        $resultadoFalha['motivo_falha'] = 'Extensao cURL nao disponivel no PHP.';
        $resultadoFalha['tipo_falha'] = 'curl_indisponivel';
        return $resultadoFalha;
    }

    $rotasNormalizadas = array_values(array_filter(array_map(static function ($rota): string {
        return '/' . ltrim(trim((string) $rota), '/');
    }, $rotas)));

    $tentativas = [];
    $ultimaFalha = $resultadoFalha;

    foreach (['https', 'http'] as $protocolo) {
        foreach ($rotasNormalizadas as $rota) {
            $url = montarUrlImpressora($ipNormalizado, $rota, $protocolo);
            if ($logger) {
                $logger(sprintf('[%s] Tentando URL: %s', strtoupper($contexto), $url));
            }

            $resposta = executarRequisicaoImpressora($url);
            $tentativa = [
                'url' => $url,
                'url_final' => $resposta['url_final'] ?? $url,
                'protocolo' => $protocolo,
                'http' => (int) ($resposta['http'] ?? 0),
                'erro' => trim((string) ($resposta['erro'] ?? '')),
                'tipo_falha' => '',
                'motivo' => '',
                'tempo_total' => (float) ($resposta['tempo_total'] ?? 0.0),
                'redirects' => (int) ($resposta['redirects'] ?? 0),
            ];

            if ($tentativa['erro'] !== '') {
                $tentativa['tipo_falha'] = 'curl';
                $tentativa['motivo'] = sincronizacaoExplicarErroCurl($resposta);
            } elseif ($tentativa['http'] < 200 || $tentativa['http'] >= 400) {
                $tentativa['tipo_falha'] = 'http';
                $tentativa['motivo'] = 'HTTP ' . $tentativa['http'] . '.';
            } else {
                $classificacaoHtml = sincronizacaoClassificarRespostaHtml((string) ($resposta['html'] ?? ''), $contexto);
                $tentativa['tipo_falha'] = $classificacaoHtml['tipo'];
                $tentativa['motivo'] = $classificacaoHtml['motivo'];

                if (!empty($classificacaoHtml['ok'])) {
                    if ($logger) {
                        $logger(sprintf(
                            '[%s] URL valida: %s | protocolo=%s | http=%d | redirects=%d | fallback_http=%s',
                            strtoupper($contexto),
                            $url,
                            $protocolo,
                            $tentativa['http'],
                            $tentativa['redirects'],
                            sincronizacaoBoolTexto($protocolo === 'http')
                        ));
                    }

                    if ($salvarHtml && $arquivoHtml !== null && $arquivoHtml !== '') {
                        sincronizacaoGravarLinha($arquivoHtml, (string) ($resposta['html'] ?? ''));
                    }

                    $tentativas[] = $tentativa;
                    return [
                        'ok' => true,
                        'contexto' => $contexto,
                        'html' => (string) ($resposta['html'] ?? ''),
                        'url' => $url,
                        'url_final' => $resposta['url_final'] ?? $url,
                        'protocolo' => $protocolo,
                        'fallback_http' => $protocolo === 'http',
                        'http' => $tentativa['http'],
                        'motivo_falha' => '',
                        'tipo_falha' => '',
                        'tentativas' => $tentativas,
                    ];
                }
            }

            $tentativas[] = $tentativa;

            if ($logger) {
                $logger(sprintf(
                    '[%s] URL falhou: %s | protocolo=%s | http=%d | erro=%s | motivo=%s',
                    strtoupper($contexto),
                    $url,
                    $protocolo,
                    $tentativa['http'],
                    $tentativa['erro'] !== '' ? $tentativa['erro'] : 'nenhum',
                    $tentativa['motivo']
                ));
            }

            $ultimaFalha = [
                'ok' => false,
                'contexto' => $contexto,
                'html' => null,
                'url' => $url,
                'url_final' => $resposta['url_final'] ?? $url,
                'protocolo' => $protocolo,
                'fallback_http' => $protocolo === 'http',
                'http' => $tentativa['http'],
                'motivo_falha' => $tentativa['motivo'] !== '' ? $tentativa['motivo'] : 'Falha ao acessar a URL.',
                'tipo_falha' => $tentativa['tipo_falha'] !== '' ? $tentativa['tipo_falha'] : 'desconhecida',
                'tentativas' => $tentativas,
            ];

            if ($tentativa['tipo_falha'] === 'login') {
                return $ultimaFalha;
            }
        }
    }

    return $ultimaFalha;
}

function buscarHtmlInfoImpressora(string $ip, ?callable $logger = null): array
{
    $arquivoHtml = null;
    if (sincronizacaoDebugSalvarHtml()) {
        $arquivoHtml = sincronizacaoDiretorioDebug() . '/debug_status_' . sincronizacaoNormalizarNomeArquivo($ip) . '.html';
    }

    return buscarPaginaImpressora(
        $ip,
        sincronizacaoRotasPorContexto('status_tinta'),
        'status_tinta',
        $logger,
        $arquivoHtml,
        sincronizacaoDebugSalvarHtml()
    );
}

function sincronizacaoClassificarBlocoStatusTinta(?string $status, array $tintas): array
{
    $statusValido = $status !== null && trim($status) !== '' && normalizarTextoParaBusca($status) !== 'desconhecido';
    $tintasValidas = 0;
    foreach ($tintas as $valor) {
        if ($valor !== null) {
            $tintasValidas++;
        }
    }

    if ($statusValido && $tintasValidas === 4) {
        return ['classificacao' => 'sucesso', 'status_lido' => true, 'tinta_lida' => true];
    }

    if ($statusValido || $tintasValidas > 0) {
        return ['classificacao' => 'parcial', 'status_lido' => $statusValido, 'tinta_lida' => $tintasValidas > 0];
    }

    return ['classificacao' => 'falha', 'status_lido' => false, 'tinta_lida' => false];
}

function coletarStatusETintaImpressora(string $ip, ?callable $logger = null): array
{
    $pagina = buscarHtmlInfoImpressora($ip, $logger);
    if (empty($pagina['ok'])) {
        return [
            'ok' => false,
            'parcial' => false,
            'classificacao' => 'falha',
            'motivo' => (string) ($pagina['motivo_falha'] ?? 'Falha ao acessar pagina de status/tinta.'),
            'status' => null,
            'tinta_preto' => null,
            'tinta_ciano' => null,
            'tinta_magenta' => null,
            'tinta_amarelo' => null,
            'status_lido' => false,
            'tinta_lida' => false,
            'meta' => $pagina,
        ];
    }

    $html = (string) ($pagina['html'] ?? '');
    $status = extrairStatusImpressora($html);
    $statusValido = trim($status) !== '' && normalizarTextoParaBusca($status) !== 'desconhecido';
    if (!$statusValido) {
        $status = null;
    }

    $dadosTintas = [
        'tinta_preto' => extrairNivelPorCor($html, 'BK'),
        'tinta_ciano' => extrairNivelPorCor($html, 'C'),
        'tinta_magenta' => extrairNivelPorCor($html, 'M'),
        'tinta_amarelo' => extrairNivelPorCor($html, 'Y'),
    ];

    $classificacao = sincronizacaoClassificarBlocoStatusTinta($status, $dadosTintas);
    $motivo = '';
    if ($classificacao['classificacao'] === 'parcial') {
        $motivo = 'Pagina de status/tinta acessada, mas o parser retornou dados parciais.';
    } elseif ($classificacao['classificacao'] === 'falha') {
        $motivo = 'Pagina de status/tinta acessada, mas o parser nao encontrou dados validos.';
    }

    if ($logger) {
        $logger('Status lido: ' . sincronizacaoBoolTexto($classificacao['status_lido']));
        $logger('Tinta lida: ' . sincronizacaoBoolTexto($classificacao['tinta_lida']));
        $logger('Status extraido: ' . ($status !== null ? $status : 'N/D'));
        $logger('Tintas extraidas: ' . json_encode($dadosTintas, JSON_UNESCAPED_UNICODE));
    }

    return [
        'ok' => $classificacao['classificacao'] === 'sucesso',
        'parcial' => $classificacao['classificacao'] === 'parcial',
        'classificacao' => $classificacao['classificacao'],
        'motivo' => $motivo,
        'status' => $status,
        'tinta_preto' => $dadosTintas['tinta_preto'],
        'tinta_ciano' => $dadosTintas['tinta_ciano'],
        'tinta_magenta' => $dadosTintas['tinta_magenta'],
        'tinta_amarelo' => $dadosTintas['tinta_amarelo'],
        'status_lido' => $classificacao['status_lido'],
        'tinta_lida' => $classificacao['tinta_lida'],
        'meta' => $pagina,
    ];
}

function sincronizacaoPreencherContadoresFaltantes(array $base, array $fallback): array
{
    foreach (['total', 'pb', 'cor'] as $chave) {
        if ($base[$chave] === null && $fallback[$chave] !== null) {
            $base[$chave] = (int) $fallback[$chave];
        }
    }

    return $base;
}

function sincronizacaoAlgumValorInteiro(array $dados, array $campos): bool
{
    foreach ($campos as $campo) {
        if (array_key_exists($campo, $dados) && $dados[$campo] !== null && is_numeric($dados[$campo])) {
            return true;
        }
    }

    return false;
}

function buscarUsoImpressora($ip, ?callable $logger = null): array
{
    $ipNormalizado = trim((string) $ip);
    $arquivoHtml = null;
    if (sincronizacaoDebugSalvarHtml()) {
        $arquivoHtml = sincronizacaoDiretorioDebug() . '/debug_uso_' . sincronizacaoNormalizarNomeArquivo($ipNormalizado) . '.html';
    }

    $pagina = buscarPaginaImpressora(
        $ipNormalizado,
        sincronizacaoRotasPorContexto('uso'),
        'uso',
        $logger,
        $arquivoHtml,
        sincronizacaoDebugSalvarHtml()
    );

    if (empty($pagina['ok'])) {
        return [
            'ok' => false,
            'parcial' => false,
            'classificacao' => 'falha',
            'motivo' => (string) ($pagina['motivo_falha'] ?? 'Falha ao acessar pagina de uso.'),
            'dados' => [
                'total' => null,
                'pb' => null,
                'cor' => null,
                'a4_pb_simples' => null,
                'a4_cor_simples' => null,
                'a4_pb_duplex' => null,
                'a4_cor_duplex' => null,
                'a3_pb_simples' => null,
                'a3_cor_simples' => null,
                'a3_pb_duplex' => null,
                'a3_cor_duplex' => null,
            ],
            'paginas_lidas' => false,
            'a4_lido' => false,
            'a3_lido' => false,
            'meta' => $pagina,
        ];
    }

    $conteudoRetornado = (string) ($pagina['html'] ?? '');
    $blocoPreferencial = extrairBlocoInformacoesUso($conteudoRetornado);
    $origem = $blocoPreferencial !== null ? 'fieldset' : 'html_completo';
    $contadores = extrairContadoresUsoPorRotulo($blocoPreferencial ?? $conteudoRetornado);

    if (in_array(null, $contadores, true) && $blocoPreferencial !== null) {
        $contadores = sincronizacaoPreencherContadoresFaltantes($contadores, extrairContadoresUsoPorRotulo($conteudoRetornado));
        $origem .= '+fallback_html';
    }

    if (in_array(null, $contadores, true)) {
        $contadores = sincronizacaoPreencherContadoresFaltantes($contadores, extrairContadoresUsoPorOrdem($blocoPreferencial ?? $conteudoRetornado));
        $origem .= '+fallback_ordem';
    }

    $a4Letter = extrairA4LetterOrdenadoPorTamanho($conteudoRetornado, $logger);
    $a3Ledger = extrairA3LedgerOrdenadoPorTamanho($conteudoRetornado, $logger);

    $dados = [
        'total' => $contadores['total'] !== null ? (int) $contadores['total'] : null,
        'pb' => $contadores['pb'] !== null ? (int) $contadores['pb'] : null,
        'cor' => $contadores['cor'] !== null ? (int) $contadores['cor'] : null,
        'a4_pb_simples' => isset($a4Letter['a4_pb_simples']) && is_numeric($a4Letter['a4_pb_simples']) ? (int) $a4Letter['a4_pb_simples'] : null,
        'a4_cor_simples' => isset($a4Letter['a4_cor_simples']) && is_numeric($a4Letter['a4_cor_simples']) ? (int) $a4Letter['a4_cor_simples'] : null,
        'a4_pb_duplex' => isset($a4Letter['a4_pb_duplex']) && is_numeric($a4Letter['a4_pb_duplex']) ? (int) $a4Letter['a4_pb_duplex'] : null,
        'a4_cor_duplex' => isset($a4Letter['a4_cor_duplex']) && is_numeric($a4Letter['a4_cor_duplex']) ? (int) $a4Letter['a4_cor_duplex'] : null,
        'a3_pb_simples' => isset($a3Ledger['a3_pb_simples']) && is_numeric($a3Ledger['a3_pb_simples']) ? (int) $a3Ledger['a3_pb_simples'] : null,
        'a3_cor_simples' => isset($a3Ledger['a3_cor_simples']) && is_numeric($a3Ledger['a3_cor_simples']) ? (int) $a3Ledger['a3_cor_simples'] : null,
        'a3_pb_duplex' => isset($a3Ledger['a3_pb_duplex']) && is_numeric($a3Ledger['a3_pb_duplex']) ? (int) $a3Ledger['a3_pb_duplex'] : null,
        'a3_cor_duplex' => isset($a3Ledger['a3_cor_duplex']) && is_numeric($a3Ledger['a3_cor_duplex']) ? (int) $a3Ledger['a3_cor_duplex'] : null,
    ];

    $paginasLidas = $dados['total'] !== null && $dados['pb'] !== null && $dados['cor'] !== null;
    $a4Lido = sincronizacaoAlgumValorInteiro($dados, ['a4_pb_simples', 'a4_cor_simples', 'a4_pb_duplex', 'a4_cor_duplex']);
    $a3Lido = sincronizacaoAlgumValorInteiro($dados, ['a3_pb_simples', 'a3_cor_simples', 'a3_pb_duplex', 'a3_cor_duplex']);

    $classificacao = 'falha';
    $motivo = 'Pagina de uso acessada, mas o parser nao encontrou dados validos.';
    if ($paginasLidas && ($a4Lido || $a3Lido)) {
        $classificacao = 'sucesso';
        $motivo = '';
    } elseif ($paginasLidas || $a4Lido || $a3Lido) {
        $classificacao = 'parcial';
        $motivo = 'Pagina de uso acessada, mas o parser retornou dados parciais.';
    }

    if ($logger) {
        $logger('Origem dos contadores de uso: ' . $origem);
        $logger('Paginas lidas: ' . sincronizacaoBoolTexto($paginasLidas));
        $logger('A4 lido: ' . sincronizacaoBoolTexto($a4Lido));
        $logger('A3 lido: ' . sincronizacaoBoolTexto($a3Lido));
        $logger('Uso extraido: ' . json_encode($dados, JSON_UNESCAPED_UNICODE));
    }

    return [
        'ok' => $classificacao === 'sucesso',
        'parcial' => $classificacao === 'parcial',
        'classificacao' => $classificacao,
        'motivo' => $motivo,
        'dados' => $dados,
        'paginas_lidas' => $paginasLidas,
        'a4_lido' => $a4Lido,
        'a3_lido' => $a3Lido,
        'meta' => $pagina,
    ];
}
function sincronizacaoCamposNumericosUso(): array
{
    return [
        'paginas_total',
        'paginas_pb',
        'paginas_cor',
        'a4_pb_simples',
        'a4_cor_simples',
        'a4_pb_duplex',
        'a4_cor_duplex',
        'a3_pb_simples',
        'a3_cor_simples',
        'a3_pb_duplex',
        'a3_cor_duplex',
    ];
}

function buscarSnapshotImpressora(mysqli $conn, int $id): ?array
{
    $sql = 'SELECT status_impressora, tinta_preto, tinta_ciano, tinta_magenta, tinta_amarelo,
                   paginas_total, paginas_pb, paginas_cor,
                   a4_pb_simples, a4_cor_simples, a4_pb_duplex, a4_cor_duplex,
                   a3_pb_simples, a3_cor_simples, a3_pb_duplex, a3_cor_duplex,
                   ultima_atualizacao
            FROM impressoras
            WHERE id = ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    foreach (array_merge(
        ['tinta_preto', 'tinta_ciano', 'tinta_magenta', 'tinta_amarelo'],
        sincronizacaoCamposNumericosUso()
    ) as $campoNumerico) {
        if (array_key_exists($campoNumerico, $row)) {
            $row[$campoNumerico] = ($row[$campoNumerico] !== null && $row[$campoNumerico] !== '') ? (int) $row[$campoNumerico] : null;
        }
    }

    $row['status_impressora'] = trim((string) ($row['status_impressora'] ?? ''));
    $row['ultima_atualizacao'] = trim((string) ($row['ultima_atualizacao'] ?? ''));

    return $row;
}

function atualizarCamposImpressora(
    mysqli $conn,
    int $id,
    array $campos,
    ?string $colunaUltimaAtualizacao = null,
    bool $atualizarUltimaAtualizacao = false
): array {
    if ($id <= 0) {
        return ['ok' => false, 'erro' => 'ID invalido para update.', 'campos' => []];
    }

    $colunasPermitidas = array_flip(array_merge(
        ['status_impressora', 'tinta_preto', 'tinta_ciano', 'tinta_magenta', 'tinta_amarelo'],
        sincronizacaoCamposNumericosUso()
    ));

    $set = [];
    $tipos = '';
    $parametros = [];
    $camposAplicados = [];

    foreach ($campos as $coluna => $valor) {
        if (!isset($colunasPermitidas[$coluna]) || $valor === null) {
            continue;
        }

        $set[] = $coluna . ' = ?';
        $camposAplicados[] = $coluna;

        if ($coluna === 'status_impressora') {
            $tipos .= 's';
            $parametros[] = (string) $valor;
        } else {
            $tipos .= 'i';
            $parametros[] = (int) $valor;
        }
    }

    if (empty($set) && !($atualizarUltimaAtualizacao && $colunaUltimaAtualizacao !== null && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $colunaUltimaAtualizacao))) {
        return ['ok' => false, 'erro' => 'Nenhum campo valido para atualizar.', 'campos' => []];
    }

    if ($atualizarUltimaAtualizacao && $colunaUltimaAtualizacao !== null && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $colunaUltimaAtualizacao)) {
        $set[] = $colunaUltimaAtualizacao . ' = NOW()';
    }

    $sql = 'UPDATE impressoras SET ' . implode(', ', $set) . ' WHERE id = ?';
    $tipos .= 'i';
    $parametros[] = $id;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'erro' => 'Falha ao preparar update da impressora: ' . $conn->error, 'campos' => $camposAplicados];
    }

    $stmt->bind_param($tipos, ...$parametros);
    $ok = $stmt->execute();
    $erro = $stmt->error;
    $stmt->close();

    return [
        'ok' => (bool) $ok,
        'erro' => $ok ? '' : ('Falha ao executar update: ' . $erro),
        'campos' => $camposAplicados,
        'sql' => $sql,
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
    $campos = ['status_impressora' => $status];
    if ($preto !== null) {
        $campos['tinta_preto'] = $preto;
    }
    if ($ciano !== null) {
        $campos['tinta_ciano'] = $ciano;
    }
    if ($magenta !== null) {
        $campos['tinta_magenta'] = $magenta;
    }
    if ($amarelo !== null) {
        $campos['tinta_amarelo'] = $amarelo;
    }

    return atualizarCamposImpressora($conn, $id, $campos, $colunaUltimaAtualizacao, $colunaUltimaAtualizacao !== null);
}

function inserirHistoricoImpressora(
    mysqli $conn,
    int $impressoraId,
    ?int $paginasTotal,
    ?int $paginasPb,
    ?int $paginasCor,
    ?int $a4PbSimples,
    ?int $a4CorSimples,
    ?int $a4PbDuplex,
    ?int $a4CorDuplex,
    ?int $a3PbSimples,
    ?int $a3CorSimples,
    ?int $a3PbDuplex,
    ?int $a3CorDuplex,
    ?int $tintaPreto,
    ?int $tintaCiano,
    ?int $tintaMagenta,
    ?int $tintaAmarelo,
    string $statusImpressora
): array {
    $sql = "INSERT INTO historico_impressoras (
                impressora_id,
                data_hora,
                paginas_total,
                paginas_pb,
                paginas_cor,
                a4_pb_simples,
                a4_cor_simples,
                a4_pb_duplex,
                a4_cor_duplex,
                a3_pb_simples,
                a3_cor_simples,
                a3_pb_duplex,
                a3_cor_duplex,
                tinta_preto,
                tinta_ciano,
                tinta_magenta,
                tinta_amarelo,
                status_impressora
            ) VALUES (
                ?,
                NOW(),
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?
            )";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'erro' => 'Falha ao preparar insert do historico: ' . $conn->error];
    }

    $tintaPretoTexto = $tintaPreto !== null ? (string) $tintaPreto : null;
    $tintaCianoTexto = $tintaCiano !== null ? (string) $tintaCiano : null;
    $tintaMagentaTexto = $tintaMagenta !== null ? (string) $tintaMagenta : null;
    $tintaAmareloTexto = $tintaAmarelo !== null ? (string) $tintaAmarelo : null;

    $stmt->bind_param(
        'iiiiiiiiiiiisssss',
        $impressoraId,
        $paginasTotal,
        $paginasPb,
        $paginasCor,
        $a4PbSimples,
        $a4CorSimples,
        $a4PbDuplex,
        $a4CorDuplex,
        $a3PbSimples,
        $a3CorSimples,
        $a3PbDuplex,
        $a3CorDuplex,
        $tintaPretoTexto,
        $tintaCianoTexto,
        $tintaMagentaTexto,
        $tintaAmareloTexto,
        $statusImpressora
    );

    $ok = $stmt->execute();
    $erro = $stmt->error;
    $stmt->close();

    return [
        'ok' => (bool) $ok,
        'erro' => $ok ? '' : ('Falha ao inserir historico: ' . $erro),
    ];
}

function inserirHistoricoImpressoraPorSnapshot(mysqli $conn, int $impressoraId, array $snapshot, string $statusHistorico): array
{
    return inserirHistoricoImpressora(
        $conn,
        $impressoraId,
        isset($snapshot['paginas_total']) ? (int) $snapshot['paginas_total'] : null,
        isset($snapshot['paginas_pb']) ? (int) $snapshot['paginas_pb'] : null,
        isset($snapshot['paginas_cor']) ? (int) $snapshot['paginas_cor'] : null,
        isset($snapshot['a4_pb_simples']) ? (int) $snapshot['a4_pb_simples'] : null,
        isset($snapshot['a4_cor_simples']) ? (int) $snapshot['a4_cor_simples'] : null,
        isset($snapshot['a4_pb_duplex']) ? (int) $snapshot['a4_pb_duplex'] : null,
        isset($snapshot['a4_cor_duplex']) ? (int) $snapshot['a4_cor_duplex'] : null,
        isset($snapshot['a3_pb_simples']) ? (int) $snapshot['a3_pb_simples'] : null,
        isset($snapshot['a3_cor_simples']) ? (int) $snapshot['a3_cor_simples'] : null,
        isset($snapshot['a3_pb_duplex']) ? (int) $snapshot['a3_pb_duplex'] : null,
        isset($snapshot['a3_cor_duplex']) ? (int) $snapshot['a3_cor_duplex'] : null,
        isset($snapshot['tinta_preto']) ? (int) $snapshot['tinta_preto'] : null,
        isset($snapshot['tinta_ciano']) ? (int) $snapshot['tinta_ciano'] : null,
        isset($snapshot['tinta_magenta']) ? (int) $snapshot['tinta_magenta'] : null,
        isset($snapshot['tinta_amarelo']) ? (int) $snapshot['tinta_amarelo'] : null,
        $statusHistorico
    );
}

function sincronizacaoStatusFalhaPorTipo(string $tipoFalha): string
{
    switch ($tipoFalha) {
        case 'login':
            return 'Login necessario';
        case 'frameset':
        case 'invalida':
            return 'Resposta invalida';
        case 'ip_vazio':
            return 'offline';
        default:
            return 'offline';
    }
}

function sincronizacaoCombinarMotivos(array $motivos): string
{
    $limpos = [];
    foreach ($motivos as $motivo) {
        $texto = trim((string) $motivo);
        if ($texto !== '') {
            $limpos[] = $texto;
        }
    }

    return implode(' | ', array_values(array_unique($limpos)));
}

function sincronizacaoMontarResultadoBase(int $id, string $nome, string $ip): array
{
    return [
        'id' => $id,
        'nome' => $nome,
        'ip' => $ip,
        'status' => '',
        'preto' => null,
        'ciano' => null,
        'magenta' => null,
        'amarelo' => null,
        'paginas_total' => null,
        'paginas_pb' => null,
        'paginas_cor' => null,
        'a4_pb_simples' => null,
        'a4_cor_simples' => null,
        'a4_pb_duplex' => null,
        'a4_cor_duplex' => null,
        'a3_pb_simples' => null,
        'a3_cor_simples' => null,
        'a3_pb_duplex' => null,
        'a3_cor_duplex' => null,
        'ok' => false,
        'parcial' => false,
        'classificacao' => 'falha',
        'erro' => '',
        'status_lido' => false,
        'tinta_lida' => false,
        'paginas_lidas' => false,
        'a4_lido' => false,
        'a3_lido' => false,
        'protocolo_status_tinta' => '',
        'url_status_tinta' => '',
        'protocolo_uso' => '',
        'url_uso' => '',
        'fallback_http_status_tinta' => false,
        'fallback_http_uso' => false,
        'dados_gravados' => false,
        'campos_gravados' => [],
        'historico_gravado' => false,
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
    $arquivoLog = sincronizacaoDiretorioDebug() . '/sincronizacao_' . sincronizacaoNormalizarNomeArquivo($ip, 'sem_ip_' . ($id > 0 ? $id : '0')) . '.log';
    $logger = sincronizacaoCriarLogger($arquivoLog);
    $resultado = sincronizacaoMontarResultadoBase($id, $nome, $ip);

    $logger(str_repeat('=', 90));
    $logger('Data/Hora: ' . date('Y-m-d H:i:s'));
    $logger('Impressora: ' . ($nome !== '' ? $nome : 'Sem nome'));
    $logger('ID: ' . $id);
    $logger('IP: ' . ($ip !== '' ? $ip : 'Sem IP'));

    if ($id <= 0) {
        $resultado['erro'] = 'ID da impressora invalido.';
        $logger('Falha: ' . $resultado['erro']);
        return $resultado;
    }

    if ($ip === '') {
        $statusFalha = sincronizacaoStatusFalhaPorTipo('ip_vazio');
        $update = atualizarCamposImpressora($conn, $id, ['status_impressora' => $statusFalha], null, false);
        $resultado['status'] = $statusFalha;
        $resultado['erro'] = 'IP vazio.';
        $resultado['dados_gravados'] = !empty($update['ok']);
        $resultado['campos_gravados'] = $update['campos'] ?? [];
        $logger('Status salvo em falha: ' . $statusFalha);
        $logger('Dados gravados no banco: ' . sincronizacaoBoolTexto(!empty($update['ok'])));
        $logger('Historico gravado: NAO');
        return $resultado;
    }

    $coletaStatusTinta = coletarStatusETintaImpressora($ip, $logger);
    $coletaUso = buscarUsoImpressora($ip, $logger);

    $resultado['status_lido'] = (bool) ($coletaStatusTinta['status_lido'] ?? false);
    $resultado['tinta_lida'] = (bool) ($coletaStatusTinta['tinta_lida'] ?? false);
    $resultado['paginas_lidas'] = (bool) ($coletaUso['paginas_lidas'] ?? false);
    $resultado['a4_lido'] = (bool) ($coletaUso['a4_lido'] ?? false);
    $resultado['a3_lido'] = (bool) ($coletaUso['a3_lido'] ?? false);
    $resultado['protocolo_status_tinta'] = (string) (($coletaStatusTinta['meta']['protocolo'] ?? '') ?: '');
    $resultado['url_status_tinta'] = (string) (($coletaStatusTinta['meta']['url'] ?? '') ?: '');
    $resultado['fallback_http_status_tinta'] = !empty($coletaStatusTinta['meta']['fallback_http']);
    $resultado['protocolo_uso'] = (string) (($coletaUso['meta']['protocolo'] ?? '') ?: '');
    $resultado['url_uso'] = (string) (($coletaUso['meta']['url'] ?? '') ?: '');
    $resultado['fallback_http_uso'] = !empty($coletaUso['meta']['fallback_http']);

    $camposParaPersistir = [];
    if (!empty($coletaStatusTinta['status_lido']) && !empty($coletaStatusTinta['status'])) {
        $camposParaPersistir['status_impressora'] = (string) $coletaStatusTinta['status'];
    }

    foreach (['tinta_preto', 'tinta_ciano', 'tinta_magenta', 'tinta_amarelo'] as $campoTinta) {
        if (isset($coletaStatusTinta[$campoTinta]) && $coletaStatusTinta[$campoTinta] !== null) {
            $camposParaPersistir[$campoTinta] = (int) $coletaStatusTinta[$campoTinta];
        }
    }

    $mapaUsoParaBanco = [
        'total' => 'paginas_total',
        'pb' => 'paginas_pb',
        'cor' => 'paginas_cor',
        'a4_pb_simples' => 'a4_pb_simples',
        'a4_cor_simples' => 'a4_cor_simples',
        'a4_pb_duplex' => 'a4_pb_duplex',
        'a4_cor_duplex' => 'a4_cor_duplex',
        'a3_pb_simples' => 'a3_pb_simples',
        'a3_cor_simples' => 'a3_cor_simples',
        'a3_pb_duplex' => 'a3_pb_duplex',
        'a3_cor_duplex' => 'a3_cor_duplex',
    ];

    foreach ($mapaUsoParaBanco as $campoOrigem => $campoBanco) {
        if (isset($coletaUso['dados'][$campoOrigem]) && $coletaUso['dados'][$campoOrigem] !== null) {
            $camposParaPersistir[$campoBanco] = (int) $coletaUso['dados'][$campoOrigem];
        }
    }

    $blocoStatusTinta = (string) ($coletaStatusTinta['classificacao'] ?? 'falha');
    $blocoUso = (string) ($coletaUso['classificacao'] ?? 'falha');
    $classificacaoFinal = 'falha';
    if ($blocoStatusTinta === 'sucesso' && $blocoUso === 'sucesso') {
        $classificacaoFinal = 'sucesso';
    } elseif ($blocoStatusTinta !== 'falha' || $blocoUso !== 'falha') {
        $classificacaoFinal = 'parcial';
    }

    $logger('Resumo leitura status/tinta: ' . $blocoStatusTinta);
    $logger('Resumo leitura uso: ' . $blocoUso);
    $logger('Fallback HTTP status/tinta: ' . sincronizacaoBoolTexto($resultado['fallback_http_status_tinta']));
    $logger('Fallback HTTP uso: ' . sincronizacaoBoolTexto($resultado['fallback_http_uso']));

    if ($classificacaoFinal === 'falha') {
        $tipoFalhaPrincipal = (string) (($coletaStatusTinta['meta']['tipo_falha'] ?? '') ?: ($coletaUso['meta']['tipo_falha'] ?? 'desconhecida'));
        $statusFalha = sincronizacaoStatusFalhaPorTipo($tipoFalhaPrincipal);
        $updateFalha = atualizarCamposImpressora($conn, $id, ['status_impressora' => $statusFalha], null, false);

        $resultado['status'] = $statusFalha;
        $resultado['erro'] = sincronizacaoCombinarMotivos([
            $coletaStatusTinta['motivo'] ?? '',
            $coletaUso['motivo'] ?? '',
            $coletaStatusTinta['meta']['motivo_falha'] ?? '',
            $coletaUso['meta']['motivo_falha'] ?? '',
        ]);
        $resultado['dados_gravados'] = !empty($updateFalha['ok']);
        $resultado['campos_gravados'] = $updateFalha['campos'] ?? [];

        $logger('Classificacao final: FALHA');
        $logger('Status salvo em falha: ' . $statusFalha);
        $logger('Dados gravados no banco: ' . sincronizacaoBoolTexto(!empty($updateFalha['ok'])));
        $logger('Historico gravado: NAO');
        $logger('Motivo da falha: ' . $resultado['erro']);
        return $resultado;
    }

    $atualizarUltimaAtualizacao = $classificacaoFinal === 'sucesso';
    $update = atualizarCamposImpressora($conn, $id, $camposParaPersistir, $colunaUltimaAtualizacao, $atualizarUltimaAtualizacao);
    if (empty($update['ok'])) {
        $resultado['erro'] = (string) ($update['erro'] ?? 'Falha ao atualizar dados da impressora.');
        $logger('Falha ao gravar no banco: ' . $resultado['erro']);
        return $resultado;
    }

    $snapshot = buscarSnapshotImpressora($conn, $id);
    if ($snapshot === null) {
        $resultado['erro'] = 'Falha ao recarregar snapshot da impressora apos update.';
        $logger($resultado['erro']);
        return $resultado;
    }

    $resultado['status'] = (string) ($snapshot['status_impressora'] ?? '');
    $resultado['preto'] = $snapshot['tinta_preto'] ?? null;
    $resultado['ciano'] = $snapshot['tinta_ciano'] ?? null;
    $resultado['magenta'] = $snapshot['tinta_magenta'] ?? null;
    $resultado['amarelo'] = $snapshot['tinta_amarelo'] ?? null;
    $resultado['paginas_total'] = $snapshot['paginas_total'] ?? null;
    $resultado['paginas_pb'] = $snapshot['paginas_pb'] ?? null;
    $resultado['paginas_cor'] = $snapshot['paginas_cor'] ?? null;
    $resultado['a4_pb_simples'] = $snapshot['a4_pb_simples'] ?? null;
    $resultado['a4_cor_simples'] = $snapshot['a4_cor_simples'] ?? null;
    $resultado['a4_pb_duplex'] = $snapshot['a4_pb_duplex'] ?? null;
    $resultado['a4_cor_duplex'] = $snapshot['a4_cor_duplex'] ?? null;
    $resultado['a3_pb_simples'] = $snapshot['a3_pb_simples'] ?? null;
    $resultado['a3_cor_simples'] = $snapshot['a3_cor_simples'] ?? null;
    $resultado['a3_pb_duplex'] = $snapshot['a3_pb_duplex'] ?? null;
    $resultado['a3_cor_duplex'] = $snapshot['a3_cor_duplex'] ?? null;
    $resultado['dados_gravados'] = true;
    $resultado['campos_gravados'] = $update['campos'] ?? [];

    $statusHistorico = (string) ($snapshot['status_impressora'] ?? 'Sem status');
    if ($classificacaoFinal === 'parcial') {
        $statusHistorico = 'PARCIAL: ' . $statusHistorico;
        if (function_exists('mb_substr')) {
            $statusHistorico = mb_substr($statusHistorico, 0, 30);
        } else {
            $statusHistorico = substr($statusHistorico, 0, 30);
        }
    }

    $historico = inserirHistoricoImpressoraPorSnapshot($conn, $id, $snapshot, $statusHistorico);
    $resultado['historico_gravado'] = !empty($historico['ok']);

    $motivos = [
        $coletaStatusTinta['motivo'] ?? '',
        $coletaUso['motivo'] ?? '',
    ];

    if (empty($historico['ok'])) {
        $motivos[] = $historico['erro'] ?? 'Falha ao inserir historico.';
        $classificacaoFinal = 'parcial';
    }

    $resultado['classificacao'] = $classificacaoFinal;
    $resultado['ok'] = $classificacaoFinal === 'sucesso';
    $resultado['parcial'] = $classificacaoFinal === 'parcial';
    $resultado['erro'] = $classificacaoFinal === 'sucesso' ? '' : sincronizacaoCombinarMotivos($motivos);

    $logger('Classificacao final: ' . strtoupper($resultado['classificacao']));
    $logger('Status foi lido: ' . sincronizacaoBoolTexto($resultado['status_lido']));
    $logger('Tintas foram lidas: ' . sincronizacaoBoolTexto($resultado['tinta_lida']));
    $logger('Paginas foram lidas: ' . sincronizacaoBoolTexto($resultado['paginas_lidas']));
    $logger('A4 foi lido: ' . sincronizacaoBoolTexto($resultado['a4_lido']));
    $logger('A3 foi lido: ' . sincronizacaoBoolTexto($resultado['a3_lido']));
    $logger('Campos gravados no banco: ' . (!empty($resultado['campos_gravados']) ? implode(', ', $resultado['campos_gravados']) : 'nenhum'));
    $logger('ultima_atualizacao atualizada: ' . sincronizacaoBoolTexto($atualizarUltimaAtualizacao));
    $logger('Historico gravado: ' . sincronizacaoBoolTexto($resultado['historico_gravado']));
    if ($resultado['erro'] !== '') {
        $logger('Motivo final: ' . $resultado['erro']);
    }

    return $resultado;
}

