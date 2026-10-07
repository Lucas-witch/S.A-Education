## Plano detalhado: Implementação completa — S.A Education (mobile-first, PWA)

TL;DR: Reescrever o projeto para ser um PWA mobile-first, com autenticação (e-mail/senha + OAuth Google/Microsoft), banco MySQL, API JSON mínima e front-end responsivo que reproduza as telas do guia. Substituir emojis por imagens em `assets/images/`. O trabalho será executado em fases com checkpoints e testes.

**Escopo**
- Autenticação: registro, login, logout, sessão segura, OAuth Google/Microsoft.
- Backend: PDO MySQL, tabelas `usuarios` e `salas`, prepared statements, tratamento de erros.
- API: endpoints JSON em `api/` para `salas`, `usuario` e `ranking`.
- Frontend: páginas PHP server-side renderizadas + progressive enhancement com `fetch` para API, mobile-first CSS, imagens (substituir emojis), bottom navigation e componentes de cards.
- PWA: `manifest.json`, `sw.js`, imagens de ícone no `assets/images/`.
- Segurança: CSRF tokens, `htmlspecialchars()` nas saídas, session cookie flags, input validation.

**Fases e tarefas (detalhadas)**

**Fase 0 — Preparação (1 dia)**
- **Checklist:** criar branch `feature/rewrite-app` local.
- **Criar pastas:** `assets/images/`, `db/`, `api/`, `logs/`, `seed/`.
- **Assets existentes:** usar o logo enviado; indicar no código onde outras imagens devem ser colocadas (`assets/images/trophy.svg`, `room_icons/math.svg`, `avatars/default.png`).

**Fase 1 — Banco e infra (1 dia)**
- `db/schema.sql` — criar tabela `usuarios` e `salas` com campos:
  - `usuarios(id, nome_completo, email, username, senha, perfil, perfil_imagem, criado_em)`
  - `salas(id, nome, prof, alunos, materia, icone, codigo, privacidade, descricao, criado_em)`
- `seed/seed.sql` — inserir 3 salas e 5 usuários (senha hash com `password_hash('Senha@123', PASSWORD_DEFAULT)`).
- Atualizar `conexao.php` para suportar config via variáveis (constantes/ENV). Garantir PDO exceptions no `logs/app.log` em caso de erro.

**Fase 2 — Helpers e segurança (0.5 dia)**
- Criar `includes/helpers.php` com funções:
  - `esc($s)` retorna `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')`.
  - `csrf_token()` gera/retorna token em `$_SESSION['csrf_token']`.
  - `verify_csrf($token)` valida e mata a requisição se inválido.
- Ajustar `conexao.php` para não exibir erros ao usuário; logar exceções.
- Configurar sessão segura no topo dos scripts: `session_set_cookie_params([...])` antes de `session_start()`.

**Fase 3 — Autenticação & fluxos (1 dia)**
- Corrigir `includes/auth.php` — remover saída `alterar`, iniciar sessão apenas quando necessário; fornecer função `require_auth()` que redireciona para `login.php` se `$_SESSION['usuario_id']` ausente.
- Implementar `logout.php` (limpa sessão e cookies, redireciona para `index.php`).
- Ajustar `cadastro.html` → transformar em `cadastro.php` (form POST) com names esperados: `nome_completo`, `email`, `username`, `senha`, `confirmar_senha`, `perfil` e incluir campo hidden `csrf`.
- Atualizar `processa_cadastro.php`: validar `csrf`, checar duplicidades (email/username), inserir usuário com `password_hash`, redirecionar para `login.php` com mensagem de sucesso.
- Atualizar `processa_login.php`: validar `csrf`, preparar statement, `password_verify`, `session_regenerate_id(true)`, setar `$_SESSION` padrão e redirecionar para `perfil-dashboard.php`.

**Fase 4 — OAuth (Google/Microsoft) (1-2 dias)**
- Criar rota `oauth/redirect.php` e `oauth/callback.php` para cada provedor (Google, Microsoft) ou usar uma lib leve.
- Armazenar `oauth_provider` e `oauth_id` na tabela `usuarios` (adicionar colunas opcionais). Se usuário não existir, criar conta com dados recebidos (email + nome) e gerar `username` único.
- Documentar configuração (client_id, client_secret) em `README` e `.env.example`.

