<?php
require_once __DIR__ . '/../app/utilidades.php';
require_once __DIR__ . '/../conexao.php';

$mensagem = obter_mensagem_flash();

$resultadoTotal = $conn->query('SELECT COUNT(*) AS total FROM usuarios');
if (!$resultadoTotal) {
    error_log('Falha ao consultar usuarios para cadastro: ' . $conn->error);
    $conn->close();
    definir_mensagem_flash('erro', 'Nao foi possivel validar o cadastro de usuarios.');
    header('Location: login.php');
    exit;
}

$linhaTotal = $resultadoTotal->fetch_assoc() ?: ['total' => 0];
$resultadoTotal->free();
$conn->close();

$cadastroInicial = (int) ($linhaTotal['total'] ?? 0) === 0;

if (!$cadastroInicial) {
    require_once __DIR__ . '/verificar_admin.php';
}

$csrfToken = obter_token_csrf();
$voltarHref = $cadastroInicial ? 'login.php' : '../index.php';
$voltarTexto = $cadastroInicial ? 'Voltar para login' : 'Voltar para painel';
$tituloPagina = 'Cadastrar Usuario | Estoque de Tintas';
$caminhoCss = '../css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-br">
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<body class="tela-autenticacao">

<div class="auth-layout">
    <aside class="auth-lateral">
        <div class="auth-marca">
            <div class="auth-badge">
                <i class="fa-solid fa-users"></i>
            </div>

            <h1>Novo usuario</h1>
            <p>
                Cadastre uma pessoa para acessar o sistema e ajudar no controle
                de estoque, validade e movimentacao das tintas.
            </p>

            <div class="auth-recursos">
                <div class="auth-recurso">
                    <i class="fa-solid fa-user-check"></i>
                    <div>
                        <strong>Acesso individual</strong>
                        <span>Cada pessoa entra com seu proprio e-mail e senha.</span>
                    </div>
                </div>

                <div class="auth-recurso">
                    <i class="fa-solid fa-user-shield"></i>
                    <div>
                        <strong>Controle de acesso</strong>
                        <span>
                            <?= $cadastroInicial ? 'O primeiro cadastro sera criado como administrador.' : 'Novos cadastros sao criados como usuario comum.' ?>
                        </span>
                    </div>
                </div>

                <div class="auth-recurso">
                    <i class="fa-solid fa-database"></i>
                    <div>
                        <strong>Cadastro seguro</strong>
                        <span>As senhas ficam protegidas no banco com criptografia.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-rodape">
            Gerenciamento de acesso ao sistema
        </div>
    </aside>

    <section class="auth-card">
        <div class="auth-conteudo">
            <div class="auth-topo">
                <span class="auth-tag">
                    <i class="fa-solid fa-user-plus"></i>
                    Cadastro de acesso
                </span>
                <h2>Cadastrar usuario</h2>
                <p>Preencha os dados abaixo para criar um novo acesso ao sistema.</p>
            </div>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'senhas'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    As senhas informadas nao coincidem.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'email'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    Este e-mail ja esta cadastrado.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'senha_fraca'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    A senha precisa ter pelo menos 8 caracteres.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'dados'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    Revise os dados informados e tente novamente.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sistema'): ?>
                <div class="auth-alerta auth-alerta-erro">
                    <i class="fa-solid fa-circle-xmark"></i>
                    Nao foi possivel concluir o cadastro no momento.
                </div>
            <?php endif; ?>

            <?php if (!empty($mensagem)): ?>
                <div class="auth-alerta <?= $mensagem['tipo'] === 'sucesso' ? 'auth-alerta-sucesso' : 'auth-alerta-erro' ?>">
                    <i class="fa-solid <?= $mensagem['tipo'] === 'sucesso' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= e($mensagem['texto']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="processa_cadastro_usuario.php" class="auth-formulario">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="auth-campo">
                    <label for="nome">
                        <i class="fa-solid fa-user"></i>
                        Nome completo
                    </label>
                    <input type="text" id="nome" name="nome" placeholder="Digite o nome completo" required maxlength="120">
                </div>

                <div class="auth-campo">
                    <label for="email">
                        <i class="fa-solid fa-envelope"></i>
                        E-mail
                    </label>
                    <input type="email" id="email" name="email" placeholder="Digite o e-mail" required maxlength="150">
                </div>

                <div class="auth-linha-dupla">
                    <div class="auth-campo">
                        <label for="senha">
                            <i class="fa-solid fa-lock"></i>
                            Senha
                        </label>
                        <input type="password" id="senha" name="senha" placeholder="Crie uma senha" required maxlength="255">
                    </div>

                    <div class="auth-campo">
                        <label for="confirmar_senha">
                            <i class="fa-solid fa-lock"></i>
                            Confirmar senha
                        </label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha" required maxlength="255">
                    </div>
                </div>

                <div class="auth-acoes">
                    <a href="<?= e($voltarHref) ?>" class="auth-link">
                        <i class="fa-solid fa-arrow-left"></i>
                        <?= e($voltarTexto) ?>
                    </a>

                    <button type="submit" class="auth-botao">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Cadastrar
                    </button>
                </div>
            </form>

            <div class="auth-rodape-form">
                <?php if ($cadastroInicial): ?>
                    Ja tem cadastro? <a href="login.php">Acessar login</a>
                <?php else: ?>
                    Apenas administradores podem criar novos acessos.
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

</body>
</html>
