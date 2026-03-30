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
        $paginaDescricao = "Atalhos para leitura de indicadores do estoque";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean">
        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h2>Relatorios</h2>
                    <p>Acesse rapidamente os indicadores e listas mais usados.</p>
                </div>
            </div>
        </section>

        <section class="cards-menu">
            <div class="card-menu">
                <h3><i class="fa-solid fa-chart-column"></i> Graficos do estoque</h3>
                <p>Visao por cor, modelo e alertas para tomada de decisao.</p>
                <a href="tintas.php#graficos" class="btn-entrar">
                    <i class="fa-solid fa-arrow-right"></i> Abrir graficos
                </a>
            </div>

            <div class="card-menu">
                <h3><i class="fa-solid fa-cart-shopping"></i> Lista de compras</h3>
                <p>Priorize compras com base no status atual do estoque.</p>
                <a href="tintas.php" class="btn-entrar">
                    <i class="fa-solid fa-arrow-right"></i> Abrir lista
                </a>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
