# API mobile v1

Esta API acrescenta autenticação nativa e um envelope JSON sem remover nem alterar as URLs web existentes. As rotas PHP em `api/` continuam disponíveis para a aplicação web. O app deve chamar apenas os endpoints versionados descritos abaixo.

## Pré-requisitos de implantação

- PHP 8.0+ com as extensões e configurações da aplicação, MySQL/MariaDB e HTTPS em produção.
- Aplicar as migrações existentes exigidas por cada recurso, além de `php db/migrate_mobile_auth.php`, usando o CLI e a configuração de ambiente adequada. A migração mobile é aditiva e idempotente.
- Não execute migrações automaticamente em requisições web. Faça backup, aplique em homologação e confirme que `mobile_auth_sessions` e `mobile_auth_tokens` existem antes de expor login mobile.
- Não coloque credenciais de banco, `APP_SECRET`, credenciais SMTP ou segredos de assinatura no app. Os tokens retornados são credenciais de sessão.
- Configure o servidor para encaminhar o cabeçalho `Authorization` ao PHP (`HTTP_AUTHORIZATION` ou `REDIRECT_HTTP_AUTHORIZATION`). A API não habilita CORS por padrão; clientes nativos não precisam de CORS. CORS não autentica clientes.

## Autenticação

As rotas usam access token opaco no cabeçalho `Authorization: Bearer <token>`. Access tokens expiram em 15 minutos. Refresh tokens expiram em 30 dias; cada renovação consome e substitui o refresh token anterior. O servidor persiste somente SHA-256 dos tokens e associa tokens a uma sessão por usuário/dispositivo.

Se um refresh token já consumido for apresentado novamente, a sessão inteira é revogada, incluindo os tokens atuais. Logout revoga a sessão ligada ao refresh token. A alteração de `usuarios.auth_version` invalida os tokens existentes. Senhas e tokens nunca são incluídos nos logs da API.

Armazene o refresh token em armazenamento protegido do sistema (por exemplo, SecureStore/Keychain/Keystore no cliente React Native), nunca em AsyncStorage ou em texto claro. Mantenha access token em memória sempre que possível. Use TLS em todas as conexões públicas.

### Login

`POST /api/v1/auth.php?action=login` — JSON até 8 KB:

```json
{
  "identifier": "usuario_ou_email",
  "password": "senha",
  "account_type": "user",
  "device_name": "Android"
}
```

`account_type` é `user` (perfis estudante/professor) ou `institution` (conta institucional e registro institucional existente). A autenticação institucional não concede aprovação, validação ou assinatura. Essas permissões são verificadas no servidor em cada operação.

Resposta de sucesso `200`:

```json
{
  "data": {
    "tokens": {
      "access_token": "token-opaco",
      "refresh_token": "token-opaco",
      "token_type": "Bearer",
      "expires_in": 900,
      "refresh_expires_in": 2592000
    },
    "account_type": "user",
    "user": {
      "id": 42,
      "nome_completo": "Conta de exemplo",
      "email": "exemplo@example.invalid",
      "username": "exemplo",
      "perfil": "estudante",
      "perfil_imagem": "assets/images/icons/user-icon.png"
    }
  },
  "error": null,
  "meta": null
}
```

Tentativas inválidas recebem mensagem uniforme e são limitadas por IP e identificador. Não há cadastro mobile nem OAuth nesta versão.

### Renovar, sair e obter conta

- `POST /api/v1/auth.php?action=refresh`, JSON `{ "refresh_token": "..." }`: retorna novo par de tokens.
- `POST /api/v1/auth.php?action=logout`, JSON `{ "refresh_token": "..." }`: revoga a sessão e retorna `{ "data": { "revoked": true }, "error": null, "meta": null }`. Logout é idempotente para tokens desconhecidos.
- `GET /api/v1/auth.php?action=me`, com bearer access token: retorna tipo de conta e dados da própria conta.

## Envelope, erros e códigos

Todas as respostas da API v1 usam:

```json
{ "data": {}, "error": null, "meta": null }
```

Erros seguem `{ "data": null, "error": { "code": "INVALID_PARAMETERS", "message": "..." }, "meta": null }`. Códigos comuns: `400` parâmetros/corpo inválidos, `401` autenticação ou token inválido, `403` permissão negada, `404` endpoint/recurso indisponível, `405` método não aceito, `409` conflito de estado, `413` payload acima do limite, `415` tipo de conteúdo não suportado, `422` validação, `429` limite de tentativas e `500` erro interno genérico. A ponte usa mensagens públicas genéricas para erros legados, sem encaminhar mensagens de exceção; respostas `500` não expõem mensagens técnicas.

A ponte para endpoints existentes envelopa respostas JSON antigas, converte redirects de autenticação em `401` e converte respostas não JSON em erro genérico. As rotas antigas fora de `/api/v1/` mantêm seus formatos históricos para não quebrar o site.

## Recursos existentes disponíveis via ponte v1

`GET` e `POST` são os métodos disponíveis nos recursos legados, sujeitos aos métodos efetivamente implementados por cada endpoint. URL base da ponte: `/api/v1/index.php?resource=<nome>`. Para `POST`, clientes podem enviar `application/json` (até 1 MiB; a ponte preenche os campos esperados pelo endpoint legado) ou `multipart/form-data` para uploads. Requisições autenticadas usam bearer token; não usam cookie de sessão web. O CSRF legado só é dispensado após a validação do bearer. A autorização e as permissões de domínio continuam sendo verificadas pelo backend existente.

