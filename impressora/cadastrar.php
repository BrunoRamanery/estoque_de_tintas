<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

$csrfToken = obter_token_csrf();
$mensagem = obter_mensagem_flash();

$formData = [
    'nome' => '',
    'modelo' => '',
    'ip' => '',
    'localizacao' => '',
    'observacao' => '',
];

if (isset($_SESSION['impressora_form_old']) && is_array($_SESSION['impressora_form_old'])) {
    $formData = array_merge($formData, $_SESSION['impressora_form_old']);
}
unset($_SESSION['impressora_form_old']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Impressora</title>
    <link rel="stylesheet" href="../css/principal.css">
</head>
<body class="tela-formulario">
<div class="container form-container">
    <div class="form-topo">
        <h1>Cadastrar Impressora</h1>
        <p class="subtitulo">Preencha os dados para registrar uma nova impressora.</p>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alerta alerta-<?= e($mensagem['tipo']) ?>">
            <span><?= e($mensagem['texto']) ?></span>
        </div>
    <?php endif; ?>

    <form action="salvar_impressora.php" method="POST" class="formulario-grid">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="campo">
            <label for="nome">Nome</label>
            <input id="nome" type="text" name="nome" value="<?= e($formData['nome']) ?>" required maxlength="100">
        </div>

        <div class="campo">
            <label for="modelo">Modelo</label>
            <input id="modelo" type="text" name="modelo" value="<?= e($formData['modelo']) ?>" required maxlength="100">
        </div>

        <div class="campo">
            <label for="ip">IP</label>
            <input id="ip" type="text" name="ip" value="<?= e($formData['ip']) ?>" required maxlength="45">
        </div>

        <div class="campo">
            <label for="localizacao">Localizacao</label>
            <input id="localizacao" type="text" name="localizacao" value="<?= e($formData['localizacao']) ?>" maxlength="120">
        </div>

        <div class="campo">
            <label for="observacao">Observacao</label>
            <input id="observacao" type="text" name="observacao" value="<?= e($formData['observacao']) ?>" maxlength="255">
        </div>

        <div class="form-acoes-grid">
            <button type="submit" class="btn-salvar">Salvar</button>
            <a href="impressoras.php" class="btn-voltar">Voltar</a>
        </div>
    </form>
</div>
</body>
</html>
