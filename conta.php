<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$tituloPagina = 'Conta';
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "";
        $paginaAtual = "conta";
        $paginaTitulo = "Conta";
        $paginaDescricao = "Pagina da conta em desenvolvimento";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean">
        <section class="bloco-vazio-pagina">
            <div class="bloco-vazio-pagina__conteudo">
                <i class="fa-solid fa-user"></i>
                <h1>Conta</h1>
                <p>Em desenvolvimento...</p>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
