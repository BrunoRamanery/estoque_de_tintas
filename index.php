<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$tituloPagina = 'Home';
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "";
        $paginaAtual = "home";
        $paginaTitulo = "Home";
        $paginaDescricao = "Pagina inicial do sistema";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean">
        <section class="bloco-detalhes" style="min-height: 320px; display:flex; align-items:center; justify-content:center;">
            <div style="text-align:center;">
                <h1 style="margin-bottom:10px;"><i class="fa-solid fa-hand-sparkles"></i> Bem-vindo</h1>
                <p style="font-size:18px; color:#64748b;">Bem-vindo ao sistema de estoque de tintas.</p>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
