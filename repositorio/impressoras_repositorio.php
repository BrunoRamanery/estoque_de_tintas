<?php
/**
 * Repositorio de impressoras.
 *
 * Esta camada fala somente com o banco de dados.
 */

function repo_impressoras_listar(mysqli $conn, string $busca = ''): array
{
    $sql = 'SELECT
                id,
                nome,
                modelo,
                ip,
                localizacao,
                observacao,
                status_impressora,
                tinta_preto,
                tinta_ciano,
                tinta_magenta,
                tinta_amarelo,
                paginas_total,
                paginas_pb,
                paginas_cor,
                a4_pb_simples,
                a4_cor_simples,
                a4_pb_duplex,
                a4_cor_duplex,
                a3_pb_simples,
                a3_cor_simples,
                a3_pb_duplex,
                a3_cor_duplex,
                ultima_atualizacao
            FROM impressoras';
    $tipos = '';
    $parametros = [];

    if ($busca !== '') {
        $sql .= ' WHERE nome LIKE ? OR modelo LIKE ? OR ip LIKE ? OR localizacao LIKE ?';
        $buscaLike = '%' . $busca . '%';
        $tipos = 'ssss';
        $parametros = [$buscaLike, $buscaLike, $buscaLike, $buscaLike];
    }

    $sql .= ' ORDER BY nome ASC, modelo ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta de impressoras.');
    }

    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$parametros);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nome' => trim((string) ($row['nome'] ?? '')),
            'modelo' => trim((string) ($row['modelo'] ?? '')),
            'ip' => trim((string) ($row['ip'] ?? '')),
            'localizacao' => trim((string) ($row['localizacao'] ?? '')),
            'observacao' => trim((string) ($row['observacao'] ?? '')),
            'status_impressora' => trim((string) ($row['status_impressora'] ?? '')),
            'tinta_preto' => ($row['tinta_preto'] !== null && $row['tinta_preto'] !== '') ? (int) $row['tinta_preto'] : null,
            'tinta_ciano' => ($row['tinta_ciano'] !== null && $row['tinta_ciano'] !== '') ? (int) $row['tinta_ciano'] : null,
            'tinta_magenta' => ($row['tinta_magenta'] !== null && $row['tinta_magenta'] !== '') ? (int) $row['tinta_magenta'] : null,
            'tinta_amarelo' => ($row['tinta_amarelo'] !== null && $row['tinta_amarelo'] !== '') ? (int) $row['tinta_amarelo'] : null,
            'paginas_total' => ($row['paginas_total'] !== null && $row['paginas_total'] !== '') ? (int) $row['paginas_total'] : null,
            'paginas_pb' => ($row['paginas_pb'] !== null && $row['paginas_pb'] !== '') ? (int) $row['paginas_pb'] : null,
            'paginas_cor' => ($row['paginas_cor'] !== null && $row['paginas_cor'] !== '') ? (int) $row['paginas_cor'] : null,
            'a4_pb_simples' => ($row['a4_pb_simples'] !== null && $row['a4_pb_simples'] !== '') ? (int) $row['a4_pb_simples'] : null,
            'a4_cor_simples' => ($row['a4_cor_simples'] !== null && $row['a4_cor_simples'] !== '') ? (int) $row['a4_cor_simples'] : null,
            'a4_pb_duplex' => ($row['a4_pb_duplex'] !== null && $row['a4_pb_duplex'] !== '') ? (int) $row['a4_pb_duplex'] : null,
            'a4_cor_duplex' => ($row['a4_cor_duplex'] !== null && $row['a4_cor_duplex'] !== '') ? (int) $row['a4_cor_duplex'] : null,
            'a3_pb_simples' => ($row['a3_pb_simples'] !== null && $row['a3_pb_simples'] !== '') ? (int) $row['a3_pb_simples'] : null,
            'a3_cor_simples' => ($row['a3_cor_simples'] !== null && $row['a3_cor_simples'] !== '') ? (int) $row['a3_cor_simples'] : null,
            'a3_pb_duplex' => ($row['a3_pb_duplex'] !== null && $row['a3_pb_duplex'] !== '') ? (int) $row['a3_pb_duplex'] : null,
            'a3_cor_duplex' => ($row['a3_cor_duplex'] !== null && $row['a3_cor_duplex'] !== '') ? (int) $row['a3_cor_duplex'] : null,
            'ultima_atualizacao' => trim((string) ($row['ultima_atualizacao'] ?? '')),
        ];
    }

    $stmt->close();
    return $itens;
}

