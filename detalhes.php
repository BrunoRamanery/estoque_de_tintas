<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/servicos/tintas_servico.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$csrfToken = obter_token_csrf();
$modelo = trim((string) ($_GET['modelo'] ?? ''));
$busca = trim((string) ($_GET['busca'] ?? ''));

if ($modelo === '') {
    header('Location: tintas.php');
    exit;
}

try {
    $dadosDetalhes = servico_tintas_obter_detalhes_modelo($conn, $modelo, $busca);
} catch (RuntimeException $erro) {
    $conn->close();
    error_log('Falha ao carregar detalhes do modelo ' . $modelo . ': ' . $erro->getMessage());
    definir_mensagem_flash('erro', 'Nao foi possivel carregar os detalhes do modelo.');
    header('Location: tintas.php');
    exit;
}

$conn->close();

$tintas = $dadosDetalhes['tintas'];
$totalQuantidade = $dadosDetalhes['total_quantidade'];
$totalCompraUrgente = $dadosDetalhes['total_compra_urgente'];
$totalVenceBreve = $dadosDetalhes['total_vence_breve'];
$resumoCor = $dadosDetalhes['resumo_cor'];

$tituloPagina = 'Modelo ' . $modelo;
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
<?php
    $basePrefix = "";
    $paginaAtual = "tintas";
    $paginaTitulo = "Modelo " . $modelo;
    $paginaDescricao = "Resumo e historico do modelo selecionado";
    require __DIR__ . "/includes/topo_sistema.php";
?>
<div class="container detalhes-simples">
    <div class="topo">
        <h1><i class="fa-solid fa-box"></i> Modelo <?= e($modelo) ?></h1>
        <a href="tintas.php" class="botao">Voltar</a>
    </div>

    <div class="cards">
        <div class="card">
            <span>Total</span>
            <strong><?= e($totalQuantidade) ?></strong>
        </div>

        <div class="card alerta">
            <span>Compra urgente</span>
            <strong><?= e($totalCompraUrgente) ?></strong>
        </div>

        <div class="card aviso">
            <span>Vence em breve</span>
            <strong><?= e($totalVenceBreve) ?></strong>
        </div>
    </div>

    <div class="resumo-cor">
        <h3><i class="fa-solid fa-palette"></i> Resumo por cor</h3>
        <div class="linha-cores">
            <?php foreach ($resumoCor as $cor => $qtd): ?>
                <div class="cor-item">
                    <span class="badge"><?= e($cor) ?></span>
                    <strong><?= e($qtd) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="GET" class="filtros">
        <input type="hidden" name="modelo" value="<?= e($modelo) ?>">
        <input type="text" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por impressora, cor, mes ou ano">
        <button type="submit" class="botao">Filtrar</button>
        <a class="botao botao-secundario" href="detalhes.php?modelo=<?= urlencode($modelo) ?>">Limpar</a>
    </form>

    <div class="tabela">
        <table>
            <tr>
                <th>Impressora</th>
                <th>Cor</th>
                <th>Qtd</th>
                <th>Validade</th>
                <th>Compra</th>
                <th>Acoes</th>
            </tr>

            <?php if (!empty($tintas)): ?>
                <?php foreach ($tintas as $t): ?>
                    <tr>
                        <td><?= e($t['impressora']) ?></td>
                        <td><?= e(strtoupper((string) $t['cor'])) ?></td>
                        <td><?= e($t['quantidade']) ?></td>
                        <td><?= e((int) $t['mes']) ?>/<?= e((int) $t['ano']) ?></td>
                        <td><?= e((string) ($t['status_compra']['label'] ?? '')) ?></td>
                        <td class="acoes">
                            <a class="btn-acao btn-editar" href="funcoes/editar.php?id=<?= (int) $t['id'] ?>&retorno_modelo=<?= urlencode($modelo) ?>">
                                <i class="fa-solid fa-pen-to-square"></i> Editar
                            </a>

                            <form method="POST" action="funcoes/deletar.php">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <input type="hidden" name="retorno_modelo" value="<?= e($modelo) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <button class="btn-acao btn-excluir" type="submit" onclick="return confirm('Deseja excluir esta tinta?');">
                                    <i class="fa-solid fa-trash"></i> Excluir
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="vazio">Nenhum registro encontrado para este modelo.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</div>
    </div>
</div>
</body>
</html>
