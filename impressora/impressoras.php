<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../servicos/impressoras_servico.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

$mensagem = obter_mensagem_flash();
$busca = trim((string) ($_GET['busca'] ?? ''));
$csrfToken = obter_token_csrf();

try {
    $dadosListagem = servico_impressoras_obter_listagem($conn, $busca);
} catch (RuntimeException $erro) {
    error_log('Falha ao carregar listagem de impressoras: ' . $erro->getMessage());
    $dadosListagem = [
        'impressoras' => [],
        'total_impressoras' => 0,
        'total_modelos' => 0,
        'sem_localizacao' => 0,
    ];
    $mensagem = [
        'tipo' => 'erro',
        'texto' => 'Nao foi possivel carregar a listagem de impressoras no momento.',
    ];
}

$conn->close();

$impressoras = $dadosListagem['impressoras'];
$totalImpressoras = $dadosListagem['total_impressoras'];
$totalModelos = $dadosListagem['total_modelos'];
$semLocalizacao = $dadosListagem['sem_localizacao'];

$montarQueryComBusca = static function (int $id, string $buscaAtual): string {
    $query = ['id' => $id];
    if ($buscaAtual !== '') {
        $query['busca'] = $buscaAtual;
    }
    return http_build_query($query);
};

$montarLinkDetalhes = static function (int $id, string $buscaAtual) use ($montarQueryComBusca): string {
    return 'detalhes.php?' . $montarQueryComBusca($id, $buscaAtual);
};

$montarLinkEditar = static function (int $id, string $buscaAtual) use ($montarQueryComBusca): string {
    return 'editar.php?' . $montarQueryComBusca($id, $buscaAtual);
};

