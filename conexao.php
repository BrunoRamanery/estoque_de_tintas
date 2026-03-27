<?php
$configPath = __DIR__ . '/config/config.php';

if (!is_file($configPath)) {
    error_log('Arquivo de configuracao ausente: ' . $configPath);
    http_response_code(500);
    exit('Nao foi possivel iniciar o sistema no momento.');
}

$config = require $configPath;

$host = trim((string) ($config['db_host'] ?? ''));
$user = trim((string) ($config['db_user'] ?? ''));
$pass = (string) ($config['db_pass'] ?? '');
$db = trim((string) ($config['db_name'] ?? ''));

if ($host === '' || $user === '' || $db === '') {
    error_log('Configuracao de banco incompleta em config/config.php.');
    http_response_code(500);
    exit('Nao foi possivel iniciar o sistema no momento.');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log('Erro ao conectar no banco: ' . $conn->connect_error);
    http_response_code(500);
    exit('Nao foi possivel conectar ao banco no momento.');
}

$conn->set_charset('utf8mb4');
