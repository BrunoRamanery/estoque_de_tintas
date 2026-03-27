<?php
require_once __DIR__ . '/../app/utilidades.php';

if (!usuario_esta_logado()) {
    $documentRoot = str_replace('\\', '/', (string) realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')));
    $projetoRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $basePath = '';

    if ($documentRoot !== '' && $projetoRoot !== '' && str_starts_with($projetoRoot, $documentRoot)) {
        $basePath = substr($projetoRoot, strlen($documentRoot));
    }

    if ($basePath === '' || $basePath === false) {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim(dirname($scriptName), '/');

        if (str_ends_with($basePath, '/usuario')) {
            $basePath = substr($basePath, 0, -8);
        }
    }

    $loginPath = rtrim((string) $basePath, '/') . '/usuario/login.php';
    header('Location: ' . $loginPath);
    exit;
}
