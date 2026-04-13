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
<?php
$tituloPagina = 'Editar Impressora';
$caminhoCss = "../css/principal.css";
?>
<?php require __DIR__ . "/../includes/cabecalho.php"; ?>
<body class="tela-sistema">
<?php
    $basePrefix = "../";
    $paginaAtual = "impressoras";
    $paginaTitulo = "Editar impressora";
    $paginaDescricao = "Atualize os dados da impressora selecionada";
    require __DIR__ . "/../includes/topo_sistema.php";
?>
<div class="container form-container">
    <section class="pagina-hero">
        <div class="pagina-hero__conteudo">
            <span class="pagina-hero__eyebrow">
                <i class="fa-solid fa-pen-to-square"></i>
                Ajuste visual do cadastro
            </span>
            <h1>Editar Impressora</h1>
            <p>Atualize os dados cadastrais da impressora mantendo exatamente a mesma logica do sistema. Esta camada muda apenas a organizacao visual do formulario.</p>

            <div class="pagina-hero__chips">
                <span class="pagina-hero__chip">
                    <i class="fa-solid fa-print"></i>
                    <?= e($formData['nome'] !== '' ? $formData['nome'] : 'Impressora selecionada') ?>
                </span>
                <span class="pagina-hero__chip">
                    <i class="fa-solid fa-network-wired"></i>
                    <?= e($formData['ip'] !== '' ? $formData['ip'] : 'IP nao informado') ?>
                </span>
            </div>
        </div>

        <aside class="pagina-hero__painel">
            <span class="pagina-hero__rotulo">Resumo atual</span>
            <strong><?= e($formData['modelo'] !== '' ? $formData['modelo'] : 'Sem modelo') ?></strong>
            <small>Os dados continuam sendo gravados pelo mesmo endpoint e com a mesma validacao ja existente.</small>

            <div class="pagina-hero__metricas">
                <div class="pagina-hero__metrica">
                    <span>Localizacao</span>
                    <strong><?= e($formData['localizacao'] !== '' ? $formData['localizacao'] : 'Nao informada') ?></strong>
                </div>
                <div class="pagina-hero__metrica">
                    <span>Retorno</span>
                    <strong>Detalhes da impressora</strong>
                </div>
            </div>
        </aside>
    </section>

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
            <label for="nome"><i class="fa-solid fa-print"></i> Nome</label>
            <input id="nome" type="text" name="nome" value="<?= e($formData['nome']) ?>" required maxlength="100">
        </div>

        <div class="campo">
            <label for="modelo"><i class="fa-solid fa-layer-group"></i> Modelo</label>
            <input id="modelo" type="text" name="modelo" value="<?= e($formData['modelo']) ?>" required maxlength="100">
        </div>

        <div class="campo">
            <label for="ip"><i class="fa-solid fa-network-wired"></i> IP</label>
            <input id="ip" type="text" name="ip" value="<?= e($formData['ip']) ?>" required maxlength="45">
        </div>

        <div class="campo">
            <label for="localizacao"><i class="fa-solid fa-location-dot"></i> Localizacao</label>
            <input id="localizacao" type="text" name="localizacao" value="<?= e($formData['localizacao']) ?>" maxlength="120">
        </div>

        <div class="campo">
            <label for="observacao"><i class="fa-solid fa-note-sticky"></i> Observacao</label>
            <input id="observacao" type="text" name="observacao" value="<?= e($formData['observacao']) ?>" maxlength="255">
        </div>

        <div class="form-acoes-grid">
            <button type="submit" class="btn-salvar">
                <i class="fa-solid fa-floppy-disk"></i>
                Atualizar
            </button>
            <a href="<?= e($voltarUrl) ?>" class="btn-voltar">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar
            </a>
        </div>
    </form>
</div>
    </div>
</div>
</body>
</html>
