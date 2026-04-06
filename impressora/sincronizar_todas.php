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
$totalFalha = 0;

foreach ($impressoras as $impressora) {
    try {
        $resultado = sincronizarImpressoraPorRegistro($conn, $impressora, $colunaUltimaAtualizacao);
    } catch (Throwable $erro) {
        $idImpressora = (int) ($impressora['id'] ?? 0);
        if ($idImpressora > 0) {
            atualizarDadosImpressora($conn, $idImpressora, 'offline', null, null, null, null, $colunaUltimaAtualizacao);
        }

        $resultado = [
            'id' => $idImpressora,
            'nome' => (string) ($impressora['nome'] ?? ''),
            'ip' => (string) ($impressora['ip'] ?? ''),
            'status' => 'offline',
            'preto' => null,
            'ciano' => null,
            'magenta' => null,
            'amarelo' => null,
            'ok' => false,
            'erro' => 'Erro interno: ' . $erro->getMessage(),
        ];
    }

    $relatorio[] = $resultado;

    if ($resultado['ok']) {
        $totalSucesso++;
    } else {
        $totalFalha++;
    }

    // Pequeno intervalo para evitar estouro de conexoes em sequencia.
    usleep(150000);
}

$conn->close();

if (!$modoRelatorio) {
    if ($totalFalha === 0) {
        definir_mensagem_flash('sucesso', 'Sincronizacao concluida com sucesso. ' . $totalSucesso . ' impressora(s) atualizada(s).');
    } elseif ($totalSucesso === 0) {
        definir_mensagem_flash('erro', 'Sincronizacao concluida com falhas. Nenhuma impressora foi atualizada.');
    } else {
        definir_mensagem_flash('erro', 'Sincronizacao concluida com alertas. Sucesso: ' . $totalSucesso . ' | Falhas: ' . $totalFalha . '.');
    }

    $url = 'impressoras.php';
    if ($retornoBusca !== '') {
        $url .= '?' . http_build_query(['busca' => $retornoBusca]);
    }
    header('Location: ' . $url);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronizacao de Impressoras</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #0f172a; }
        h1 { margin-bottom: 8px; }
        .resumo { margin-bottom: 20px; }
        .resumo strong { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dbe2ea; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        .ok { color: #166534; font-weight: 700; }
        .erro { color: #b91c1c; font-weight: 700; }
        .muted { color: #64748b; }
        .acoes { margin-top: 18px; }
        .acoes a { color: #1d4ed8; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <h1>Sincronizacao de Impressoras</h1>
    <div class="resumo">
        <strong>Total: <?= (int) count($relatorio) ?></strong>
        <strong>Sucesso: <?= (int) $totalSucesso ?></strong>
        <strong>Falhas: <?= (int) $totalFalha ?></strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Impressora</th>
                <th>IP</th>
                <th>Status salvo</th>
                <th>Tintas (BK/C/M/Y)</th>
                <th>Resultado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($relatorio)): ?>
                <tr>
                    <td colspan="5" class="muted">Nenhuma impressora cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($relatorio as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($item['nome'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($item['ip'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($item['status'] ?? 'Desconhecido')) ?></td>
                        <td>
                            BK: <?= $item['preto'] !== null ? ((int) $item['preto']) . '%' : '-' ?> |
                            C: <?= $item['ciano'] !== null ? ((int) $item['ciano']) . '%' : '-' ?> |
                            M: <?= $item['magenta'] !== null ? ((int) $item['magenta']) . '%' : '-' ?> |
                            Y: <?= $item['amarelo'] !== null ? ((int) $item['amarelo']) . '%' : '-' ?>
                        </td>
                        <td>
                            <?php if (!empty($item['ok'])): ?>
                                <span class="ok">OK</span>
                            <?php else: ?>
                                <span class="erro">ERRO</span>
                                <?php if (!empty($item['erro'])): ?>
                                    <div class="muted"><?= htmlspecialchars((string) $item['erro']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="acoes">
        <a href="impressoras.php">Voltar para impressoras</a>
    </div>
</body>
</html>
