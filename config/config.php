<?php
/**
 * Configuracoes do sistema.
 *
 * Em producao, prefira configurar variaveis de ambiente.
 */
$appEnv = (string) (getenv('APP_ENV') !== false ? getenv('APP_ENV') : 'local');
$appEnv = strtolower(trim($appEnv));
$ambienteLocal = in_array($appEnv, ['local', 'development', 'dev'], true);

$dbHostEnv = getenv('DB_HOST');
$dbNameEnv = getenv('DB_NAME');
$dbUserEnv = getenv('DB_USER');
$dbPassEnv = getenv('DB_PASS');

return [
    'app_env' => $appEnv,
    'db_host' => ($dbHostEnv !== false && trim((string) $dbHostEnv) !== '') ? (string) $dbHostEnv : ($ambienteLocal ? 'localhost' : ''),
    'db_name' => ($dbNameEnv !== false && trim((string) $dbNameEnv) !== '') ? (string) $dbNameEnv : ($ambienteLocal ? 'tintas' : ''),
    'db_user' => ($dbUserEnv !== false && trim((string) $dbUserEnv) !== '') ? (string) $dbUserEnv : ($ambienteLocal ? 'root' : ''),
    'db_pass' => $dbPassEnv !== false ? (string) $dbPassEnv : ($ambienteLocal ? '' : ''),
];