$tituloPagina = 'Impressoras';
$caminhoCss = '../css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "../";
        $paginaAtual = "impressoras";
        $paginaTitulo = "Impressoras";
        $paginaDescricao = "Gerencie impressoras, localizacao e detalhes";
        require __DIR__ . "/../includes/topo_sistema.php";
    ?>
    <div class="container pagina-impressoras">
        <div class="topo topo-impressoras">
            <div class="titulo-bloco">
                <h1><i class="fa-solid fa-print"></i> Impressoras</h1>
                <p class="subtitulo">Tabela e cards para gerenciar impressoras e niveis de tinta.</p>
            </div>

            <div class="acoes">
                <a class="botao" href="cadastrar.php">
                    <i class="fa-solid fa-plus"></i> Nova impressora
                </a>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/mensagem_flash.php'; ?>

        <section class="cards-resumo impressoras-resumo">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-print"></i></div>
                <div>
                    <strong><?= e($totalImpressoras) ?></strong>
                    <span>Impressoras cadastradas</span>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-layer-group"></i></div>
                <div>
                    <strong><?= e($totalModelos) ?></strong>
                    <span>Modelos diferentes</span>
                </div>
            </div>

            <div class="card-resumo card-vencida">
                <div class="icone-resumo"><i class="fa-solid fa-location-dot"></i></div>
                <div>
                    <strong><?= e($semLocalizacao) ?></strong>
                    <span>Sem localizacao</span>
                </div>
            </div>
        </section>

        <form method="GET" class="painel-filtros painel-filtros-impressoras">
            <div class="campo-filtro campo-busca">
                <label for="busca">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar impressora
                </label>
                <input
                    id="busca"
                    type="text"
                    name="busca"
                    value="<?= e($busca) ?>"
                    placeholder="Digite nome, modelo, IP ou localizacao"
                >
            </div>

            <div class="acoes-filtros">
                <button type="submit" class="botao botao-filtro">
                    <i class="fa-solid fa-filter"></i> Pesquisar
                </button>
                <a href="impressoras.php" class="botao botao-filtro">
                    <i class="fa-solid fa-rotate-left"></i> Limpar
                </a>
            </div>
        </form>

        <section class="grid-modelos grid-impressoras">
            <?php if (!empty($impressoras)): ?>
                <?php foreach ($impressoras as $impressora): ?>
                    <?php
                    $idImpressora = (int) $impressora['id'];
                    $linkDetalhes = $montarLinkDetalhes($idImpressora, $busca);
                    $linkEditar = $montarLinkEditar($idImpressora, $busca);
                    $modelo = $impressora['modelo'] !== '' ? $impressora['modelo'] : '-';
                    $ip = $impressora['ip'] !== '' ? $impressora['ip'] : '-';
                    $localizacao = $impressora['localizacao'] !== '' ? $impressora['localizacao'] : '-';
                    $observacao = $impressora['observacao'] !== '' ? $impressora['observacao'] : 'Sem observacao';
                    ?>
                    <article class="card-modelo card-impressora">
                        <div class="card-topo">
                            <div class="icone-modelo">
                                <i class="fa-solid fa-print"></i>
                            </div>
                            <div>
                                <h2><?= e($impressora['nome']) ?></h2>
                                <p>Modelo: <?= e($modelo) ?></p>
                            </div>
                        </div>

                        <div class="card-infos">
                            <div class="mini-info">
                                <span class="mini-label">IP</span>
                                <strong><?= e($ip) ?></strong>
                            </div>

                            <div class="mini-info">
                                <span class="mini-label">Localizacao</span>
                                <strong><?= e($localizacao) ?></strong>
                            </div>
                        </div>

                        <div class="card-rodape card-rodape-impressora">
                            <span class="mini-label">Observacao</span>
                            <p><?= e($observacao) ?></p>
                            <div class="acoes acoes-impressora-card">
                                <a class="btn-acao btn-editar" href="<?= e($linkDetalhes) ?>">
                                    <i class="fa-solid fa-eye"></i> Ver detalhes
                                </a>
                                <a class="btn-acao btn-editar" href="<?= e($linkEditar) ?>">
                                    <i class="fa-solid fa-pen-to-square"></i> Editar
                                </a>
                                <form method="POST" action="excluir.php">
                                    <input type="hidden" name="id" value="<?= e($idImpressora) ?>">
                                    <input type="hidden" name="busca" value="<?= e($busca) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <button type="submit" class="btn-acao btn-excluir" onclick="return confirm('Excluir esta impressora?');">
                                        <i class="fa-solid fa-trash"></i> Excluir
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="estado-vazio estado-vazio-impressora">
                    <i class="fa-solid fa-print"></i>
                    <h2>Nenhuma impressora encontrada</h2>
                    <p>Use o botao "Nova impressora" para cadastrar um novo equipamento.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="bloco-detalhes bloco-tabela-impressoras">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-table"></i>
                </div>
                <div>
                    <h2>Tabela de impressoras</h2>
                    <p>Lista completa para consulta rapida.</p>
                </div>
            </div>

            <div class="tabela-wrapper tabela-wrapper-impressoras">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-print"></i> Nome</th>
                            <th><i class="fa-solid fa-layer-group"></i> Modelo</th>
                            <th><i class="fa-solid fa-network-wired"></i> IP</th>
                            <th><i class="fa-solid fa-location-dot"></i> Localizacao</th>
                            <th><i class="fa-solid fa-note-sticky"></i> Observacao</th>
                            <th><i class="fa-solid fa-screwdriver-wrench"></i> Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($impressoras)): ?>
                            <?php foreach ($impressoras as $impressora): ?>
                                <?php
                                $idImpressora = (int) $impressora['id'];
                                $linkDetalhes = $montarLinkDetalhes($idImpressora, $busca);
                                $linkEditar = $montarLinkEditar($idImpressora, $busca);
                                $modelo = $impressora['modelo'] !== '' ? $impressora['modelo'] : '-';
                                $ip = $impressora['ip'] !== '' ? $impressora['ip'] : '-';
                                $localizacao = $impressora['localizacao'] !== '' ? $impressora['localizacao'] : '-';
                                $observacao = $impressora['observacao'] !== '' ? $impressora['observacao'] : '-';
                                ?>
                                <tr>
                                    <td><?= e($impressora['nome']) ?></td>
                                    <td><?= e($modelo) ?></td>
                                    <td><?= e($ip) ?></td>
                                    <td><?= e($localizacao) ?></td>
                                    <td><?= e($observacao) ?></td>
                                    <td class="acoes acoes-impressora">
                                        <a class="btn-acao btn-editar" href="<?= e($linkDetalhes) ?>">
                                            <i class="fa-solid fa-eye"></i> Ver detalhes
                                        </a>
                                        <a class="btn-acao btn-editar" href="<?= e($linkEditar) ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Editar
                                        </a>
                                        <form method="POST" action="excluir.php">
                                            <input type="hidden" name="id" value="<?= e($idImpressora) ?>">
                                            <input type="hidden" name="busca" value="<?= e($busca) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <button type="submit" class="btn-acao btn-excluir" onclick="return confirm('Excluir esta impressora?');">
                                                <i class="fa-solid fa-trash"></i> Excluir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="vazio">Nenhuma impressora cadastrada no momento.</td>
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
