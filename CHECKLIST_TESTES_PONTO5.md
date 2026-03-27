# Checklist Manual - Ponto 5 (Seguranca e Preparacao)

## 0) Pre-requisitos
- Apache e MySQL ativos no XAMPP.
- Banco e tabelas criados.
- Projeto acessivel em `http://localhost/estoque_de_tintas`.

## 1) Acesso sem login (protecao de rotas)
1. Abrir `http://localhost/estoque_de_tintas/index.php` em aba anonima.
2. Abrir `http://localhost/estoque_de_tintas/impressora/impressoras.php` em aba anonima.
3. Abrir `http://localhost/estoque_de_tintas/detalhes.php?modelo=XP` em aba anonima.

Resultado esperado:
- Todas as rotas redirecionam para `.../usuario/login.php`.

## 2) Login
1. Na tela de login, enviar e-mail/senha invalidos.
2. Enviar e-mail em formato invalido.
3. Enviar credenciais validas.

Resultado esperado:
- Casos invalidos: mensagem amigavel de erro.
- Caso valido: acesso ao painel (`index.php`) com sessao ativa.

## 3) Cadastro de usuario
1. Abrir `usuario/cadastro_usuario.php`.
2. Tentar cadastrar com:
   - campos vazios
   - nome muito longo (>120)
   - email invalido
   - senha curta (<8)
   - senhas diferentes
3. Cadastrar usuario valido.

Resultado esperado:
- Dados invalidos: bloqueio com mensagem amigavel.
- Dado valido: cadastro concluido.

## 4) CSRF
1. Com login ativo, abrir DevTools e remover `csrf_token` de um POST (ex.: cadastrar tinta ou impressora).
2. Submeter formulario sem token.

Resultado esperado:
- Requisicao bloqueada (400) e operacao nao executada.

## 5) Validacao de tintas
1. Abrir `funcoes/cadastrar.php`.
2. Testar:
   - `impressora` > 100 chars
   - `modelo` > 100 chars
   - `cor` > 30 chars
   - `quantidade` > 9999
   - `mes` fora de 1..12
   - `ano` fora de 2000..2100
3. Testar cadastro valido.

Resultado esperado:
- Invalidos: mensagens de validacao.
- Valido: salva com sucesso.

## 6) Validacao de impressoras
1. Abrir `impressora/cadastrar.php`.
2. Testar:
   - `nome` > 100 chars
   - `modelo` > 100 chars
   - `ip` invalido
   - `localizacao` > 120 chars
   - `observacao` > 255 chars
3. Testar cadastro valido.

Resultado esperado:
- Invalidos: mensagens de validacao.
- Valido: salva com sucesso.

## 7) CRUD de impressoras
1. Criar uma impressora.
2. Editar a mesma impressora (`impressora/editar.php?id=...`).
3. Excluir pela listagem ou detalhes.

Resultado esperado:
- Fluxo completo funcionando com feedback visual (flash).
- Exclusao somente via `POST + CSRF`.

## 8) Tratamento de erro amigavel
1. Induzir erro de banco (ex.: parar MySQL rapidamente) e abrir `index.php` e `impressora/impressoras.php`.
2. Restaurar banco.

Resultado esperado:
- Tela mostra mensagem amigavel (sem stack trace).
- Detalhe tecnico fica no log (`error_log` do PHP/Apache).

## 9) Arquivos sensiveis
1. Confirmar que `config/.htaccess` existe.
2. Confirmar que `.htaccess` raiz bloqueia extensoes sensiveis.
3. Tentar acessar arquivo sensivel (ex.: `.sql` na web).

Resultado esperado:
- Acesso negado para arquivos protegidos.

