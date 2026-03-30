<?php
require_once __DIR__ . '/app/utilidades.php';
require_once __DIR__ . '/usuario/verificar_login.php';

$usuarioNome = (string) ($_SESSION['usuario_nome'] ?? 'Usuario');
$usuarioNivel = (string) ($_SESSION['usuario_nivel'] ?? 'comum');
$usuarioNivelLabel = $usuarioNivel === 'admin' ? 'Administrador' : 'Usuario';
$usuarioAdmin = usuario_e_admin();

$tituloPagina = 'Conta';
$caminhoCss = 'css/principal.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<body class="tela-sistema">
    <?php
        $basePrefix = "";
        $paginaAtual = "conta";
        $paginaTitulo = "Conta";
        $paginaDescricao = "Dados da sessao e atalhos de acesso";
        require __DIR__ . "/includes/topo_sistema.php";
    ?>

    <div class="container dashboard-clean">
        <section class="bloco-detalhes">
            <div class="bloco-detalhes-topo">
                <div class="icone-bloco">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h2>Conta</h2>
                    <p>Informacoes da sessao ativa e acessos administrativos.</p>
                </div>
            </div>
        </section>

        <section class="cards-resumo cards-resumo-clean">
            <div class="card-resumo card-compra-breve">
                <div class="icone-resumo"><i class="fa-solid fa-user"></i></div>
                <div>
                    <strong><?= e($usuarioNome) ?></strong>
                    <span>Usuario conectado</span>
                </div>
            </div>

            <div class="card-resumo card-breve">
                <div class="icone-resumo"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                    <strong><?= e($usuarioNivelLabel) ?></strong>
                    <span>Nivel de acesso</span>
                </div>
            </div>
        </section>

        <section class="cards-menu">
            <div class="card-menu">
                <h3><i class="fa-solid fa-users"></i> Gerenciar usuarios</h3>
                <p>
                    <?php if ($usuarioAdmin): ?>
                        Cadastre novos usuarios e controle os acessos do sistema.
                    <?php else: ?>
                        Apenas administradores podem cadastrar novos usuarios.
                    <?php endif; ?>
                </p>

                <?php if ($usuarioAdmin): ?>
                    <a href="usuario/cadastro_usuario.php" class="btn-entrar">
                        <i class="fa-solid fa-arrow-right"></i> Acessar cadastro
                    </a>
                <?php else: ?>
                    <a href="index.php" class="btn-entrar">
                        <i class="fa-solid fa-arrow-right"></i> Voltar ao painel
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>
    </div>
</div>
</body>
</html>
