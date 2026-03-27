<?php
require_once __DIR__ . '/../app/utilidades.php';

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuario';
$usuarioNivel = $_SESSION['usuario_nivel'] ?? 'comum';

$usuarioNivelLabel = $usuarioNivel === 'admin' ? 'Administrador' : 'Usuario';
?>
<header class="topo-sistema">
    <div class="topo-sistema__esquerda">
        <div class="topo-sistema__logo">
            <i class="fa-solid fa-fill-drip"></i>
        </div>

        <div class="topo-sistema__texto">
            <h1>Estoque de Tintas</h1>
            <p>Painel de controle do sistema</p>
        </div>
    </div>

    <div class="topo-sistema__direita">
        <div class="topo-sistema__usuario">
            <div class="topo-sistema__avatar">
                <i class="fa-solid fa-user"></i>
            </div>

            <div class="topo-sistema__dados">
                <strong><?= e($usuarioNome) ?></strong>
                <span><?= e($usuarioNivelLabel) ?></span>
            </div>
        </div>

        <a href="usuario/logout.php" class="topo-sistema__logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            Sair
        </a>
    </div>
</header>
