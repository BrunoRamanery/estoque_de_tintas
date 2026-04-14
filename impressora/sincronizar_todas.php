<?php
/**
 * Sincroniza todas as impressoras cadastradas.
 *
 * Fluxo principal (POST): processa e redireciona para impressoras.php com mensagem flash.
 * Fluxo tecnico opcional: enviar POST com relatorio=1 para exibir o relatorio em tela.
 */
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../usuario/verificar_login.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/sincronizacao_helper.php';

@set_time_limit(0);

$modoRelatorio = isset($_POST['relatorio']) && (string) $_POST['relatorio'] === '1';
$retornoBusca = trim((string) ($_POST['retorno_busca'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definir_mensagem_flash('erro', 'Metodo nao permitido para sincronizacao em lote.');
    $url = 'impressoras.php';
    if ($retornoBusca !== '') {
        $url .= '?' . http_build_query(['busca' => $retornoBusca]);
    }
    $conn->close();
    header('Location: ' . $url);
    exit;
}

validar_csrf_ou_encerrar((string) ($_POST['csrf_token'] ?? ''));

$consulta = $conn->query('SELECT id, nome, modelo, ip FROM impressoras ORDER BY nome ASC, id ASC');
if (!$consulta) {
    if ($modoRelatorio) {
        $conn->close();
        exit('Falha ao listar impressoras para sincronizacao.');
    }

    definir_mensagem_flash('erro', 'Falha ao listar impressoras para sincronizacao.');
    $url = 'impressoras.php';
    if ($retornoBusca !== '') {
        $url .= '?' . http_build_query(['busca' => $retornoBusca]);
    }
    $conn->close();
    header('Location: ' . $url);
    exit;
}

$impressoras = [];
while ($row = $consulta->fetch_assoc()) {
    $impressoras[] = $row;
}
$consulta->free();

$colunaUltimaAtualizacao = detectarColunaUltimaAtualizacao($conn);

$relatorio = [];
$totalSucesso = 0;
$totalParcial = 0;
$totalFalha = 0;

$montarResultadoErroInterno = static function (array $impressora, Throwable $erro): array {
    return [
        'id' => (int) ($impressora['id'] ?? 0),
        'nome' => (string) ($impressora['nome'] ?? ''),
        'ip' => (string) ($impressora['ip'] ?? ''),
        'status' => '',
        'preto' => null,
        'ciano' => null,
        'magenta' => null,
        'amarelo' => null,
        'ok' => false,
        'parcial' => false,
        'classificacao' => 'falha',
        'erro' => 'Erro interno: ' . $erro->getMessage(),
        'status_lido' => false,
        'tinta_lida' => false,
        'paginas_lidas' => false,
        'a4_lido' => false,
        'a3_lido' => false,
        'protocolo_status_tinta' => '',
        'url_status_tinta' => '',
        'protocolo_uso' => '',
        'url_uso' => '',
        'fallback_http_status_tinta' => false,
        'fallback_http_uso' => false,
        'dados_gravados' => false,
        'campos_gravados' => [],
        'historico_gravado' => false,
    ];
};

foreach ($impressoras as $impressora) {
    try {
        $resultado = sincronizarImpressoraPorRegistro($conn, $impressora, $colunaUltimaAtualizacao);
    } catch (Throwable $erro) {
        $resultado = $montarResultadoErroInterno($impressora, $erro);
    }

    $relatorio[] = $resultado;

    if (!empty($resultado['ok'])) {
        $totalSucesso++;
    } elseif (!empty($resultado['parcial'])) {
        $totalParcial++;
    } else {
        $totalFalha++;
    }

    // Pequeno intervalo para evitar estouro de conexoes em sequencia.
    usleep(150000);
}

$conn->close();

if (!$modoRelatorio) {
    if ($totalFalha === 0 && $totalParcial === 0) {
        definir_mensagem_flash('sucesso', 'Sincronizacao concluida com sucesso. ' . $totalSucesso . ' impressora(s) atualizada(s).');
    } elseif ($totalSucesso === 0 && $totalParcial === 0) {
        definir_mensagem_flash('erro', 'Sincronizacao concluida com falhas. Nenhuma impressora foi atualizada.');
    } else {
        definir_mensagem_flash('erro', 'Sincronizacao concluida com alertas. Sucesso: ' . $totalSucesso . ' | Parcial: ' . $totalParcial . ' | Falhas: ' . $totalFalha . '.');
    }

    $url = 'impressoras.php';
    if ($retornoBusca !== '') {
        $url .= '?' . http_build_query(['busca' => $retornoBusca]);
    }
    header('Location: ' . $url);
    exit;
}

$tituloPagina = 'Sincronizacao de Impressoras';
$caminhoCss = '../css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "../";
        $paginaAtual = "impressoras";
        $paginaTitulo = "Sincronizacao em lote";
        $paginaDescricao = "Relatorio tecnico da execucao de sincronizacao";
        require __DIR__ . "/../includes/topo_sistema.php";
    ?>
    <div class="container sincronizacao-relatorio">
        <section class="pagina-hero">
            <div class="pagina-hero__conteudo">
                <span class="pagina-hero__eyebrow">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    Relatorio tecnico
                </span>
                <h1>Sincronizacao de Impressoras</h1>
                <p>Visao consolidada da execucao manual da sincronizacao em lote. O processamento continua o mesmo; aqui foi alterada apenas a apresentacao do resultado.</p>

                <div class="pagina-hero__chips">
                    <span class="pagina-hero__chip">
                        <i class="fa-solid fa-print"></i>
                        <?= e((string) count($relatorio)) ?> impressora(s)
                    </span>
                    <span class="pagina-hero__chip">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= e((string) $totalSucesso) ?> atualizada(s)
                    </span>
                    <span class="pagina-hero__chip">
                        <i class="fa-solid fa-circle-half-stroke"></i>
                        <?= e((string) $totalParcial) ?> parcial(is)
                    </span>
                    <span class="pagina-hero__chip">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <?= e((string) $totalFalha) ?> falha(s)
                    </span>
                </div>

                <div class="pagina-hero__acoes">
                    <a href="impressoras.php" class="btn-voltar">
                        <i class="fa-solid fa-arrow-left"></i>
                        Voltar para impressoras
                    </a>
                </div>
            </div>

            <aside class="pagina-hero__painel">
                <span class="pagina-hero__rotulo">Resumo da execucao</span>
                <strong><?= ($totalFalha === 0 && $totalParcial === 0) ? 'Concluida sem falhas' : 'Concluida com alertas' ?></strong>
                <small>Use esta tela apenas para conferencia tecnica. A sincronizacao segue sendo gravada da mesma forma no banco e nas rotinas ja existentes.</small>

                <div class="pagina-hero__metricas">
                    <div class="pagina-hero__metrica">
                        <span>Taxa de sucesso</span>
                        <strong><?= e((string) (count($relatorio) > 0 ? (int) round(($totalSucesso / count($relatorio)) * 100) : 0)) ?>%</strong>
                    </div>
                    <div class="pagina-hero__metrica">
                        <span>Status predominante</span>
                        <strong><?= ($totalSucesso >= ($totalParcial + $totalFalha)) ? 'Atualizacao salva' : 'Revisar impressoras' ?></strong>
                    </div>
                </div>
            </aside>
        </section>

        <section class="sincronizacao-relatorio__resumo cards-resumo">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-print"></i></div>
                <div>
                    <strong><?= e((string) count($relatorio)) ?></strong>
                    <span>Total processado</span>
                    <small>Quantidade de impressoras percorridas nesta execucao.</small>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <strong><?= e((string) $totalSucesso) ?></strong>
                    <span>Sincronizadas</span>
                    <small>Impressoras que retornaram dados e atualizaram estado.</small>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-circle-half-stroke"></i></div>
                <div>
                    <strong><?= e((string) $totalParcial) ?></strong>
                    <span>Parciais</span>
                    <small>Houve leitura e gravacao util, mas parte da coleta falhou.</small>
                </div>
            </div>

            <div class="card-resumo card-vencida">
                <div class="icone-resumo"><i class="fa-solid fa-circle-xmark"></i></div>
                <div>
                    <strong><?= e((string) $totalFalha) ?></strong>
                    <span>Com falha</span>
                    <small>Itens que precisam de revisao de rede, status ou autenticacao.</small>
                </div>
            </div>
        </section>

        <section class="bloco-detalhes relatorios-secao">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-table"></i>
                </div>
                <div>
                    <h2>Resultado por impressora</h2>
                    <p>Lista tecnica da execucao, com status salvo, niveis de tinta retornados e possiveis mensagens de erro.</p>
                </div>
            </div>

            <div class="tabela-wrapper tabela-wrapper-relatorios">
                <table class="tabela-relatorios">
                    <thead>
                        <tr>
                            <th>Impressora</th>
                            <th>IP</th>
                            <th>Status salvo</th>
                            <th>Tintas</th>
                            <th>Resultado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($relatorio)): ?>
                            <tr>
                                <td colspan="5" class="vazio">Nenhuma impressora cadastrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($relatorio as $item): ?>
                                <tr>
                                    <td>
                                        <div class="celula-impressora">
                                            <strong><?= e((string) ($item['nome'] ?? '')) ?></strong>
                                            <span>ID <?= e((string) ((int) ($item['id'] ?? 0))) ?></span>
                                        </div>
                                    </td>
                                    <td><?= e((string) ($item['ip'] ?? '-')) ?></td>
                                    <td>
                                        <span class="impressora-pill impressora-pill--status">
                                            <?= e((string) ($item['status'] ?? 'Desconhecido')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="sincronizacao-tintas">
                                            <span>BK <?= $item['preto'] !== null ? e((string) ((int) $item['preto'])) . '%' : '-' ?></span>
                                            <span>C <?= $item['ciano'] !== null ? e((string) ((int) $item['ciano'])) . '%' : '-' ?></span>
                                            <span>M <?= $item['magenta'] !== null ? e((string) ((int) $item['magenta'])) . '%' : '-' ?></span>
                                            <span>Y <?= $item['amarelo'] !== null ? e((string) ((int) $item['amarelo'])) . '%' : '-' ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['ok'])): ?>
                                            <span class="sincronizacao-pill sincronizacao-pill--ok">
                                                <i class="fa-solid fa-circle-check"></i>
                                                SUCESSO
                                            </span>
                                        <?php elseif (!empty($item['parcial'])): ?>
                                            <span class="impressora-pill impressora-pill--sync">
                                                <i class="fa-solid fa-circle-half-stroke"></i>
                                                PARCIAL
                                            </span>
                                        <?php else: ?>
                                            <span class="sincronizacao-pill sincronizacao-pill--erro">
                                                <i class="fa-solid fa-circle-xmark"></i>
                                                FALHA
                                            </span>
                                        <?php endif; ?>
                                        <div class="celula-impressora">
                                            <span>Status/Tinta: <?= !empty($item['status_lido']) || !empty($item['tinta_lida']) ? 'SIM' : 'NAO' ?></span>
                                            <span>Uso: <?= !empty($item['paginas_lidas']) || !empty($item['a4_lido']) || !empty($item['a3_lido']) ? 'SIM' : 'NAO' ?></span>
                                            <span>Historico: <?= !empty($item['historico_gravado']) ? 'SIM' : 'NAO' ?></span>
                                            <?php if (!empty($item['erro'])): ?>
                                                <span><?= e((string) $item['erro']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
