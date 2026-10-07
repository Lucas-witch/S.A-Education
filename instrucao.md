# Instruções para correção do fluxo de autenticação e do banco

## Objetivo
Corrigir o problema de login que impede o redirecionamento para dashboard.php e alinhar o banco de dados com as regras de negócio do projeto para que a autenticação seja consistente, segura e previsível.

## Contexto do problema
O erro não está no `header('Location: dashboard.php')` em si. O problema real está antes do redirect: o fluxo de autenticação falha em uma ou mais validações e o código retorna para login.php ou encerra a execução antes de criar a sessão.

O login só deve prosseguir para dashboard.php quando todas as condições abaixo forem verdadeiras:

- o CSRF for válido
- o campo `login` não vier vazio
- o campo `senha` não vier vazio
- o usuário existir no banco
- a senha for validada com `password_verify`
- o perfil do usuário for `estudante` ou `professor`

Se qualquer uma dessas validações falhar, o fluxo retorna para `login.php` ou encerra com `exit`, e o usuário nunca chega ao dashboard.

## Diagnóstico técnico do projeto

### 1) Fluxo de login atual
Em `processa_login.php`, o script realiza:

- validação de método HTTP
- validação CSRF via `verify_csrf()`
- validação de campos obrigatórios
- consulta em `usuarios` por `email` ou `username`
- validação da senha com `password_verify`
- criação da sessão com `$_SESSION['usuario_id']`, `$_SESSION['usuario_perfil']` etc.
- redirecionamento para `dashboard.php`

Se o usuário não foi encontrado, a senha não casar, ou o perfil não ser permitido, o código faz redirect para login.php.

### 2) Banco atual
No schema `db/sa_education.sql`, a tabela `usuarios` tem:

- `email` único
- `username` único
- `perfil` enum com: `estudante`, `professor`, `instituicao`
- `senha` salva em hash

A regra atual no login é:

- `u.perfil IN ('estudante', 'professor')`

Isso é válido apenas se o banco e o cadastro estiverem totalmente alinhados com essa regra. Caso contrário, a autenticação quebra.

### 3) Evidência de dados de teste no banco
O dump ainda inclui usuários de exemplo, como:

- `admin@saeducation.com`
- `ana.clara@example.com`
- `lucas@example.com`
- `marina@example.com`

Esses dados misturam ambiente de desenvolvimento com o que deveria ser base de produção. O projeto exige uma política de “DB como fonte de verdade” e deve evitar usuários padrão ou dados demo em produção.

### 4) CSRF e sessão
Em `includes/helpers.php`, `verify_csrf()` valida o token gerado pela sessão. Se a aplicação gerar um token em um fluxo e o formulário enviar outro, a validação falha e a execução termina antes do redirect.

Além disso, em `dashboard.php`, a página depende de `$_SESSION['usuario_id']` para continuar. Se a sessão não for criada corretamente, o guard do auth faz `header('Location: login.php')`.

## Regras de negócio que devem ser adotadas

### Regra 1 — Banco como fonte da verdade
- Não inserir usuários padrão no bootstrap da aplicação.
- Remover dados demo do banco antes de considerar ambiente funcional.
- Garantir que toda criação/alteração de usuário venha do banco e do código de cadastro, não de seed arbitrário.

### Regra 2 — Perfis permitidos
Definir claramente os perfis válidos:

- `estudante`
- `professor`
- `instituicao`

E garantir que o fluxo local de login aceite apenas:

- `estudante`
- `professor`

O perfil `instituicao` deve usar cadastro específico e não entrar pelo login comum.

### Regra 3 — Google OAuth
Se o sistema usar autenticação por Google, a regra deve ser:

- permitir apenas contas de `estudante` ou `professor`
- bloquear criação de contas institucionais via Google
- manter o fluxo institucional separado

### Regra 4 — Redefinição de senha
O projeto deve ter um fluxo de recuperação de senha separado, com validação de conta e atualização segura do hash da senha.

