# S.A Education.
Aplicativo de estudos do projeto integrador de 2026

Repositorio criado para fins de desenvolvimento coletivo do novo projeto integrador
# S.A Education — Autenticação PHP


## Estrutura
- `index.php` — tela inicial com Criar conta / Entrar
- `cadastro.php` — formulário de criação de conta
- `login.php` — formulário de login
- `processa_cadastro.php` — valida e grava o usuário
- `processa_login.php` — autentica o usuário
- `dashboard.php` — página protegida após login
- `logout.php` — encerra a sessão
- `conexao.php` — conexão PDO com MySQL
- `style.css` — identidade visual
- `database.sql` — banco e tabela de usuários

## Instalação
1. Crie um banco MySQL executando `database.sql`.
2. Abra `conexao.php` e altere usuário, senha e nome do banco, se necessário.
3. Coloque a pasta no servidor PHP (XAMPP, WAMP, Laragon ou hospedagem).
4. Acesse `index.php`.

## Segurança implementada
- PDO com prepared statements.
- `password_hash()` para armazenamento de senhas.
- `password_verify()` no login.
- Sessão PHP.
- Regeneração do ID da sessão após autenticação.
- Validação de e-mail, username e senha.
- Verificação de confirmação de senha.
- Mensagens de erro sem revelar se uma conta específica existe.