| `resource` | Métodos e parâmetros/corpo do endpoint existente | Acesso e comportamento |
|---|---|---|
| `pesquisa` | `GET`: `q`, `assunto`, `materia`, `tipo`, `acesso`, `origem`, `page`, `limit` (máx. 20) | Público ou autenticado; filtro e escopo por permissão; `origem=biblioteca` limita resultados a materiais da Biblioteca |
| `salas` | `GET`: `id` ou `q`, `assunto`, `materia`, `acesso`; v1 aceita também `page` e `limit` (máx. 50) | Público ou autenticado; autorização aplicada antes da paginação; `meta` contém página, limite, total e páginas |
| `ranking` | `GET`, sem parâmetros | Público; expõe somente dados permitidos pelo opt-in; limite existente de 50 |
| `usuario` | `GET`, sem parâmetros | Bearer de usuário estudante/professor; retorna somente os dados da própria conta |
| `configuracoes` | `GET`; `POST` JSON com `preferences` | Usuário ou instituição autenticados; preferências são validadas e associadas à conta autenticada |
| `exportar-dados` | `GET`, sem parâmetros | Usuário ou instituição autenticados; exporta o escopo limitado já implementado |
| `feed` | `GET?action=comments&id=<id>`; `POST` com `action` e campos próprios da ação, opcional `media` | Usuário autenticado; ações legadas `publish`, `save_draft`, `publish_draft`, `edit`, `delete`, `like`, `save`, `comment`, `delete_comment`, `report`; upload multipart |
| `biblioteca-editor` | `POST` multipart: `action=save_draft|submit_review`, campos de material, opcional `arquivo` | Usuário ou instituição autenticados; papéis, aprovação, assinatura, cotas e limites são verificados no servidor |
| `biblioteca-leitura` | `GET?material_id=<id>`; `POST` com `material_id`, `action` e campos correspondentes | Usuário autenticado; operações incluem preferências, progresso e anotações; valida acesso ao material e propriedade |
| `biblioteca-emprestimos` | `GET`; `POST` com `action=request|create_copy|decide|availability` e campos da ação | Usuário ou instituição; autorização por conta e instituição; listas legadas limitadas no serviço/endpoints |
| `biblioteca-revisao` | `POST` multipart/form: `material_id`, `decision=approve|reject|archive`, `reason` | Usuário autenticado e permissão administrativa; validadores de revisão permanecem no serviço |
| `instituicao-portal` | `GET?institution_id=<id>`; `POST` form/JSON com `institution_id`, `action` e dados específicos | Usuário ou instituição; vínculo, papel, estado institucional e assinatura são revalidados no servidor |
| `video-aprendizado` | `POST` form/JSON: `publication_id`, `action=start|heartbeat`; heartbeat também requer `session_token`, `position`, `sequence`, `is_playing` | Usuário estudante/professor autenticado; verifica acesso à aula e estado Premium. Provedor real de anúncios permanece ausente |

Para parâmetros de leitura, as APIs legadas mantêm as validações e limites indicados em seus arquivos. A ponte não expõe operações do painel administrativo que não estejam em `api/`. O feed móvel ainda não tem uma operação própria de listagem de publicações; o endpoint legado `feed` oferece comentários e ações, não uma listagem. O catálogo de questões ainda não tem execução mobile de quizzes.

### Exemplos de chamadas sem dados de conta reais

```http
GET /api/v1/index.php?resource=pesquisa&origem=biblioteca&q=biologia&page=1&limit=10
Accept: application/json
Authorization: Bearer <access_token>
```

```http
POST /api/v1/index.php?resource=configuracoes
Authorization: Bearer <access_token>
Content-Type: application/json

{"preferences":{"theme":"dark","reduce_motion":true}}
```

```http
GET /api/v1/index.php?resource=salas&page=1&limit=20
Accept: application/json
```

## Contratos legados em `api/`

As URLs antigas não são removidas. Seus contratos reais continuam sendo os dos arquivos correspondentes: `GET` de catálogo/pesquisa, salas, ranking, perfil, configurações e exportação; `GET`/`POST` para leitura, empréstimos, Feed e portal institucional; `POST` para editor, revisão e aprendizagem em vídeo. Campos de escrita, regras de negócio, permissões e formatos estão listados na tabela de recursos acima. Fora do namespace v1, respostas podem ser objeto/lista JSON sem envelope, mensagens de erro históricas ou respostas textuais; clientes mobile devem usar exclusivamente v1.

## Pendências e limites conhecidos

- Não foi implementado cadastro mobile, recuperação de senha nativa, OAuth, checkout ou pagamentos.
- Instituições não ganham validação/assinatura por autenticar; recursos protegidos continuam bloqueados até cumprir as condições existentes.
- Não há endpoint de listagem de Feed mobile, execução de questões/quiz ou criação de pontuação do ranking.
- As rotas legadas não especificam uma versão de esquema uniforme. A migração mobile não substitui as migrações funcionais de Biblioteca, instituições, vídeo, questões e permissões.
- A ponte adapta contratos, mas uploads de arquivos grandes e reprodução protegida devem ser testados com limites reais do servidor e dispositivos Android antes de release.
