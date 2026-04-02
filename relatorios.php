<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$impressoras = [];
$consulta = $conn->query('SELECT nome, paginas_total, paginas_pb, paginas_cor FROM impressoras ORDER BY nome ASC');
if ($consulta instanceof mysqli_result) {
    while ($linha = $consulta->fetch_assoc()) {
        $impressoras[] = $linha;
    }
    $consulta->free();
}
$conn->close();

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

        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-print"></i>
                </div>
                <div>
                    <h2>Relatorio de paginas por impressora</h2>
                    <p>Totais de paginas geral, preto e branco e coloridas.</p>
                </div>
            </div>

            <div class="tabela-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Impressora</th>
                            <th>Paginas total</th>
                            <th>Paginas P&B</th>
                            <th>Paginas cor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($impressoras)): ?>
                            <?php foreach ($impressoras as $impressora): ?>
                                <tr>
                                    <td><?= e($impressora['nome'] ?? '') ?></td>
                                    <td><?= (int) ($impressora['paginas_total'] ?? 0) ?></td>
                                    <td><?= (int) ($impressora['paginas_pb'] ?? 0) ?></td>
                                    <td><?= (int) ($impressora['paginas_cor'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="vazio">Nenhuma impressora encontrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
