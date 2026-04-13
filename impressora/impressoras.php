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

$definicoesTintas = [
    ['sigla' => 'BK', 'campo' => 'tinta_preto', 'cor' => '#0f172a'],
    ['sigla' => 'C', 'campo' => 'tinta_ciano', 'cor' => '#00AEEF'],
    ['sigla' => 'M', 'campo' => 'tinta_magenta', 'cor' => '#ED008C'],
    ['sigla' => 'Y', 'campo' => 'tinta_amarelo', 'cor' => '#FFF200'],
];

$normalizarPercentualTinta = static function ($valor): ?int {
    if ($valor === null || $valor === '') {
        return null;
    }

    if (!is_numeric($valor)) {
        return null;
    }

    $numero = (int) round((float) $valor);
    if ($numero < 0) {
        return 0;
    }

    if ($numero > 100) {
        return 100;
    }

    return $numero;
};

$formatarDataHoraCurta = static function (?string $valor): string {
    $texto = trim((string) $valor);
    if ($texto === '') {
        return 'Sem sincronizacao';
    }

    $data = date_create($texto);
    if (!$data) {
        return $texto;
    }

    return $data->format('d/m/Y H:i');
};

$detectarFormatoUso = static function (array $impressora): array {
    $totalA3 = 0;
    $totalA4 = 0;

    foreach (['a3_pb_simples', 'a3_cor_simples', 'a3_pb_duplex', 'a3_cor_duplex'] as $campoA3) {
        $totalA3 += (int) ($impressora[$campoA3] ?? 0);
    }

    foreach (['a4_pb_simples', 'a4_cor_simples', 'a4_pb_duplex', 'a4_cor_duplex'] as $campoA4) {
        $totalA4 += (int) ($impressora[$campoA4] ?? 0);
    }

    if ($totalA3 > 0 && $totalA4 > 0) {
        return [
            'label' => 'A3 + A4 detectado',
            'classe' => 'impressora-pill--misto',
            'chave' => 'misto',
        ];
    }

    if ($totalA3 > 0) {
        return [
            'label' => 'Uso A3 detectado',
            'classe' => 'impressora-pill--a3',
            'chave' => 'a3',
        ];
    }

    if ($totalA4 > 0) {
        return [
            'label' => 'Uso A4 detectado',
            'classe' => 'impressora-pill--a4',
            'chave' => 'a4',
        ];
    }

    return [
        'label' => 'Formato sem deteccao',
        'classe' => 'impressora-pill--sync',
        'chave' => 'indefinido',
    ];
};

$renderizarTanqueTinta = static function (string $sigla, ?int $percentual, string $corHex, string $contexto = 'card'): string {
    $classeContexto = $contexto === 'tabela' ? 'tanque-tinta--tabela' : 'tanque-tinta--card';
    $semDado = $percentual === null;
    $altura = $semDado ? 0 : (int) max(0, min(100, $percentual));
    $textoValor = $semDado ? 'N/D' : $altura . '%';

    ob_start();
    ?>
    <div class="tanque-tinta <?= e($classeContexto) ?><?= $semDado ? ' tanque-tinta--vazio' : '' ?>">
        <span class="tanque-tinta__sigla"><?= e($sigla) ?></span>
        <div class="tanque-tinta__coluna" title="<?= e($sigla . ' ' . $textoValor) ?>">
            <div class="tanque-tinta__preenchimento" style="height: <?= e((string) $altura) ?>%; background: <?= e($corHex) ?>;"></div>
        </div>
        <span class="tanque-tinta__valor"><?= e($textoValor) ?></span>
    </div>
    <?php
    return (string) ob_get_clean();
};

