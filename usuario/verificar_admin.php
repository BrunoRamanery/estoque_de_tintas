<?php
require_once __DIR__ . '/verificar_login.php';

if (!usuario_e_admin()) {
    definir_mensagem_flash('erro', 'Acesso restrito para administradores.');
    header('Location: ../index.php');
    exit;
}
