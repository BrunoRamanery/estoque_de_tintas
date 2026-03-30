<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$tituloPagina = 'Relatorios';
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "";
        $paginaAtual = "relatorios";
        $paginaTitulo = "Relatorios";
        $paginaDescricao = "Pagina de relatorios em desenvolvimento";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean">
        <section class="bloco-vazio-pagina">
            <div class="bloco-vazio-pagina__conteudo">
                <i class="fa-solid fa-file-lines"></i>
                <h1>Relatorios</h1>
                <p>Em desenvolvimento...</p>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
