<?php
require_once __DIR__ . '/../app/utilidades.php';

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