**Fase 5 — API JSON (0.5-1 dia)**
- Criar `api/salas.php` (GET list, GET?id, POST criar [autenticado]), `api/usuario.php` (GET perfil autenticado), `api/ranking.php` (GET leaderboard paginado).
- Usar session cookie para autenticar requests (retornar 401 se não autenticado para endpoints protegidos).

**Fase 6 — Frontend: templates & CSS (2 dias)**
- Unificar CSS: manter `assets/css/style.css` como principal. Migrar estilos de `estilo.css` para este arquivo.
- Atualizar cabeçalho/footer: `includes/header.php` e `includes/footer.php` com paths corretos e bottom-nav mobile.
- Substituir emojis por imagens: trocar ocorrências em `index.php`, `perfil-dashboard.php`, `sala.php`, `perfil.php`, `ranking.php`, `criar-sala.php`, `entrar-sala.php` para `<img src="assets/images/..." alt="...">` com classes responsivas.
- Criar componentes visuais em HTML (cards, room-card, profile-pill) conforme o design.

**Fase 7 — Interatividade JS (0.5 dia)**
- Expandir `assets/js/app.js` para: menu mobile open/close, tabs, filtros, chamada `fetch('/api/salas.php')` para carregar salas dinamicamente, manipular respostas JSON e renderizar cards.
- Implementar pequeno utilitário para exibir alerts e modais.

**Fase 8 — PWA e service worker (0.5 dia)**
- Atualizar `manifest.json` com `assets/images/logo_SAeducation.png` e pocket.
- Atualizar `sw.js` para caching: navegational fallback, cache-first para assets, network-first para APIs.

**Fase 9 — Testes e QA (1 dia)**
- Testes manuais enumerados: registro, login, logout, fluxo quiz, criar sala, entrar sala, ranking, offline básico.
- Corrigir regressões e melhorias de UX.

**Fase 10 — Documentação e entrega (0.5 dia)**
- Atualizar `README.md` com passos de setup, env, criação do banco e seed. Incluir notas para design sobre imagens faltantes com nomes sugeridos.

**Arquivos a criar/editar (lista precisa)**
- Criar: `db/schema.sql`, `seed/seed.sql`, `api/salas.php`, `api/usuario.php`, `api/ranking.php`, `includes/helpers.php`, `logout.php`, `oauth/` (redirect/callback files), `assets/images/` placeholders, `logs/app.log` (gitignored), `seed/README.md`.
- Editar: `includes/auth.php`, `processa_cadastro.php`, `processa_login.php`, `conexao.php`, `includes/header.php`, `includes/footer.php`, `includes/data.php` (fazer fallback DB), `assets/css/style.css`, `assets/js/app.js`, `manifest.json`, `sw.js`.

**Pontos de atenção / riscos**
- OAuth demanda registro de apps Google/Microsoft e URLs de callback corretas; sem credenciais é impossível testar. Forneceremos `.env.example` e instruções.
- Imagens finais da equipe de design devem substituir placeholders automaticamente (mesmo nome/path) para facilitar deploy.
- Testar em ambiente HTTPS para `session.cookie_secure` e OAuth funcionar.

**Verificação final / checklist**
1. Banco criado e seed importado.
2. Registrar/login/logout funcionando; `$_SESSION['usuario_id']` presente.
3. CSRF funcionanddo em forms principais.
4. API endpoints respondendo JSON e autenticação por sessão.
5. PWA instala e assets de ícone carregam.
6. Telas mobile refletem layout do guia visual (cards, botton nav, cores e imagens).

**Próximo passo**
Se aprovar este plano, eu gerarei o conjunto de alterações (lista de arquivos) e pedirei sua confirmação para iniciar a implementação. Lembre-se: começarei criando a branch e implementando as correções críticas primeiro (remover saída em `includes/auth.php`, criar `logout.php`, alinhar `cadastro`), depois seguirei pelo fluxo descrito.

**Observação:** criei este plano em `/memories/session/implementation_plan.md` para persistência; quando autorizar, executarei as mudanças em etapas e reportarei progresso após cada grupo de arquivos alterados.