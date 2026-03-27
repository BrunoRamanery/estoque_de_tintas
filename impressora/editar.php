<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../servicos/impressoras_servico.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

$csrfToken = obter_token_csrf();
$mensagem = obter_mensagem_flash();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$buscaRetorno = trim((string) ($_GET['busca'] ?? ''));

$redirecionarListagem = static function (string $busca): void {
    $url = 'impressoras.php';
    if ($busca !== '') {
        $url .= '?' . http_build_query(['busca' => $busca]);
    }

    header('Location: ' . $url);
    exit;
};

if ($id === false || $id === null || $id <= 0) {
    $conn->close();
    definir_mensagem_flash('erro', 'ID de impressora invalido.');
    $redirecionarListagem($buscaRetorno);
}

try {
    $impressora = servico_impressoras_buscar_detalhes($conn, (int) $id);
} catch (RuntimeException $erro) {
    error_log('Falha ao carregar impressora para edicao ID ' . $id . ': ' . $erro->getMessage());
    $conn->close();
    definir_mensagem_flash('erro', 'Nao foi possivel carregar a impressora para edicao.');
    $redirecionarListagem($buscaRetorno);
}

$conn->close();

if (!$impressora) {
    definir_mensagem_flash('erro', 'Impressora nao encontrada.');
    $redirecionarListagem($buscaRetorno);
}

$chaveFormOld = 'impressora_form_old_editar_' . (int) $id;
$formData = [
    'nome' => (string) ($impressora['nome'] ?? ''),
    'modelo' => (string) ($impressora['modelo'] ?? ''),
    'ip' => (string) ($impressora['ip'] ?? ''),
    'localizacao' => (string) ($impressora['localizacao'] ?? ''),
    'observacao' => (string) ($impressora['observacao'] ?? ''),
];

if (isset($_SESSION[$chaveFormOld]) && is_array($_SESSION[$chaveFormOld])) {
    $formData = array_merge($formData, $_SESSION[$chaveFormOld]);
}
unset($_SESSION[$chaveFormOld]);

$voltarUrl = 'detalhes.php?id=' . (int) $id;
if ($buscaRetorno !== '') {
    $voltarUrl .= '&' . http_build_query(['busca' => $buscaRetorno]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Impressora</title>
    <link rel="stylesheet" href="../css/principal.css">
</head>
<body class="tela-formulario">
<div class="container form-container">
    <div class="form-topo">
        <h1>Editar Impressora</h1>
        <p class="subtitulo">Atualize os dados da impressora selecionada.</p>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alerta alerta-<?= e($mensagem['tipo']) ?>">
            <span><?= e($mensagem['texto']) ?></span>
        </div>
    <?php endif; ?>

    <form action="atualizar.php" method="POST" class="formulario-grid">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= e((int) $id) ?>">
        <input type="hidden" name="busca" value="<?= e($buscaRetorno) ?>">

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
            <button type="submit" class="btn-salvar">Atualizar</button>
            <a href="<?= e($voltarUrl) ?>" class="btn-voltar">Voltar</a>
        </div>
    </form>
</div>
</body>
</html>
