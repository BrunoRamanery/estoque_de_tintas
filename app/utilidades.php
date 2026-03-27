<?php
/**
 * Funcoes utilitarias do sistema.
 *
 * Este arquivo concentra:
 * - sessao e seguranca
 * - funcoes de apoio para HTML
 * - mensagens flash
 * - validacao dos formularios
 * - regras simples de status (validade e compra)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    $usaHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
    );

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $usaHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function obter_token_csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function validar_csrf_ou_encerrar(string $csrfRecebido): void
{
    $csrfSessao = (string) ($_SESSION['csrf_token'] ?? '');

    if ($csrfSessao === '' || !hash_equals($csrfSessao, $csrfRecebido)) {
        http_response_code(400);
        exit('Requisicao invalida.');
    }
}

function usuario_esta_logado(): bool
{
    return isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0;
}

function usuario_e_admin(): bool
{
    return usuario_esta_logado() && (string) ($_SESSION['usuario_nivel'] ?? '') === 'admin';
}

function definir_mensagem_flash(string $tipo, string $texto): void
{
    $_SESSION['mensagem'] = [
        'tipo' => $tipo,
        'texto' => $texto,
    ];
}

function obter_mensagem_flash(): ?array
{
    $mensagem = $_SESSION['mensagem'] ?? null;
    unset($_SESSION['mensagem']);

    return is_array($mensagem) ? $mensagem : null;
}

function dados_vazios_tinta(): array
{
    return [
        'impressora' => '',
        'modelo' => '',
        'cor' => '',
        'quantidade_raw' => '',
        'mes_raw' => '',
        'ano_raw' => '',
    ];
}

function dados_tinta_da_fonte(array $source): array
{
    return [
        'impressora' => trim((string) ($source['impressora'] ?? '')),
        'modelo' => trim((string) ($source['modelo'] ?? '')),
        'cor' => trim((string) ($source['cor'] ?? '')),
        'quantidade_raw' => trim((string) ($source['quantidade'] ?? '')),
        'mes_raw' => trim((string) ($source['mes'] ?? '')),
        'ano_raw' => trim((string) ($source['ano'] ?? '')),
    ];
}

function validar_dados_tinta(array $dados): array
{
    $erros = [];

    $impressora = trim((string) ($dados['impressora'] ?? ''));
    $modelo = trim((string) ($dados['modelo'] ?? ''));
    $cor = trim((string) ($dados['cor'] ?? ''));
    $quantidadeBruta = $dados['quantidade'] ?? $dados['quantidade_raw'] ?? null;
    $mesBruto = $dados['mes'] ?? $dados['mes_raw'] ?? null;
    $anoBruto = $dados['ano'] ?? $dados['ano_raw'] ?? null;

    if ($impressora === '') {
        $erros[] = 'O campo impressora e obrigatorio.';
    } elseif (mb_strlen($impressora) > 100) {
        $erros[] = 'A impressora deve ter no maximo 100 caracteres.';
    }

    if ($modelo === '') {
        $erros[] = 'O campo modelo e obrigatorio.';
    } elseif (mb_strlen($modelo) > 100) {
        $erros[] = 'O campo modelo deve ter no maximo 100 caracteres.';
    }

    if ($cor === '') {
        $erros[] = 'O campo cor e obrigatorio.';
    } elseif (mb_strlen($cor) > 30) {
        $erros[] = 'O campo cor deve ter no maximo 30 caracteres.';
    }

    $quantidade = filter_var($quantidadeBruta, FILTER_VALIDATE_INT);
    if ($quantidade === false || $quantidade < 0 || $quantidade > 9999) {
        $erros[] = 'A quantidade deve ser um numero valido.';
    }

    $mes = filter_var($mesBruto, FILTER_VALIDATE_INT);
    if ($mes === false || $mes < 1 || $mes > 12) {
        $erros[] = 'Mes invalido.';
    }

    $ano = filter_var($anoBruto, FILTER_VALIDATE_INT);
    if ($ano === false || $ano < 2000 || $ano > 2100) {
        $erros[] = 'Ano invalido.';
    }

    return $erros;
}

function parsear_dados_tinta(array $dados): array
{
    $quantidadeBruta = $dados['quantidade'] ?? $dados['quantidade_raw'] ?? null;
    $mesBruto = $dados['mes'] ?? $dados['mes_raw'] ?? null;
    $anoBruto = $dados['ano'] ?? $dados['ano_raw'] ?? null;

    $quantidade = filter_var($quantidadeBruta, FILTER_VALIDATE_INT);
    $mes = filter_var($mesBruto, FILTER_VALIDATE_INT);
    $ano = filter_var($anoBruto, FILTER_VALIDATE_INT);

    return [
        'quantidade' => $quantidade === false ? null : (int) $quantidade,
        'mes' => $mes === false ? null : (int) $mes,
        'ano' => $ano === false ? null : (int) $ano,
    ];
}

function indice_competencia_atual(): int
{
    return ((int) date('Y') * 12) + (int) date('n');
}

function formatar_competencia(string $competencia): string
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $competencia, $matches) !== 1) {
        return $competencia;
    }

    return $matches[2] . '/' . $matches[1];
}

function obter_status_validade(int $mes, int $ano): array
{
    $referenciaAtual = indice_competencia_atual();
    $referenciaItem = ($ano * 12) + $mes;

    if ($referenciaItem < $referenciaAtual) {
        return [
            'chave' => 'vencida',
            'label' => 'Vencida',
            'classe' => 'status-vencida',
            'icone' => 'fa-circle-xmark',
        ];
    }

    if ($referenciaItem <= $referenciaAtual + 3) {
        return [
            'chave' => 'proxima',
            'label' => 'Vence em breve',
            'classe' => 'status-vence-breve',
            'icone' => 'fa-clock',
        ];
    }

    return [
        'chave' => 'ok',
        'label' => 'Valida',
        'classe' => 'status-valida',
        'icone' => 'fa-circle-check',
    ];
}

function obter_status_validade_por_competencia(string $competencia): array
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $competencia, $matches) !== 1) {
        return [
            'chave' => 'ok',
            'label' => 'Valida',
            'classe' => 'status-valida',
            'icone' => 'fa-circle-check',
        ];
    }

    return obter_status_validade((int) $matches[2], (int) $matches[1]);
}

function obter_status_compra(int $quantidade): array
{
    if ($quantidade >= 0 && $quantidade <= 2) {
        return [
            'chave' => 'urgente',
            'label' => 'Comprar urgente',
            'classe' => 'status-compra-urgente',
            'icone' => 'fa-cart-shopping',
        ];
    }

    if ($quantidade >= 3 && $quantidade <= 5) {
        return [
            'chave' => 'baixo',
            'label' => 'Comprar em breve',
            'classe' => 'status-compra-breve',
            'icone' => 'fa-bag-shopping',
        ];
    }

    return [
        'chave' => 'ok',
        'label' => 'Estoque ok',
        'classe' => 'status-estoque-ok',
        'icone' => 'fa-boxes-stacked',
    ];
}

function obter_peso_alerta(array $statusValidade, array $statusCompra): int
{
    if ($statusValidade['chave'] === 'vencida') {
        return 4;
    }

    if ($statusCompra['chave'] === 'urgente') {
        return 3;
    }

    if ($statusValidade['chave'] === 'proxima') {
        return 2;
    }

    if ($statusCompra['chave'] === 'baixo') {
        return 1;
    }

    return 0;
}

function obter_aparencia_alerta_por_peso(int $peso): array
{
    if ($peso >= 4) {
        return [
            'classe_tag' => 'tag-vermelha',
            'icone' => 'fa-circle-xmark',
            'prioridade' => 'critica',
        ];
    }

    if ($peso === 3) {
        return [
            'classe_tag' => 'tag-laranja',
            'icone' => 'fa-cart-shopping',
            'prioridade' => 'alta',
        ];
    }

    if ($peso === 2) {
        return [
            'classe_tag' => 'tag-amarela',
            'icone' => 'fa-clock',
            'prioridade' => 'media',
        ];
    }

    if ($peso === 1) {
        return [
            'classe_tag' => 'tag-azul',
            'icone' => 'fa-bag-shopping',
            'prioridade' => 'baixa',
        ];
    }

    return [
        'classe_tag' => 'tag-azul',
        'icone' => 'fa-circle-check',
        'prioridade' => 'ok',
    ];
}

function montar_texto_alerta_cor(string $nomeCor, array $statusValidade, array $statusCompra, string $validade): string
{
    $partes = [];

    if ($statusCompra['chave'] === 'urgente') {
        $partes[] = 'comprar urgente';
    } elseif ($statusCompra['chave'] === 'baixo') {
        $partes[] = 'comprar em breve';
    }

    if ($statusValidade['chave'] === 'vencida') {
        $partes[] = 'vencida (' . formatar_competencia($validade) . ')';
    } elseif ($statusValidade['chave'] === 'proxima') {
        $partes[] = 'vence em breve (' . formatar_competencia($validade) . ')';
    }

    if (empty($partes)) {
        $partes[] = 'estoque ok';
    }

    return $nomeCor . ': ' . implode(' e ', $partes);
}

function gerar_alertas_por_cor(array $cores): array
{
    $alertas = [];

    foreach ($cores as $cor) {
        $nomeCor = trim((string) ($cor['cor'] ?? 'Cor'));
        $quantidade = (int) ($cor['quantidade'] ?? 0);
        $validade = substr((string) ($cor['validade'] ?? ''), 0, 7);

        $statusCompra = obter_status_compra($quantidade);
        $statusValidade = obter_status_validade_por_competencia($validade);
        $peso = obter_peso_alerta($statusValidade, $statusCompra);

        if ($peso === 0) {
            continue;
        }

        $aparencia = obter_aparencia_alerta_por_peso($peso);

        $alertas[] = [
            'cor' => $nomeCor,
            'quantidade' => $quantidade,
            'validade' => $validade,
            'status_compra' => $statusCompra,
            'status_validade' => $statusValidade,
            'peso' => $peso,
            'prioridade' => $aparencia['prioridade'],
            'classe_tag' => $aparencia['classe_tag'],
            'icone' => $aparencia['icone'],
            'texto' => montar_texto_alerta_cor($nomeCor, $statusValidade, $statusCompra, $validade),
        ];
    }

    usort($alertas, static function (array $a, array $b): int {
        if (($b['peso'] ?? 0) !== ($a['peso'] ?? 0)) {
            return ($b['peso'] ?? 0) <=> ($a['peso'] ?? 0);
        }

        return strcasecmp((string) ($a['cor'] ?? ''), (string) ($b['cor'] ?? ''));
    });

    return $alertas;
}

function obter_classe_prioridade_linha(array $statusValidade, array $statusCompra): string
{
    if ($statusValidade['chave'] === 'vencida' || $statusCompra['chave'] === 'urgente') {
        return 'linha-urgente';
    }

    if ($statusValidade['chave'] === 'proxima' || $statusCompra['chave'] === 'baixo') {
        return 'linha-atencao';
    }

    return 'linha-ok';
}

