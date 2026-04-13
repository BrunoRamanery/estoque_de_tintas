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
        <section class="relatorios-hero">
            <div class="relatorios-hero__conteudo">
                <span class="relatorios-hero__eyebrow">Painel principal</span>
                <h1>Controle operacional do sistema</h1>
                <p>Use a home como ponto de entrada para estoque de tintas, parque de impressoras, relatórios e gestão de acesso. Toda a lógica permanece a mesma; aqui a ideia é só organizar melhor a navegação.</p>

                <div class="relatorios-hero__chips">
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-droplet"></i>
                        Estoque e validade
                    </span>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-print"></i>
                        Sincronizacao de impressoras
                    </span>
                    <span class="relatorio-chip">
                        <i class="fa-solid fa-chart-column"></i>
                        Leitura por periodo
                    </span>
                </div>
            </div>

            <div class="relatorios-hero__painel">
                <span class="relatorios-hero__rotulo">Acesso rapido</span>
                <strong>4 areas principais</strong>
                <small>Navegacao centralizada para reduzir cliques e facilitar a leitura do sistema.</small>
            </div>
        </section>

        <section class="grid-modelos">
            <a class="card-modelo" href="tintas.php">
                <div class="card-topo">
                    <div class="icone-modelo">
                        <i class="fa-solid fa-droplet"></i>
                    </div>
                    <div>
                        <h2>Tintas</h2>
                        <p>Estoque, validade, compras e distribuicao por modelo.</p>
                    </div>
                </div>
                <div class="card-rodape">
                    <span class="ver-detalhes">Abrir painel <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>

            <a class="card-modelo" href="impressora/impressoras.php">
                <div class="card-topo">
                    <div class="icone-modelo">
                        <i class="fa-solid fa-print"></i>
                    </div>
                    <div>
                        <h2>Impressoras</h2>
                        <p>Cadastro, sincronizacao, nivel de tinta e perfil A3/A4.</p>
                    </div>
                </div>
                <div class="card-rodape">
                    <span class="ver-detalhes">Abrir painel <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>

            <a class="card-modelo" href="relatorios.php">
                <div class="card-topo">
                    <div class="icone-modelo">
                        <i class="fa-solid fa-chart-column"></i>
                    </div>
                    <div>
                        <h2>Relatorios</h2>
                        <p>Consumo por periodo, ranking e leitura visual do parque.</p>
                    </div>
                </div>
                <div class="card-rodape">
                    <span class="ver-detalhes">Abrir painel <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>

            <a class="card-modelo" href="conta.php">
                <div class="card-topo">
                    <div class="icone-modelo">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <div>
                        <h2>Conta</h2>
                        <p>Informacoes da sessao ativa e acessos administrativos.</p>
                    </div>
                </div>
                <div class="card-rodape">
                    <span class="ver-detalhes">Abrir painel <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </section>
    </div>
    </div>
</div>
</body>
</html>
