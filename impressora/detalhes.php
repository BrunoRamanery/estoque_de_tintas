<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../servicos/impressoras_servico.php';
require_once __DIR__ . '/../usuario/verificar_login.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$buscaRetorno = trim((string) ($_GET['busca'] ?? ''));
$csrfToken = obter_token_csrf();

$redirecionarListagem = static function (string $busca): void {
    $url = 'impressoras.php';
    if ($busca !== '') {
        $url .= '?' . http_build_query(['busca' => $busca]);
    }

    header('Location: ' . $url);
    exit;
};

if ($id === false || $id === null || $id <= 0) {
    $conn->close();
    definir_mensagem_flash('erro', 'ID de impressora invalido.');
    $redirecionarListagem($buscaRetorno);
}

$impressora = null;
try {
    $impressora = servico_impressoras_buscar_detalhes($conn, (int) $id);
} catch (RuntimeException $erro) {
    error_log('Falha ao carregar detalhes da impressora ID ' . $id . ': ' . $erro->getMessage());
    $conn->close();
    definir_mensagem_flash('erro', 'Nao foi possivel carregar os detalhes da impressora.');
    $redirecionarListagem($buscaRetorno);
}

$conn->close();

if (!$impressora) {
    definir_mensagem_flash('erro', 'Impressora nao encontrada.');
    $redirecionarListagem($buscaRetorno);
}

$voltarUrl = 'impressoras.php';
if ($buscaRetorno !== '') {
    $voltarUrl .= '?' . http_build_query(['busca' => $buscaRetorno]);
}

$queryDetalhe = ['id' => (int) $id];
if ($buscaRetorno !== '') {
    $queryDetalhe['busca'] = $buscaRetorno;
}
$linkEditar = 'editar.php?' . http_build_query($queryDetalhe);

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

$detectarFormatoUso = static function (array $item): array {
    $totalA3 = 0;
    $totalA4 = 0;

    foreach (['a3_pb_simples', 'a3_cor_simples', 'a3_pb_duplex', 'a3_cor_duplex'] as $campoA3) {
        $totalA3 += (int) ($item[$campoA3] ?? 0);
    }

    foreach (['a4_pb_simples', 'a4_cor_simples', 'a4_pb_duplex', 'a4_cor_duplex'] as $campoA4) {
        $totalA4 += (int) ($item[$campoA4] ?? 0);
    }

    if ($totalA3 > 0 && $totalA4 > 0) {
        return ['label' => 'A3 + A4 detectado', 'classe' => 'impressora-pill--misto'];
    }

    if ($totalA3 > 0) {
        return ['label' => 'Uso A3 detectado', 'classe' => 'impressora-pill--a3'];
    }

    if ($totalA4 > 0) {
        return ['label' => 'Uso A4 detectado', 'classe' => 'impressora-pill--a4'];
    }

    return ['label' => 'Formato sem deteccao', 'classe' => 'impressora-pill--sync'];
};

$formatoVisual = $detectarFormatoUso($impressora);
$statusVisual = trim((string) ($impressora['status_impressora'] ?? '')) !== ''
    ? trim((string) $impressora['status_impressora'])
    : 'Sem status';
$ultimaAtualizacaoFormatada = $formatarDataHoraCurta($impressora['ultima_atualizacao'] ?? null);
$paginasTotalAtual = (int) ($impressora['paginas_total'] ?? 0);

$tituloPagina = 'Detalhes da Impressora';
$caminhoCss = '../css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "../";
        $paginaAtual = "impressoras";
        $paginaTitulo = "Detalhes da impressora";
        $paginaDescricao = "Visualize informacoes completas da impressora";
        require __DIR__ . "/../includes/topo_sistema.php";
    ?>
    <div class="container pagina-impressoras">
        <div class="topo topo-impressoras">
            <div class="titulo-bloco">
                <h1><i class="fa-solid fa-circle-info"></i> Detalhes da Impressora</h1>
                <p class="subtitulo">Informacoes completas do equipamento selecionado.</p>
            </div>

            <div class="acoes">
                <a class="botao botao-secundario" href="<?= e($voltarUrl) ?>">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
                <a class="botao" href="cadastrar.php">
                    <i class="fa-solid fa-plus"></i> Nova impressora
                </a>
                <a class="botao" href="<?= e($linkEditar) ?>">
                    <i class="fa-solid fa-pen-to-square"></i> Editar
                </a>
                <form method="POST" action="excluir.php">
                    <input type="hidden" name="id" value="<?= e((int) $id) ?>">
                    <input type="hidden" name="busca" value="<?= e($buscaRetorno) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="botao" onclick="return confirm('Excluir esta impressora?');">
                        <i class="fa-solid fa-trash"></i> Excluir
                    </button>
                </form>
            </div>
        </div>

        <section class="bloco-detalhes bloco-detalhes-impressora">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-print"></i>
                </div>
                <div>
                    <h2><?= e((string) ($impressora['nome'] ?? '')) ?></h2>
                    <p>Dados cadastrados da impressora.</p>
                    <div class="impressora-badges">
                        <span class="impressora-pill impressora-pill--status">
                            <i class="fa-solid fa-circle-info"></i>
                            <?= e($statusVisual) ?>
                        </span>
                        <span class="impressora-pill <?= e($formatoVisual['classe']) ?>">
                            <i class="fa-solid fa-clone"></i>
                            <?= e($formatoVisual['label']) ?>
                        </span>
                        <span class="impressora-pill impressora-pill--sync">
                            <i class="fa-solid fa-clock"></i>
                            <?= e($ultimaAtualizacaoFormatada) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mini-metricas-impressora">
                <div class="mini-metrica-impressora">
                    <span>ID</span>
                    <strong><?= e((int) ($impressora['id'] ?? 0)) ?></strong>
                </div>

                <div class="mini-metrica-impressora">
                    <span>Modelo</span>
                    <strong><?= e((string) (($impressora['modelo'] ?? '') !== '' ? $impressora['modelo'] : '-')) ?></strong>
                </div>

                <div class="mini-metrica-impressora">
                    <span>IP</span>
                    <strong><?= e((string) (($impressora['ip'] ?? '') !== '' ? $impressora['ip'] : '-')) ?></strong>
                </div>

                <div class="mini-metrica-impressora">
                    <span>Localizacao</span>
                    <strong><?= e((string) (($impressora['localizacao'] ?? '') !== '' ? $impressora['localizacao'] : '-')) ?></strong>
                </div>

                <div class="mini-metrica-impressora">
                    <span>Paginas atuais</span>
                    <strong><?= e((string) $paginasTotalAtual) ?></strong>
                </div>
            </div>

            <div class="observacao-impressora">
                <span>Observacao</span>
                <p><?= e((string) (($impressora['observacao'] ?? '') !== '' ? $impressora['observacao'] : 'Sem observacao')) ?></p>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
