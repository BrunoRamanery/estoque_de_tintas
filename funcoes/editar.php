<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../servicos/tintas_servico.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

$csrfToken = obter_token_csrf();
$errors = [];
$retornoModelo = trim((string) ($_GET['retorno_modelo'] ?? $_POST['retorno_modelo'] ?? ''));

$montarRedirecionamento = static function (string $modelo): string {
    return $modelo !== ''
        ? '../detalhes.php?modelo=' . urlencode($modelo)
        : '../index.php';
};

$voltarUrl = $montarRedirecionamento($retornoModelo);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    definir_mensagem_flash('erro', 'ID invalido para edicao.');
    $conn->close();
    header('Location: ' . $voltarUrl);
    exit;
}

try {
    $registro = buscar_tinta_por_id($conn, $id);
} catch (RuntimeException $erro) {
    error_log('Falha ao carregar registro para edicao ID ' . $id . ': ' . $erro->getMessage());
    definir_mensagem_flash('erro', 'Nao foi possivel carregar o registro para edicao.');
    $conn->close();
    header('Location: ' . $voltarUrl);
    exit;
}

if (!$registro) {
    definir_mensagem_flash('erro', 'Registro nao encontrado.');
    $conn->close();
    header('Location: ' . $voltarUrl);
    exit;
}

$formData = dados_tinta_da_fonte($registro);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

    $formData = dados_tinta_da_fonte($_POST);
    try {
        $processamentoAtualizacao = servico_tintas_processar_atualizacao($conn, $id, $formData);
    } catch (RuntimeException $erro) {
        error_log('Falha ao editar tinta ID ' . $id . ': ' . $erro->getMessage());
        definir_mensagem_flash('erro', 'Erro interno ao atualizar o registro.');
        header('Location: ' . $montarRedirecionamento($retornoModelo));
        exit;
    }

    $errors = $processamentoAtualizacao['errors'];

    if (empty($errors)) {
        $resultadoAtualizacao = (array) ($processamentoAtualizacao['resultado'] ?? []);
        $atualizou = (bool) ($resultadoAtualizacao['ok'] ?? false);
        $mensagemSucesso = (($resultadoAtualizacao['acao'] ?? '') === 'mesclado')
            ? 'Registro atualizado e consolidado com outro ja existente.'
            : 'Registro atualizado com sucesso.';
        definir_mensagem_flash($atualizou ? 'sucesso' : 'erro', $atualizou ? $mensagemSucesso : 'Erro ao atualizar o registro.');
        $redirectModelo = $retornoModelo !== '' ? trim((string) $formData['modelo']) : '';
        header('Location: ' . $montarRedirecionamento($redirectModelo));
        exit;
    }
}

$conn->close();
$tituloPagina = 'Editar Tinta';
$caminhoCss = '../css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "../";
        $paginaAtual = "tintas";
        $paginaTitulo = "Editar tinta";
        $paginaDescricao = "Atualize os dados do registro selecionado";
        require __DIR__ . "/../includes/topo_sistema.php";
    ?>
    <div class="container form-container">
        <div class="form-topo">
            <h1><i class="fa-solid fa-pen-to-square"></i> Editar Tinta</h1>
            <p class="subtitulo">Atualize os dados da tinta selecionada.</p>
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
            <input type="hidden" name="retorno_modelo" value="<?= e($retornoModelo) ?>">

            <div class="campo">
                <label for="impressora"><i class="fa-solid fa-print"></i> Impressora</label>
                <input type="text" name="impressora" id="impressora" value="<?= e($formData['impressora']) ?>" required maxlength="100">
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
                    <i class="fa-solid fa-floppy-disk"></i> Atualizar
                </button>

                <a href="<?= e($voltarUrl) ?>" class="btn-voltar">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
            </div>
        </form>
    </div>
    </div>
</div>
</body>
</html>