function repo_impressoras_buscar_por_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        'SELECT
            id,
            nome,
            modelo,
            ip,
            localizacao,
            observacao,
            status_impressora,
            tinta_preto,
            tinta_ciano,
            tinta_magenta,
            tinta_amarelo,
            paginas_total,
            paginas_pb,
            paginas_cor,
            a4_pb_simples,
            a4_cor_simples,
            a4_pb_duplex,
            a4_cor_duplex,
            a3_pb_simples,
            a3_cor_simples,
            a3_pb_duplex,
            a3_cor_duplex,
            ultima_atualizacao
         FROM impressoras
         WHERE id = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar detalhes da impressora.');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'nome' => trim((string) ($row['nome'] ?? '')),
        'modelo' => trim((string) ($row['modelo'] ?? '')),
        'ip' => trim((string) ($row['ip'] ?? '')),
        'localizacao' => trim((string) ($row['localizacao'] ?? '')),
        'observacao' => trim((string) ($row['observacao'] ?? '')),
        'status_impressora' => trim((string) ($row['status_impressora'] ?? '')),
        'tinta_preto' => ($row['tinta_preto'] !== null && $row['tinta_preto'] !== '') ? (int) $row['tinta_preto'] : null,
        'tinta_ciano' => ($row['tinta_ciano'] !== null && $row['tinta_ciano'] !== '') ? (int) $row['tinta_ciano'] : null,
        'tinta_magenta' => ($row['tinta_magenta'] !== null && $row['tinta_magenta'] !== '') ? (int) $row['tinta_magenta'] : null,
        'tinta_amarelo' => ($row['tinta_amarelo'] !== null && $row['tinta_amarelo'] !== '') ? (int) $row['tinta_amarelo'] : null,
        'paginas_total' => ($row['paginas_total'] !== null && $row['paginas_total'] !== '') ? (int) $row['paginas_total'] : null,
        'paginas_pb' => ($row['paginas_pb'] !== null && $row['paginas_pb'] !== '') ? (int) $row['paginas_pb'] : null,
        'paginas_cor' => ($row['paginas_cor'] !== null && $row['paginas_cor'] !== '') ? (int) $row['paginas_cor'] : null,
        'a4_pb_simples' => ($row['a4_pb_simples'] !== null && $row['a4_pb_simples'] !== '') ? (int) $row['a4_pb_simples'] : null,
        'a4_cor_simples' => ($row['a4_cor_simples'] !== null && $row['a4_cor_simples'] !== '') ? (int) $row['a4_cor_simples'] : null,
        'a4_pb_duplex' => ($row['a4_pb_duplex'] !== null && $row['a4_pb_duplex'] !== '') ? (int) $row['a4_pb_duplex'] : null,
        'a4_cor_duplex' => ($row['a4_cor_duplex'] !== null && $row['a4_cor_duplex'] !== '') ? (int) $row['a4_cor_duplex'] : null,
        'a3_pb_simples' => ($row['a3_pb_simples'] !== null && $row['a3_pb_simples'] !== '') ? (int) $row['a3_pb_simples'] : null,
        'a3_cor_simples' => ($row['a3_cor_simples'] !== null && $row['a3_cor_simples'] !== '') ? (int) $row['a3_cor_simples'] : null,
        'a3_pb_duplex' => ($row['a3_pb_duplex'] !== null && $row['a3_pb_duplex'] !== '') ? (int) $row['a3_pb_duplex'] : null,
        'a3_cor_duplex' => ($row['a3_cor_duplex'] !== null && $row['a3_cor_duplex'] !== '') ? (int) $row['a3_cor_duplex'] : null,
        'ultima_atualizacao' => trim((string) ($row['ultima_atualizacao'] ?? '')),
    ];
}

function repo_impressoras_inserir(mysqli $conn, array $dados): int
{
    $sql = 'INSERT INTO impressoras (nome, modelo, ip, localizacao, observacao) VALUES (?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar cadastro da impressora.');
    }

    $stmt->bind_param('sssss', $dados['nome'], $dados['modelo'], $dados['ip'], $dados['localizacao'], $dados['observacao']);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao cadastrar impressora: ' . $erro);
    }

    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function repo_impressoras_atualizar(mysqli $conn, int $id, array $dados): bool
{
    $sql = 'UPDATE impressoras SET nome = ?, modelo = ?, ip = ?, localizacao = ?, observacao = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar atualizacao da impressora.');
    }

    $stmt->bind_param('sssssi', $dados['nome'], $dados['modelo'], $dados['ip'], $dados['localizacao'], $dados['observacao'], $id);
    $ok = $stmt->execute();
    $erro = $stmt->error;
    $stmt->close();

    if (!$ok) {
        throw new RuntimeException('Erro ao atualizar impressora: ' . $erro);
    }

    return true;
}

function repo_impressoras_excluir(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare('DELETE FROM impressoras WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar exclusao da impressora.');
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $erro = $stmt->error;
    $stmt->close();

    if (!$ok) {
        throw new RuntimeException('Erro ao excluir impressora: ' . $erro);
    }

    return true;
}
?>
