<?php
/**
 * Repositorio de tintas.
 *
 * Esta camada fala somente com o banco de dados.
 */

function repo_tintas_buscar_por_modelo(mysqli $conn, string $modelo, string $busca = ''): array
{
    $sql = 'SELECT id, COALESCE(impressora, \'\') AS impressora, modelo, cor, quantidade, mes, ano
            FROM tinta
            WHERE modelo = ?';
    $types = 's';
    $params = [$modelo];

    if ($busca !== '') {
        $sql .= ' AND (COALESCE(impressora, \'\') LIKE ? OR cor LIKE ? OR CAST(mes AS CHAR) LIKE ? OR CAST(ano AS CHAR) LIKE ?)';
        $buscaLike = '%' . $busca . '%';
        $types .= 'ssss';
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $params[] = $buscaLike;
    }

    $sql .= ' ORDER BY ano ASC, mes ASC, cor ASC, COALESCE(impressora, \'\') ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta de detalhes.');
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }

    $stmt->close();
    return $itens;
}
/**
 * Repositorio da tabela de tintas.
 *
 * Aqui ficam as consultas ao banco de dados.
 * As paginas chamam estas funcoes em vez de montar SQL direto.
 */

function buscar_modelos_agrupados(mysqli $conn, array $filtros = []): array
{
    $busca = trim((string) ($filtros['busca'] ?? ''));
    $modelo = trim((string) ($filtros['modelo'] ?? ''));
    $cor = trim((string) ($filtros['cor'] ?? ''));

    $sql = "
        SELECT 
            modelo,
            COUNT(*) AS total_registros,
            SUM(quantidade) AS total_quantidade,
            COUNT(DISTINCT cor) AS total_cores,
            MIN(CONCAT(ano, '-', LPAD(mes, 2, '0'))) AS menor_validade
        FROM tinta
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if ($busca !== '') {
        $sql .= ' AND (COALESCE(impressora, \'\') LIKE ? OR modelo LIKE ? OR cor LIKE ?)';
        $buscaLike = '%' . $busca . '%';
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $types .= 'sss';
    }

    if ($modelo !== '') {
        $sql .= ' AND modelo = ?';
        $params[] = $modelo;
        $types .= 's';
    }

    if ($cor !== '') {
        $sql .= ' AND cor = ?';
        $params[] = $cor;
        $types .= 's';
    }

    $sql .= ' GROUP BY modelo ORDER BY modelo ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta principal: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $itens = [];

    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }

    $stmt->close();
    return $itens;
}

function buscar_modelos_completo(mysqli $conn, array $filtros = []): array
{
    $busca = trim((string) ($filtros['busca'] ?? ''));
    $modelo = trim((string) ($filtros['modelo'] ?? ''));
    $cor = trim((string) ($filtros['cor'] ?? ''));

    $sql = "
        SELECT 
            modelo,
            cor,
            COALESCE(SUM(quantidade), 0) as total_cor,
            MIN(CONCAT(ano, '-', LPAD(mes,2,'0'))) as menor_validade_cor
        FROM tinta
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if ($busca !== '') {
        $sql .= ' AND (COALESCE(impressora, \'\') LIKE ? OR modelo LIKE ? OR cor LIKE ?)';
        $buscaLike = '%' . $busca . '%';
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $types .= 'sss';
    }

    if ($modelo !== '') {
        $sql .= ' AND modelo = ?';
        $params[] = $modelo;
        $types .= 's';
    }

    if ($cor !== '') {
        $sql .= ' AND cor = ?';
        $params[] = $cor;
        $types .= 's';
    }

    $sql .= ' GROUP BY modelo, cor ORDER BY modelo ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao carregar modelos completos: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $modelos = [];

    while ($row = $result->fetch_assoc()) {
        $modeloRow = (string) $row['modelo'];

        if (!isset($modelos[$modeloRow])) {
            $modelos[$modeloRow] = [
                'modelo' => $modeloRow,
                'total' => 0,
                'menor_validade' => null,
                'cores' => [],
            ];
        }

        $modelos[$modeloRow]['total'] += (int) $row['total_cor'];
        $modelos[$modeloRow]['cores'][] = [
            'cor' => $row['cor'],
            'quantidade' => (int) $row['total_cor'],
            'validade' => $row['menor_validade_cor'],
        ];

        if (
            $modelos[$modeloRow]['menor_validade'] === null ||
            $row['menor_validade_cor'] < $modelos[$modeloRow]['menor_validade']
        ) {
            $modelos[$modeloRow]['menor_validade'] = $row['menor_validade_cor'];
        }
    }

    $stmt->close();
    return array_values($modelos);
}

