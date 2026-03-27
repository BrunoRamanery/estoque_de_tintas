<?php
require_once __DIR__ . '/../app/utilidades.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para logout.');
    header('Location: login.php');
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
