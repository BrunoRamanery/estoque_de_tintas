<?php
/**
 * Servicos de impressoras.
 *
 * Esta camada concentra regras e validacoes de negocio.
 */

require_once __DIR__ . '/../repositorio/impressoras_repositorio.php';

function servico_impressoras_validar_dados(array $dados): array
{
    $erros = [];
    $nome = trim((string) ($dados['nome'] ?? ''));
    $modelo = trim((string) ($dados['modelo'] ?? ''));
    $ip = trim((string) ($dados['ip'] ?? ''));
    $localizacao = trim((string) ($dados['localizacao'] ?? ''));
    $observacao = trim((string) ($dados['observacao'] ?? ''));

    if ($nome === '') {
        $erros[] = 'Nome e obrigatorio.';
    } elseif (mb_strlen($nome) > 100) {
        $erros[] = 'Nome deve ter no maximo 100 caracteres.';
    }

    if ($modelo === '') {
        $erros[] = 'Modelo e obrigatorio.';
    } elseif (mb_strlen($modelo) > 100) {
        $erros[] = 'Modelo deve ter no maximo 100 caracteres.';
    }

    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $erros[] = 'IP invalido.';
    } elseif (mb_strlen($ip) > 45) {
        $erros[] = 'IP deve ter no maximo 45 caracteres.';
    }

    if ($localizacao !== '' && mb_strlen($localizacao) > 120) {
        $erros[] = 'Localizacao deve ter no maximo 120 caracteres.';
    }

    if ($observacao !== '' && mb_strlen($observacao) > 255) {
        $erros[] = 'Observacao deve ter no maximo 255 caracteres.';
    }

    return $erros;
}

function servico_impressoras_obter_listagem(mysqli $conn, string $busca = ''): array
{
    $impressoras = repo_impressoras_listar($conn, $busca);

    $modelosUnicos = [];
    $semLocalizacao = 0;

    foreach ($impressoras as $impressora) {
        $modelo = trim((string) ($impressora['modelo'] ?? ''));
        $localizacao = trim((string) ($impressora['localizacao'] ?? ''));

        if ($modelo !== '') {
            $modelosUnicos[strtolower($modelo)] = true;
        }

        if ($localizacao === '') {
            $semLocalizacao++;
        }
    }

    return [
        'impressoras' => $impressoras,
        'total_impressoras' => count($impressoras),
        'total_modelos' => count($modelosUnicos),
        'sem_localizacao' => $semLocalizacao,
    ];
}

function servico_impressoras_buscar_detalhes(mysqli $conn, int $id): ?array
{
    return repo_impressoras_buscar_por_id($conn, $id);
}

function servico_impressoras_salvar(mysqli $conn, array $dados): array
{
    $erros = servico_impressoras_validar_dados($dados);

    if (!empty($erros)) {
        return [
            'ok' => false,
            'erros' => $erros,
        ];
    }

    $id = repo_impressoras_inserir($conn, $dados);

    return [
        'ok' => true,
        'id' => $id,
        'erros' => [],
    ];
}

function servico_impressoras_atualizar(mysqli $conn, int $id, array $dados): array
{
    $atual = repo_impressoras_buscar_por_id($conn, $id);
    if (!$atual) {
        return [
            'ok' => false,
            'erros' => ['Impressora nao encontrada.'],
        ];
    }

    $erros = servico_impressoras_validar_dados($dados);

    if (!empty($erros)) {
        return [
            'ok' => false,
            'erros' => $erros,
        ];
    }

    repo_impressoras_atualizar($conn, $id, $dados);

    return [
        'ok' => true,
        'erros' => [],
    ];
}

function servico_impressoras_excluir(mysqli $conn, int $id): array
{
    $atual = repo_impressoras_buscar_por_id($conn, $id);
    if (!$atual) {
        return [
            'ok' => false,
            'erros' => ['Impressora nao encontrada.'],
        ];
    }

    repo_impressoras_excluir($conn, $id);

    return [
        'ok' => true,
        'erros' => [],
    ];
}