function buscar_opcoes_modelos(mysqli $conn): array
{
    $result = $conn->query('SELECT DISTINCT modelo FROM tinta ORDER BY modelo ASC');
    if (!$result) {
        throw new RuntimeException('Erro ao carregar lista de modelos: ' . $conn->error);
    }

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = (string) $row['modelo'];
    }

    return $itens;
}

function buscar_opcoes_cores(mysqli $conn): array
{
    $result = $conn->query('SELECT DISTINCT cor FROM tinta ORDER BY cor ASC');
    if (!$result) {
        throw new RuntimeException('Erro ao carregar lista de cores: ' . $conn->error);
    }

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = (string) $row['cor'];
    }

    return $itens;
}

function buscar_dados_resumo_tintas(mysqli $conn): array
{
    $result = $conn->query('SELECT mes, ano, quantidade FROM tinta');
    if (!$result) {
        throw new RuntimeException('Erro ao carregar resumo geral: ' . $conn->error);
    }

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }

    return $itens;
}

function buscar_lista_compras(mysqli $conn): array
{
    $sql = '
        SELECT modelo, cor, SUM(quantidade) AS quantidade_total
        FROM tinta
        GROUP BY modelo, cor
        ORDER BY modelo ASC, cor ASC
    ';

    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Erro ao carregar lista de compras: ' . $conn->error);
    }

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }

    return $itens;
}

function buscar_tintas_por_modelo(mysqli $conn, string $modelo): array
{
    $stmt = $conn->prepare(
        'SELECT id, COALESCE(impressora, \'\') AS impressora, modelo, cor, quantidade, mes, ano
         FROM tinta
         WHERE modelo = ?
         ORDER BY ano ASC, mes ASC, cor ASC, COALESCE(impressora, \'\') ASC'
    );

    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta: ' . $conn->error);
    }

    $stmt->bind_param('s', $modelo);
    $stmt->execute();
    $result = $stmt->get_result();
    $itens = [];

    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }

    $stmt->close();
    return $itens;
}

function buscar_tinta_por_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT id, COALESCE(impressora, \'\') AS impressora, modelo, cor, quantidade, mes, ano FROM tinta WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Erro ao carregar o registro.');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $registro = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $registro;
}

function normalizar_lista_impressoras(string ...$valores): string
{
    $itens = [];

    foreach ($valores as $valor) {
        $partes = preg_split('/\s*[\/,;|]+\s*/u', trim($valor)) ?: [];

        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte === '') {
                continue;
            }

            $chave = mb_strtolower($parte);
            if (!isset($itens[$chave])) {
                $itens[$chave] = $parte;
            }
        }
    }

    return implode(' / ', array_values($itens));
}

function buscar_tinta_por_chave_estoque(mysqli $conn, string $modelo, string $cor, int $mes, int $ano, ?int $ignorarId = null): ?array
{
    $sql = 'SELECT id, COALESCE(impressora, \'\') AS impressora, modelo, cor, quantidade, mes, ano
            FROM tinta
            WHERE modelo = ? AND cor = ? AND mes = ? AND ano = ?';

    if ($ignorarId !== null) {
        $sql .= ' AND id <> ?';
    }

    $sql .= ' ORDER BY id ASC LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar a busca de estoque.');
    }

    if ($ignorarId !== null) {
        $stmt->bind_param('ssiii', $modelo, $cor, $mes, $ano, $ignorarId);
    } else {
        $stmt->bind_param('ssii', $modelo, $cor, $mes, $ano);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $registro = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $registro;
}

function inserir_tinta(mysqli $conn, array $dadosFormulario, array $dadosParseados): array
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn->begin_transaction();

    try {
        $impressoraNormalizada = normalizar_lista_impressoras((string) ($dadosFormulario['impressora'] ?? ''));
        $registroExistente = buscar_tinta_por_chave_estoque(
            $conn,
            $dadosFormulario['modelo'],
            $dadosFormulario['cor'],
            $dadosParseados['mes'],
            $dadosParseados['ano']
        );

        if ($registroExistente) {
            $impressoraCombinada = normalizar_lista_impressoras(
                (string) ($registroExistente['impressora'] ?? ''),
                $impressoraNormalizada
            );

            $novaQuantidade = (int) $registroExistente['quantidade'] + (int) $dadosParseados['quantidade'];

            $stmt = $conn->prepare('UPDATE tinta SET impressora = ?, quantidade = ? WHERE id = ?');
            if (!$stmt) {
                throw new RuntimeException('Erro ao preparar a consolidacao do cadastro.');
            }

            $stmt->bind_param('sii', $impressoraCombinada, $novaQuantidade, $registroExistente['id']);
            if (!$stmt->execute()) {
                $erroExecucao = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Erro ao consolidar cadastro: ' . $erroExecucao);
            }
            $stmt->close();

            $conn->commit();

            return [
                'ok' => true,
                'acao' => 'mesclado',
                'id' => (int) $registroExistente['id'],
            ];
        }

        $stmt = $conn->prepare(
            'INSERT INTO tinta (impressora, modelo, cor, quantidade, mes, ano) VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar o cadastro.');
        }

        $stmt->bind_param(
            'sssiii',
            $impressoraNormalizada,
            $dadosFormulario['modelo'],
            $dadosFormulario['cor'],
            $dadosParseados['quantidade'],
            $dadosParseados['mes'],
            $dadosParseados['ano']
        );

        if (!$stmt->execute()) {
            $erroExecucao = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Erro ao inserir cadastro: ' . $erroExecucao);
        }
        $novoId = (int) $conn->insert_id;
        $stmt->close();

        $conn->commit();

        return [
            'ok' => true,
            'acao' => 'inserido',
            'id' => $novoId,
        ];
    } catch (Throwable $erro) {
        $conn->rollback();
        throw new RuntimeException($erro->getMessage(), 0, $erro);
    }
}

