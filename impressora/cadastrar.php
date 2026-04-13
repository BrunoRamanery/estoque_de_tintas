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
<?php
$tituloPagina = 'Cadastrar Impressora';
$caminhoCss = "../css/principal.css";
?>
<?php require __DIR__ . "/../includes/cabecalho.php"; ?>
<body class="tela-sistema">
<?php
    $basePrefix = "../";
    $paginaAtual = "impressoras";
    $paginaTitulo = "Cadastrar impressora";
    $paginaDescricao = "Adicione uma nova impressora ao sistema";
    require __DIR__ . "/../includes/topo_sistema.php";
?>
<div class="container form-container">
    <section class="pagina-hero">
        <div class="pagina-hero__conteudo">
            <span class="pagina-hero__eyebrow">
                <i class="fa-solid fa-print"></i>
                Novo equipamento
            </span>
            <h1>Cadastrar Impressora</h1>
            <p>Adicione uma nova impressora ao parque sem alterar o fluxo atual de cadastro. A tela foi reorganizada para facilitar leitura, preenchimento e validacao visual.</p>

            <div class="pagina-hero__chips">
                <span class="pagina-hero__chip">
                    <i class="fa-solid fa-network-wired"></i>
                    Cadastro com IP e localizacao
                </span>
                <span class="pagina-hero__chip">
                    <i class="fa-solid fa-circle-info"></i>
                    Observacoes opcionais
                </span>
            </div>
        </div>

        <aside class="pagina-hero__painel">
            <span class="pagina-hero__rotulo">Checklist</span>
            <strong>Preencha os dados basicos</strong>
            <small>Nome, modelo e IP continuam obrigatorios. O restante permanece com o mesmo comportamento do sistema atual.</small>

            <div class="pagina-hero__metricas">
                <div class="pagina-hero__metrica">
                    <span>Sincronizacao</span>
                    <strong>Sem impacto</strong>
                </div>
                <div class="pagina-hero__metrica">
                    <span>Banco de dados</span>
                    <strong>Mesma estrutura</strong>
                </div>
            </div>
        </aside>
    </section>

    <?php if (!empty($mensagem)): ?>
        <div class="alerta alerta-<?= e($mensagem['tipo']) ?>">
            <span><?= e($mensagem['texto']) ?></span>
        </div>
    <?php endif; ?>

    <form action="salvar_impressora.php" method="POST" class="formulario-grid">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="campo">
            <label for="nome"><i class="fa-solid fa-print"></i> Nome</label>
            <input id="nome" type="text" name="nome" value="<?= e($formData['nome']) ?>" required maxlength="100" placeholder="Ex.: Epson L15150 Fiscal">
        </div>

        <div class="campo">
            <label for="modelo"><i class="fa-solid fa-layer-group"></i> Modelo</label>
            <input id="modelo" type="text" name="modelo" value="<?= e($formData['modelo']) ?>" required maxlength="100" placeholder="Ex.: Epson L15150">
        </div>

        <div class="campo">
            <label for="ip"><i class="fa-solid fa-network-wired"></i> IP</label>
            <input id="ip" type="text" name="ip" value="<?= e($formData['ip']) ?>" required maxlength="45" placeholder="Ex.: 192.168.7.249">
        </div>

        <div class="campo">
            <label for="localizacao"><i class="fa-solid fa-location-dot"></i> Localizacao</label>
            <input id="localizacao" type="text" name="localizacao" value="<?= e($formData['localizacao']) ?>" maxlength="120" placeholder="Setor, sala ou unidade">
        </div>

        <div class="campo">
            <label for="observacao"><i class="fa-solid fa-note-sticky"></i> Observacao</label>
            <input id="observacao" type="text" name="observacao" value="<?= e($formData['observacao']) ?>" maxlength="255" placeholder="Informacao complementar da impressora">
        </div>

        <div class="form-acoes-grid">
            <button type="submit" class="btn-salvar">
                <i class="fa-solid fa-floppy-disk"></i>
                Salvar
            </button>
            <a href="impressoras.php" class="btn-voltar">
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
