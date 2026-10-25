# Diagnóstico e correção de autenticação — S.A Education

## Causa confirmada no banco local

O banco `sa_education` já tinha a tabela `usuarios`, então a inicialização automática do schema não executava atualizações de um banco existente. Faltavam `auth_rate_limits`, `password_reset_tokens` e `usuarios.auth_version`. Como tanto login quanto redefinição consultam o limitador de tentativas logo no início, as duas rotas falhavam antes de validar a senha ou emitir o link.

A migração aditiva `db/migrate_security_features.php` foi aplicada ao banco local. Ela criou as estruturas ausentes, manteve os registros existentes e acrescentou os índices únicos somente após verificar duplicatas. O login continua aceitando apenas credenciais válidas de contas `estudante` ou `professor`; não há bypass de senha nem desativação do limitador.

## Recuperação de senha

- Em localhost/127.0.0.1, quando `APP_BASE_URL` não está definido, a URL é derivada do host local e do diretório da aplicação.
- Em produção, `APP_BASE_URL` deve ser definida como URL HTTPS canônica.
- O envio depende de configuração SMTP real (`SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD` e `SMTP_FROM_EMAIL`). A aplicação não carrega `.env` automaticamente.
- Se o servidor SMTP falhar, o token pendente é removido, a falha é registrada no log PHP/Apache e a solicitação não é apresentada como concluída.
- A nova senha é armazenada por `password_hash`; o incremento de `auth_version` invalida sessões anteriores.

## Verificação local

Use PHP do XAMPP e variáveis do banco configuradas para CLI. Para um banco existente, faça backup e execute:

```powershell
$env:APP_ENV = 'development'
$env:DB_HOST = '127.0.0.1'
$env:DB_NAME = 'sa_education'
$env:DB_USER = 'root'
$env:DB_PASS = ''
& 'C:\xampp\php\php.exe' db\migrate_security_features.php
```

Configure as mesmas variáveis de banco no Apache e `SMTP_*` com credenciais do provedor de e-mail. Teste com uma conta descartável. Não use dados reais, tokens ou senhas em logs e capturas compartilhadas. Em caso de falha, consulte `C:\xampp\apache\logs\error.log`.
