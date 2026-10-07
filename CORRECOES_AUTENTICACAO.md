# Correções de autenticação — S.A Education

## Problemas encontrados

1. `processa_login.php` reutilizava o placeholder `:login` duas vezes com `PDO::ATTR_EMULATE_PREPARES = false`, podendo causar `SQLSTATE[HY093]`.
2. `processa_redefinir_senha.php` reutilizava `:identificador` da mesma forma, causando o mesmo tipo de erro.
3. O login fazia `LEFT JOIN planos` e selecionava muitas colunas opcionais. Se o banco local fosse de uma versão anterior, uma coluna ausente fazia todo o login lançar `PDOException`.
4. O destino correto após o login é `perfil-dashboard.php`; não deve existir um redirecionamento intermediário para `dashboard.php`.

## Alterações

- Placeholders separados para e-mail e username em login e redefinição.
- Login passa a consultar `usuarios.*` sem depender da tabela `planos` ou das colunas de progresso.
- Validação do perfil permanece em PHP e aceita somente `estudante` e `professor` no login comum.
- Redefinição aplica `password_hash(..., PASSWORD_DEFAULT)`.
- Erros PDO são registrados no log do PHP/Apache com os prefixos:
  - `[S.A Education][login][PDO]`
  - `[S.A Education][redefinir_senha][PDO]`
- O login bem-sucedido agora redireciona diretamente para `perfil-dashboard.php`.

## Teste sugerido

1. Substitua a pasta do projeto no `htdocs`.
2. Acesse `http://localhost/saeducation/login.php`.
3. Entre com uma conta `estudante` ou `professor` criada pelo cadastro.
4. Verifique se o sistema abre a página principal.
5. Saia da conta e teste `Esqueci minha senha`.
6. Defina uma senha com pelo menos 8 caracteres, uma maiúscula e um número ou símbolo.
7. Entre novamente com a nova senha.

Se ainda surgir uma mensagem genérica, consulte o log do XAMPP, normalmente em `C:\xampp\apache\logs\error.log`.
