<?php
require_once __DIR__ . '/../app/utilidades.php';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuario';
$usuarioNivel = $_SESSION['usuario_nivel'] ?? 'comum';
$usuarioNivelLabel = $usuarioNivel === 'admin' ? 'Administrador' : 'Usuario';
$basePrefix = (string) ($basePrefix ?? '');
$paginaAtual = (string) ($paginaAtual ?? 'home');
$paginaTitulo = (string) ($paginaTitulo ?? ($tituloPagina ?? 'Sistema'));
$paginaDescricao = (string) ($paginaDescricao ?? 'Painel principal do sistema');
$csrfToken = obter_token_csrf();
$logoutAction = $basePrefix . 'usuario/logout.php';

$itensMenu = [
    [
        'chave' => 'home',
        'label' => 'Home',
        'icone' => 'fa-solid fa-house',
        'href' => $basePrefix . 'index.php',
    ],
    [
        'chave' => 'tintas',
        'label' => 'Tintas',
        'icone' => 'fa-solid fa-droplet',
        'href' => $basePrefix . 'tintas.php',
    ],
    [
        'chave' => 'impressoras',
        'label' => 'Impressoras',
        'icone' => 'fa-solid fa-print',
        'href' => $basePrefix . 'impressoras.php',
    ],
    [
        'chave' => 'relatorios',
        'label' => 'Relatorios',
        'icone' => 'fa-solid fa-chart-column',
        'href' => $basePrefix . 'relatorios.php',
    ],
    [
        'chave' => 'conta',
        'label' => 'Conta',
        'icone' => 'fa-solid fa-user-gear',
        'href' => $basePrefix . 'conta.php',
    ],
];
?>
<div class="layout-sistema">
    <aside class="layout-sistema__sidebar">
        <div class="layout-sistema__marca">
            <div class="layout-sistema__logo">
                <i class="fa-solid fa-fill-drip"></i>
            </div>
            <div>
                <h1>Estoque</h1>
                <span>Sistema de tintas</span>
            </div>
        </div>

        <nav class="layout-sistema__menu" aria-label="Menu principal">
            <?php foreach ($itensMenu as $item): ?>
                <a href="<?= e($item['href']) ?>" class="<?= $paginaAtual === $item['chave'] ? 'ativo' : '' ?>">
                    <i class="<?= e($item['icone']) ?>"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <div class="menu-secundario">
                <form method="POST" action="<?= e($logoutAction) ?>" class="layout-sistema__logout-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Sair</span>
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <div class="layout-sistema__corpo">
        <header class="layout-sistema__topo">
            <div class="layout-sistema__titulo">
                <h2><?= e($paginaTitulo) ?></h2>
                <p><?= e($paginaDescricao) ?></p>
            </div>

            <div class="layout-sistema__acoes">
                <div class="layout-sistema__usuario">
                    <div class="layout-sistema__avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <strong><?= e($usuarioNome) ?></strong>
                        <span><?= e($usuarioNivelLabel) ?></span>
                    </div>
                </div>

                <form method="POST" action="<?= e($logoutAction) ?>" class="layout-sistema__logout-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="layout-sistema__sair">
                        <i class="fa-solid fa-power-off"></i>
                        Sair
                    </button>
                </form>
            </div>
        </header>
