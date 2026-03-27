<?php
/**
 * Configuracoes do sistema.
 *
 * Em producao, prefira configurar variaveis de ambiente.
 */
return [
    'db_host' => getenv('DB_HOST') !== false ? (string) getenv('DB_HOST') : 'localhost',
    'db_name' => getenv('DB_NAME') !== false ? (string) getenv('DB_NAME') : 'tintas',
    'db_user' => getenv('DB_USER') !== false ? (string) getenv('DB_USER') : 'root',
    'db_pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
];