function atualizar_tinta(mysqli $conn, int $id, array $dadosFormulario, array $dadosParseados): array
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn->begin_transaction();

    try {
        $impressoraNormalizada = normalizar_lista_impressoras((string) ($dadosFormulario['impressora'] ?? ''));
        $registroAtual = buscar_tinta_por_id($conn, $id);
        if (!$registroAtual) {
            throw new RuntimeException('Registro nao encontrado para atualizacao.');
        }

        $registroExistente = buscar_tinta_por_chave_estoque(
            $conn,
            $dadosFormulario['modelo'],
            $dadosFormulario['cor'],
            $dadosParseados['mes'],
            $dadosParseados['ano'],
            $id
        );

        if ($registroExistente) {
            $impressoraCombinada = normalizar_lista_impressoras(
                (string) ($registroExistente['impressora'] ?? ''),
                $impressoraNormalizada
            );
            $novaQuantidade = (int) $registroExistente['quantidade'] + (int) $dadosParseados['quantidade'];

            $stmtAtualizaExistente = $conn->prepare('UPDATE tinta SET impressora = ?, quantidade = ? WHERE id = ?');
            if (!$stmtAtualizaExistente) {
                throw new RuntimeException('Erro ao preparar a consolidacao da atualizacao.');
            }

            $stmtAtualizaExistente->bind_param('sii', $impressoraCombinada, $novaQuantidade, $registroExistente['id']);
            if (!$stmtAtualizaExistente->execute()) {
                $erroExecucao = $stmtAtualizaExistente->error;
                $stmtAtualizaExistente->close();
                throw new RuntimeException('Erro ao consolidar atualizacao: ' . $erroExecucao);
            }
            $stmtAtualizaExistente->close();

            $stmtExcluiAtual = $conn->prepare('DELETE FROM tinta WHERE id = ?');
            if (!$stmtExcluiAtual) {
                throw new RuntimeException('Erro ao preparar a remocao do registro consolidado.');
            }

            $stmtExcluiAtual->bind_param('i', $id);
            if (!$stmtExcluiAtual->execute()) {
                $erroExecucao = $stmtExcluiAtual->error;
                $stmtExcluiAtual->close();
                throw new RuntimeException('Erro ao remover registro consolidado: ' . $erroExecucao);
            }
            $stmtExcluiAtual->close();

            $conn->commit();

            return [
                'ok' => true,
                'acao' => 'mesclado',
                'id' => (int) $registroExistente['id'],
            ];
        }

        $stmt = $conn->prepare(
            'UPDATE tinta SET impressora = ?, modelo = ?, cor = ?, quantidade = ?, mes = ?, ano = ? WHERE id = ?'
        );

        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar a atualizacao.');
        }

        $stmt->bind_param(
            'sssiiii',
            $impressoraNormalizada,
            $dadosFormulario['modelo'],
            $dadosFormulario['cor'],
            $dadosParseados['quantidade'],
            $dadosParseados['mes'],
            $dadosParseados['ano'],
            $id
        );

        if (!$stmt->execute()) {
            $erroExecucao = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Erro ao atualizar cadastro: ' . $erroExecucao);
        }
        $stmt->close();

        $conn->commit();

        return [
            'ok' => true,
            'acao' => 'atualizado',
            'id' => $id,
        ];
    } catch (Throwable $erro) {
        $conn->rollback();
        throw new RuntimeException($erro->getMessage(), 0, $erro);
    }
}

function excluir_tinta(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare('DELETE FROM tinta WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar a exclusao.');
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