## Plano de correção

### Fase 1 — Corrigir o banco
1. Remover dados de exemplo e usuários padrão do banco.
2. Garantir que os perfis tenham consistência e sigam as regras do projeto.
3. Validar integridade dos dados:
   - `email` único
   - `username` único
   - `perfil` sempre compatível
   - `senha` salva em hash
4. Definir uma política de produção: sem dados demo no banco final.

### Fase 2 — Padronizar autenticação
1. Centralizar as regras de auth em `includes/auth.php`.
2. Usar uma convenção única para sessão:
   - `$_SESSION['usuario_id']`
   - `$_SESSION['usuario_perfil']`
   - `$_SESSION['usuario_email']`
   - `$_SESSION['usuario_username']`
3. Eliminar lógica duplicada espalhada em vários arquivos.

### Fase 3 — Diagnosticar o login com transparência
Adicionar no fluxo de autenticação logs temporários para identificar exatamente a causa da rejeição:

- valor de `$_POST`
- `$_SESSION['csrf_token']`
- resultado do `password_verify`
- perfil encontrado no banco
- resultado da query de usuário

Isso ajuda a distinguir entre:

- token CSRF inválido
- senha incorreta
- usuário inexistente
- perfil não permitido
- sessão não criada

### Fase 4 — Validar login real
Antes de encaminhar para dashboard.php, verificar com um usuário válido no banco:

- usuário encontrado
- senha válida
- perfil permitido
- sessão preenchida corretamente

Checklist SQL útil:

```sql
SELECT id, nome_completo, email, username, perfil, senha
FROM usuarios
WHERE email = 'teste@exemplo.com' OR username = 'teste';
```

```sql
SELECT id, email, username, perfil
FROM usuarios
WHERE perfil NOT IN ('estudante', 'professor', 'instituicao');
```

### Fase 5 — Revisar regras de negócio finais
Definir claramente, em documentação e em código:

- login local: student/professor
- cadastro institucional: fluxo separado
- Google: permitido apenas para estudante/professor, se existir
- redefinição de senha: fluxo separado
- usuários padrão: zero em produção

## Tarefas que devem ser executadas pela IA

1. Revisar todos os arquivos envolvidos em autenticação:
   - `processa_login.php`
   - `login.php`
   - `includes/helpers.php`
   - `includes/auth.php`
   - `dashboard.php`
   - `conexao.php`
   - `processa_cadastro.php`
   - `processa_cadastro_instituicao.php`
   - `oauth/google_redirect.php`
   - `oauth/google_callback.php`
   - `db/sa_education.sql`

2. Analisar se há inconsistência entre:
   - regras de perfil no código
   - valores do enum no banco
   - cadastro de usuário
   - login e sessão

3. Corrigir a autenticação de forma consistente e segura.

4. Eliminar dados demo e regras que bagunçam a lógica de autenticação.

5. Garantir que o usuário que loga corretamente seja redirecionado para `dashboard.php` e que a sessão seja criada imediatamente.

6. Verificar a sintaxe PHP e validar a lógica de fluxo.

7. Retornar um resumo claro com:
   - causas do problema
   - arquivos alterados
   - mudanças realizadas no banco
   - alterações no código
   - comprovante de validação

## Recomendação final
O problema principal não é apenas o redirect quebrado; é a falta de alinhamento entre banco, regras de perfil e fluxo de autenticação. A solução correta é unificar esses três pontos e remover dados de teste que tornam o sistema imprevisível.

A IA deve corrigir o problema de raiz, não apenas contornar o redirect.

## Resultado esperado
Após as intervenções, o sistema deve:

- aceitar apenas usuários válidos de `estudante` e `professor` no login comum
- bloquear contas institucionais no fluxo normal
- manter cadastro institucional em um fluxo separado
- criar a sessão corretamente
- redirecionar para `dashboard.php` de forma consistente
- remover dados demo e inconsistências do banco
- manter a autenticação segura e previsível