$renderizarGrupoTintas = static function (array $impressora, string $contexto = 'card') use ($definicoesTintas, $normalizarPercentualTinta, $renderizarTanqueTinta): string {
    ob_start();
    ?>
    <div class="tintas-epson tintas-epson--<?= e($contexto) ?>">
        <?php foreach ($definicoesTintas as $definicao): ?>
            <?php
            $percentual = $normalizarPercentualTinta($impressora[$definicao['campo']] ?? null);
            echo $renderizarTanqueTinta($definicao['sigla'], $percentual, $definicao['cor'], $contexto);
            ?>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};

$totalUsoA3Detectado = 0;
$totalUsoA4Detectado = 0;

foreach ($impressoras as &$impressora) {
    $impressora['ultima_atualizacao_formatada'] = $formatarDataHoraCurta($impressora['ultima_atualizacao'] ?? null);
    $impressora['status_visual'] = trim((string) ($impressora['status_impressora'] ?? '')) !== ''
        ? trim((string) $impressora['status_impressora'])
        : 'Sem status';
    $impressora['formato_visual'] = $detectarFormatoUso($impressora);

    if (($impressora['formato_visual']['chave'] ?? '') === 'a3' || ($impressora['formato_visual']['chave'] ?? '') === 'misto') {
        $totalUsoA3Detectado++;
    }

    if (($impressora['formato_visual']['chave'] ?? '') === 'a4' || ($impressora['formato_visual']['chave'] ?? '') === 'misto') {
        $totalUsoA4Detectado++;
    }
}
unset($impressora);

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
                <form method="POST" action="sincronizar_todas.php" class="form-sincronizar-impressoras">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="retorno_busca" value="<?= e($busca) ?>">
                    <button type="submit" class="botao botao-sincronizar">
                        <i class="fa-solid fa-arrows-rotate"></i> Sincronizar impressoras
                    </button>
                </form>
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

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-expand"></i></div>
                <div>
                    <strong><?= e($totalUsoA3Detectado) ?></strong>
                    <span>Com uso A3 detectado</span>
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
                                <div class="impressora-badges">
                                    <span class="impressora-pill impressora-pill--status">
                                        <i class="fa-solid fa-circle-info"></i>
                                        <?= e($impressora['status_visual']) ?>
                                    </span>
                                    <span class="impressora-pill <?= e($impressora['formato_visual']['classe']) ?>">
                                        <i class="fa-solid fa-clone"></i>
                                        <?= e($impressora['formato_visual']['label']) ?>
                                    </span>
                                    <span class="impressora-pill impressora-pill--sync">
                                        <i class="fa-solid fa-clock"></i>
                                        <?= e($impressora['ultima_atualizacao_formatada']) ?>
                                    </span>
                                </div>
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

                            <div class="mini-info">
                                <span class="mini-label">Paginas atuais</span>
                                <strong><?= e((string) ((int) ($impressora['paginas_total'] ?? 0))) ?></strong>
                            </div>
                        </div>

                        <div class="bloco-tintas-card">
                            <span class="mini-label">Niveis de tinta</span>
                            <?= $renderizarGrupoTintas($impressora, 'card') ?>
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
                            <th><i class="fa-solid fa-clone"></i> Formato</th>
                            <th><i class="fa-solid fa-signal"></i> Status</th>
                            <th><i class="fa-solid fa-droplet"></i> Tintas</th>
                            <th><i class="fa-solid fa-note-sticky"></i> Observacao</th>
                            <th><i class="fa-solid fa-clock"></i> Ultima sync</th>
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
                                    <td>
                                        <span class="impressora-pill <?= e($impressora['formato_visual']['classe']) ?>">
                                            <?= e($impressora['formato_visual']['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="impressora-pill impressora-pill--status">
                                            <?= e($impressora['status_visual']) ?>
                                        </span>
                                    </td>
                                    <td class="coluna-tintas-impressora"><?= $renderizarGrupoTintas($impressora, 'tabela') ?></td>
                                    <td><?= e($observacao) ?></td>
                                    <td><?= e($impressora['ultima_atualizacao_formatada']) ?></td>
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
                                <td colspan="10" class="vazio">Nenhuma impressora cadastrada no momento.</td>
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
