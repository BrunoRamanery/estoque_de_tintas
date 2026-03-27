<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';

$mensagem = obter_mensagem_flash();
$cadastroInicial = false;

$resultadoTotal = $conn->query('SELECT COUNT(*) AS total FROM usuarios');
if (!$resultadoTotal) {
    error_log('Falha ao consultar usuarios para tela de login: ' . $conn->error);
    $conn->close();
    if ($mensagem === null) {
        $mensagem = [
            'tipo' => 'erro',
            'texto' => 'Nao foi possivel validar usuarios no momento.',
        ];
    }
} else {
    $linhaTotal = $resultadoTotal->fetch_assoc() ?: ['total' => 0];
    $resultadoTotal->free();
    $conn->close();
    $cadastroInicial = (int) ($linhaTotal['total'] ?? 0) === 0;
}

$csrfToken = obter_token_csrf();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Estoque de Tintas</title>

    <link rel="stylesheet" href="../css/principal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="tela-autenticacao">

<div class="auth-layout">
    <aside class="auth-lateral">
        <div class="auth-marca">
            <div class="auth-badge">
                <i class="fa-solid fa-fill-drip"></i>
            </div>

            <h1>Estoque de Tintas Epson</h1>
            <p>
                Controle seus lotes com mais organizacao, seguranca e praticidade.
                Faca login para acessar o painel de estoque, validade e compras.
            </p>

            <div class="auth-recursos">
                <div class="auth-recurso">
                    <i class="fa-solid fa-layer-group"></i>
                    <div>
                        <strong>Organizacao por lotes</strong>
                        <span>Acompanhe tintas agrupadas por cor, mes e ano de validade.</span>
                    </div>
                </div>

                <div class="auth-recurso">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong>Alertas inteligentes</strong>
                        <span>Veja rapidamente o que esta valido, o que vence em breve e o que precisa de compra.</span>
                    </div>
                </div>

                <div class="auth-recurso">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Acesso protegido</strong>
                        <span>Somente usuarios autorizados podem entrar e gerenciar o sistema.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-rodape">
            Sistema interno de controle de tintas
        </div>
    </aside>

    <section class="auth-card">
        <div class="auth-conteudo">
            <div class="auth-topo">
                <span class="auth-tag">
                    <i class="fa-solid fa-user-lock"></i>
                    Acesso ao sistema
                </span>
                <h2>Login</h2>
                <p>Entre com seu e-mail e senha para acessar o painel.</p>
            </div>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === '1'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    E-mail ou senha invalidos.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sistema'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    Nao foi possivel concluir o login no momento.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'limite'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-clock"></i>
                    Muitas tentativas. Aguarde alguns minutos e tente novamente.
                </div>
            <?php endif; ?>

            <?php if (!empty($mensagem)): ?>
                <div class="auth-alerta <?= $mensagem['tipo'] === 'sucesso' ? 'auth-alerta-sucesso' : 'auth-alerta-erro' ?>">
                    <i class="fa-solid <?= $mensagem['tipo'] === 'sucesso' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= e($mensagem['texto']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso'): ?>
                <div class="auth-alerta auth-alerta-sucesso">
                    <i class="fa-solid fa-circle-check"></i>
                    Cadastro realizado com sucesso. Agora faca seu login.
                </div>
            <?php endif; ?>

            <form method="POST" action="processa_login.php" class="auth-formulario">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="auth-campo">
                    <label for="email">
                        <i class="fa-solid fa-envelope"></i>
                        E-mail
                    </label>
                    <input type="email" id="email" name="email" placeholder="Digite seu e-mail" required maxlength="150">
                </div>

                <div class="auth-campo">
                    <label for="senha">
                        <i class="fa-solid fa-lock"></i>
                        Senha
                    </label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                </div>

                <div class="auth-acoes">
                    <?php if ($cadastroInicial): ?>
                        <a href="cadastro_usuario.php" class="auth-link">
                            <i class="fa-solid fa-user-plus"></i>
                            Criar primeiro usuario
                        </a>
                    <?php endif; ?>

                    <button type="submit" class="auth-botao">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Entrar
                    </button>
                </div>
            </form>

            <div class="auth-rodape-form">
                <?= $cadastroInicial ? 'Cadastre o primeiro usuario para ativar o sistema.' : 'Acesso restrito ao sistema de estoque.' ?>
            </div>
        </div>
    </section>
</div>

</body>
</html>
