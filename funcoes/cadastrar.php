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
        header('Location: ../tintas.php');
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
        header('Location: ../tintas.php');
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
<body class="tela-sistema">
    <?php
        $basePrefix = "../";
        $paginaAtual = "tintas";
        $paginaTitulo = "Nova tinta";
        $paginaDescricao = "Cadastre um novo item no estoque";
        require __DIR__ . "/../includes/topo_sistema.php";
    ?>
    <div class="container form-container">
        <section class="pagina-hero">
            <div class="pagina-hero__conteudo">
                <span class="pagina-hero__eyebrow">
                    <i class="fa-solid fa-circle-plus"></i>
                    Novo item de estoque
                </span>
                <h1>Nova Tinta</h1>
                <p>Cadastre uma nova tinta mantendo o mesmo fluxo de gravacao ja existente. Esta tela foi reorganizada para destacar melhor os campos e reduzir ruído visual.</p>

                <div class="pagina-hero__chips">
                    <span class="pagina-hero__chip">
                        <i class="fa-solid fa-print"></i>
                        Vinculo com impressora
                    </span>
                    <span class="pagina-hero__chip">
                        <i class="fa-solid fa-calendar-days"></i>
                        Controle por validade
                    </span>
                </div>
            </div>

            <aside class="pagina-hero__painel">
                <span class="pagina-hero__rotulo">Fluxo atual</span>
                <strong>Cadastro sem alterar logica</strong>
                <small>As validacoes, mesclagem de registros e operacoes no banco seguem exatamente como ja funcionavam.</small>

                <div class="pagina-hero__metricas">
                    <div class="pagina-hero__metrica">
                        <span>Persistencia</span>
                        <strong>Mesmo endpoint</strong>
                    </div>
                    <div class="pagina-hero__metrica">
                        <span>Integridade</span>
                        <strong>Sem impacto</strong>
                    </div>
                </div>
            </aside>
        </section>

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

                <a href="../tintas.php" class="btn-voltar">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
            </div>
        </form>
    </div>
    </div>
</div>
</body>
</html>
