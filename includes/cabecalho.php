<?php
/**
 * Include simples para o <head> das paginas.
 * Espera receber a variavel $tituloPagina antes do include.
 */

$hrefCss = (string) ($caminhoCss ?? 'css/principal.css');
$resolverCaminhoCss = static function (string $hrefArquivo): ?string {
    if (preg_match('/^https?:\/\//i', $hrefArquivo)) {
        return null;
    }

    $caminhoRelativo = str_replace('\\', '/', $hrefArquivo);
    $caminhoSemQuery = explode('?', $caminhoRelativo, 2)[0];

    while (str_starts_with($caminhoSemQuery, '../')) {
        $caminhoSemQuery = substr($caminhoSemQuery, 3);
    }

    if (str_starts_with($caminhoSemQuery, './')) {
        $caminhoSemQuery = substr($caminhoSemQuery, 2);
    }

    $caminhoNormalizado = str_replace('/', DIRECTORY_SEPARATOR, $caminhoSemQuery);
    $absoluto = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $caminhoNormalizado);
    return ($absoluto !== false && is_file($absoluto)) ? $absoluto : null;
};

$versionarHrefCss = static function (string $hrefArquivo) use ($resolverCaminhoCss): string {
    $absoluto = $resolverCaminhoCss($hrefArquivo);
    if ($absoluto === null) {
        return $hrefArquivo;
    }

    $separador = str_contains($hrefArquivo, '?') ? '&' : '?';
    return $hrefArquivo . $separador . 'v=' . (string) filemtime($absoluto);
};

$folhasCss = [$hrefCss];

if (preg_match('/(^|[\/\\\\])principal\.css$/i', $hrefCss)) {
    $baseCss = str_replace('\\', '/', dirname($hrefCss));
    $prefixo = ($baseCss === '.' || $baseCss === '') ? '' : rtrim($baseCss, '/') . '/';

    $folhasCss = [
        $prefixo . 'base/reiniciar.css',
        $prefixo . 'base/estrutura.css',
        $prefixo . 'componentes/topo.css',
        $prefixo . 'componentes/topo_sistema.css',
        $prefixo . 'componentes/botoes.css',
        $prefixo . 'componentes/alertas.css',
        $prefixo . 'componentes/filtros.css',
        $prefixo . 'componentes/cards.css',
        $prefixo . 'componentes/tabelas.css',
        $prefixo . 'componentes/cores.css',
        $prefixo . 'componentes/formularios.css',
        $prefixo . 'componentes/status.css',
        $prefixo . 'paginas/dashboard.css',
        $prefixo . 'paginas/impressoras.css',
        $prefixo . 'paginas/detalhes.css',
        $prefixo . 'paginas/responsivo.css',
        $prefixo . 'paginas/autenticacao.css',
    ];
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina ?? 'Controle de Tintas') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <?php foreach ($folhasCss as $folhaCss): ?>
        <link rel="stylesheet" href="<?= e($versionarHrefCss($folhaCss)) ?>">
    <?php endforeach; ?>
</head>
