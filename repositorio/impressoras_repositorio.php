<?php
/**
 * Repositorio de impressoras.
 *
 * Esta camada fala somente com o banco de dados.
 */

function repo_impressoras_listar(mysqli $conn, string $busca = ''): array
{
    $sql = 'SELECT id, nome, modelo, ip, localizacao, observacao, status_impressora, tinta_preto, tinta_ciano, tinta_magenta, tinta_amarelo FROM impressoras';
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
        ];
    }

    $stmt->close();
    return $itens;
}

function repo_impressoras_buscar_por_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT id, nome, modelo, ip, localizacao, observacao, status_impressora, tinta_preto, tinta_ciano, tinta_magenta, tinta_amarelo FROM impressoras WHERE id = ?');
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
