<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../servicos/tintas_servico.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

$csrfToken = obter_token_csrf();
$errors = [];
$formData = dados_vazios_tinta();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

    $formData = dados_tinta_da_fonte($_POST);
    try {
        $processamentoCadastro = servico_tintas_processar_cadastro($conn, $formData);
    } catch (RuntimeException $erro) {
        error_log('Falha ao cadastrar tinta: ' . $erro->getMessage());
        definir_mensagem_flash('erro', 'Erro interno ao cadastrar a tinta.');
        header('Location: ../index.php');
        exit;
    }

    $errors = $processamentoCadastro['errors'];

    if (empty($errors)) {
        $resultadoCadastro = (array) ($processamentoCadastro['resultado'] ?? []);
        $salvou = (bool) ($resultadoCadastro['ok'] ?? false);
        $mensagemSucesso = (($resultadoCadastro['acao'] ?? '') === 'mesclado')
            ? 'Tinta adicionada e somada ao registro ja existente.'
            : 'Tinta cadastrada com sucesso.';
        definir_mensagem_flash($salvou ? 'sucesso' : 'erro', $salvou ? $mensagemSucesso : 'Erro ao salvar a tinta.');
        header('Location: ../index.php');
        exit;
    }
}

$conn->close();
$tituloPagina = 'Nova Tinta';
$caminhoCss = '../css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<body class="tela-formulario">
    <div class="container form-container">
        <div class="form-topo">
            <h1><i class="fa-solid fa-circle-plus"></i> Nova Tinta</h1>
            <p class="subtitulo">Preencha os dados abaixo para cadastrar uma nova tinta no estoque.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <ul class="erros">
                <?php foreach ($errors as $error): ?>
                    <li><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" class="formulario-grid">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="campo">
                <label for="impressora"><i class="fa-solid fa-print"></i> Impressora</label>
                <input type="text" name="impressora" id="impressora" value="<?= e($formData['impressora']) ?>" required maxlength="100" placeholder="Digite o nome da impressora">
            </div>

            <div class="campo">
                <label><i class="fa-solid fa-box"></i> Modelo</label>
                <input name="modelo" value="<?= e($formData['modelo']) ?>" required maxlength="100">
            </div>

            <div class="campo">
                <label><i class="fa-solid fa-palette"></i> Cor</label>
                <input name="cor" value="<?= e($formData['cor']) ?>" required maxlength="30">
            </div>

            <div class="campo">
                <label><i class="fa-solid fa-layer-group"></i> Quantidade</label>
                <input type="number" min="0" max="9999" step="1" name="quantidade" value="<?= e($formData['quantidade_raw']) ?>" required>
            </div>

            <div class="campo">
                <label><i class="fa-solid fa-calendar-days"></i> Mes</label>
                <input type="number" min="1" max="12" name="mes" value="<?= e($formData['mes_raw']) ?>" required>
            </div>

            <div class="campo">
                <label><i class="fa-solid fa-calendar"></i> Ano</label>
                <input type="number" min="2000" max="2100" name="ano" value="<?= e($formData['ano_raw']) ?>" required>
            </div>

            <div class="form-acoes-grid">
                <button type="submit" class="btn-salvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>

                <a href="../index.php" class="btn-voltar">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
            </div>
        </form>
    </div>
</body>
</html>
