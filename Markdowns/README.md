# S.A Education — primeira versão de teste

Relatório da primeira versão de teste da plataforma S.A Education. Esta entrega reúne a navegação compartilhada, o perfil do usuário, as principais páginas acadêmicas e correções incrementais de autenticação, privacidade, configuração e acessibilidade. O projeto continua em desenvolvimento: recursos descritos como próximos passos não devem ser considerados disponíveis nesta versão.

**Versão:** primeira versão de teste · **Relatório atualizado:** 20 de outubro de 2026 · **Estado:** em validação; não aprovada para produção.

## Relatório da versão

### O que foi feito nesta etapa

- **Menu lateral:** fechado por padrão; pode ser aberto pelo botão do cabeçalho e fechado pelo botão próprio, pelo backdrop ou pela tecla `Escape`. O controle sincroniza foco, estado ARIA e bloqueio/restauração da rolagem. Em telas móveis, o painel sobrepõe o conteúdo e a navegação inferior não fica visível ao mesmo tempo.
- **Navegação compartilhada:** centralizados os links de Início, Salas, Biblioteca/Cursos, Feed, Questões, Ranking e Meu perfil. Os rótulos da navegação móvel são derivados da mesma lista para evitar diferenças em relação ao menu.
- **Perfil:** implementada edição de nome, e-mail e nome de usuário, com validação de unicidade, proteção CSRF, uso do usuário autenticado e persistência via PDO. A foto aceita JPEG, PNG e WebP até 5 MB, é validada no servidor e pode ser pré-visualizada, alterada ou removida. `usuarios.perfil_imagem` é a fonte canônica e os dados exibidos são sincronizados com a sessão.
- **Estilos compartilhados:** removidas redefinições do shell que alteravam a geometria do cabeçalho e da navegação entre páginas; organizadas regras e breakpoints globais, preservando estilos específicos de páginas.
- **Atualização de recursos:** service worker avançado para o cache estático `v8`, com precache de caminhos explícitos, invalidação de caches estáticos anteriores e registro sem cache HTTP do script do worker. Documentos de navegação e respostas privadas não são armazenados pelo cache estático.
- **Ícones e salas:** integrados os novos ícones à navegação e às matérias; a página inicial lista salas com vínculo ativo do usuário em vez de usar a lista geral. Entradas de salas de demonstração foram removidas do SQL/seeder e da base local usada no desenvolvimento.
- **Planos:** catálogo de produto separado dos perfis, com valores mensais definidos, validação de renda ainda pendente, limites do professor gratuito e preços institucionais armazenados no banco. A página apresenta os planos da categoria da conta; não há checkout nem confirmação de pagamento nesta versão.
- **Recuperação de conta:** substituído o reset baseado somente em e-mail/username por solicitação antienumeração e token de uso único com validade de uma hora. A entrega depende de SMTP configurado no ambiente; a redefinição incrementa a versão de autenticação para invalidar sessões anteriores.
- **Autenticação institucional:** criado fluxo e sessão separados para instituições, sem adicionar o perfil institucional ao allowlist do login de estudante/professor. A administração pode validar/rejeitar instituições e aprovar/rejeitar professores com auditoria. O painel permite convidar contas cadastradas, aceitar convites autenticados, cancelar convites e revogar vínculos; o acesso a conteúdo institucional exige vínculo ativo, validação e assinatura ativa.
- **Privacidade do ranking:** participação agora depende de opt-in explícito; o público recebe somente username, posição e pontuação. Os dados completos não são exibidos e o estado vazio não usa usuários fictícios.
- **Proteções operacionais:** adicionados rate limits com buckets pseudonimizados, cabeçalhos `no-store` no endpoint privado, validação de dimensão de foto, reprocessamento JPEG via GD em produção, configuração de banco por ambiente, `.gitignore` para segredos/uploads e migrações CLI idempotentes ou protegidas contra repetição web.
- **Acessibilidade:** restaurados indicadores de foco nas buscas e controles interativos; a validação de contraste foi calculada para as combinações principais de texto e superfície.

### Atualização — permissões e cotas (25 de outubro de 2026)

- Centralizada a resolução de perfil, aprovação, plano, confirmação/expiração de assinatura, validação institucional e permissões administrativas em `includes/permissions.php`. As páginas autenticadas recarregam as permissões do banco, não confiam no plano legado armazenado na sessão e falham de forma explícita se não puderem validá-las.
- Adicionada a migração reversível `db/migrate_permissions.php up|down`. A migração cria `product_plans`, estados de aprovação/validação, assinaturas, vínculo de autoria na Biblioteca, agrupamento mensal de questões, auditoria e tabelas de membros/convites institucionais. No MySQL/MariaDB, `down` não é atômico e apaga os dados desses novos recursos; exige backup.
- Professores são registrados como pendentes até aprovação administrativa. A tela `admin-permissoes.php` também valida instituições e registra cada decisão sem criar/confirmar pagamentos.
- Professores gratuitos aprovados têm cotas transacionais no servidor e banco: cinco aulas em vídeo, um material/livro e um banco mensal de até 100 questões. O Feed genérico e dúvidas continuam sem cota; as cotas também se aplicam à Biblioteca. Rascunhos podem ser salvos, editados e publicados depois, com nova validação ao publicar.
- O catálogo contém os valores solicitados: estudante Premium R$ 70, estudante com desconto R$ 21 após validação ainda a definir, professor Premium R$ 100 e as quatro categorias institucionais. A expiração não remove nem despublica publicações.
- A migração foi aplicada ao banco de desenvolvimento `sa_education` após backup local; a migração `up` e sua repetição, além de `down`/`up`, foram exercitadas em banco descartável MariaDB 10.4.
- Convites institucionais: e-mail apenas para conta existente, token aleatório de uso único armazenado como hash, associação ao usuário convidado, verificação de perfil/aprovação e validade atual da assinatura no envio e no aceite. Revogações/cancelamentos e convites são registrados em `institution_audit_log`. Entrega SMTP real depende da configuração do ambiente.
- Feed, Biblioteca e busca aplicam o vínculo ativo, a validação, o plano institucional compatível e a assinatura paga dentro da validade nas consultas de publicações/dados. A Biblioteca ainda não possui armazenamento privado nem entrega protegida de arquivos, e a interface de criação de conteúdo próprio da instituição continua pendente; esses itens devem ser concluídos antes de afirmar isolamento de arquivos compartilhados fora da aplicação.
- A ampliação da migração `db/migrate_permissions.php up` foi aplicada ao banco de desenvolvimento `sa_education` após backup local. As tabelas institucionais novas passaram por `up/down` em banco MariaDB descartável, e a suíte completa passou com 12/12 scripts PHP.
- **Pendências reais:** integração de checkout/provedor e confirmação de pagamento; definição do método de comprovação de renda; módulo e modelo de cursos; interface/modelo completo de questões e criação/gestão de conteúdos institucionais. O acesso institucional permanece bloqueado sem validação e assinatura ativa; nenhum fluxo marca pagamento como confirmado nesta entrega.

### Validação executada nesta entrega

| Verificação | Resultado |
| --- | --- |
| Testes automatizados PHP de ícones, perfil, sessão/auth version, dashboard, Feed, catálogo, ranking, rate limit, SMTP parser, materiais e permissões/cotas | 11/11 aprovados com PHP 8.0.30 do XAMPP |
| Testes direcionados de convites, aceite/revogação, Feed, busca e isolamento institucional | 4/4 aprovados com PHP 8.0.30 do XAMPP |
| Sintaxe PHP de todos os arquivos do repositório | 67 arquivos aprovados com PHP 8.0.30 do XAMPP |
| Sintaxe JavaScript | 5 arquivos aprovados com `node --check` |
| `git diff --check` | Aprovado nesta revisão |
| Migração de permissões no MariaDB descartável | `up`, repetição idempotente, preservação de preço configurado e `down` aprovados |
| Contraste das combinações principais (`#15173D`/`#F7F2EB`, `#625E72`/`#FFFDFA`, `#2F7138`/`#EAF5EA` e `#4C2365`/`#FFFDFA`) | 15,43:1; 6,15:1; 5,30:1; 11,92:1 — todas acima de 4,5:1 |
| Arquivos declarados no precache do Service Worker | 38 caminhos existentes verificados |
| Service Worker no navegador local | Controlando a página; único cache estático ativo observado: `sa-education-static-v8` |
| Cabeçalho HTTP do `sw.js` | Revalidação configurada após observar ausência de `Cache-Control`; verificação HTTP repetida após a alteração |
| Login institucional, recuperação de senha e arquivo do Service Worker | HTTP 200 no servidor local |
| Rotas principais sem sessão (`index`, Biblioteca, Salas, Feed, Questões, Perfil) e ranking público | As seis rotas privadas redirecionaram para `login.php` (HTTP 302); ranking respondeu HTTP 200 |
| Cookie de sessão local | `Secure=false` em HTTP localhost; `HttpOnly=true` e `SameSite=Lax` |
| Proteção HTTP de `.env.example`, schema e migração em `/db/` | HTTP 403 nos três recursos consultados |
| Acesso autenticado às páginas principais, envio real de e-mail SMTP e upload com GD | Pendente; exige configuração/validação manual |

**Limites da validação:** não foram executados testes integrados com MySQL/MariaDB real, entrega SMTP, navegador autenticado ou sessão institucional. O PHP disponível não carrega GD; a estratégia de reprocessamento foi validada por inspeção/lint, mas só pode ser exercitada após habilitar a extensão. Em produção, upload de foto falha de forma explícita sem GD. A navegação autenticada nas sete páginas, opt-in do ranking e persistência real após logout/login ainda precisam de validação manual. A ferramenta de navegador reportou uma largura efetiva de 312 CSS pixels mesmo após solicitar uma janela de 390px; portanto, ela não substitui a validação em dispositivo.

### Roadmap — próximas atualizações

Os itens abaixo são planejados, não implementados nesta versão. A ordem é indicativa e pode ser ajustada após a validação com usuários.

1. **Validar e estabilizar a versão de teste**
   - Percorrer Início, Biblioteca, Salas, Feed, Questões, Ranking e Meu perfil com sessão autenticada.
   - Repetir em desktop e celular, com cache existente e limpo, e após atualizações sucessivas.
   - Confirmar foco, rolagem, abertura/fechamento do menu e persistência de dados e foto no navegador.
   - Verificar instalação, ativação e atualização do service worker em ambiente HTTPS.
2. **Completar a experiência de salas e aprendizagem**
   - Definir os fluxos de criação e participação em salas para estudantes e professores.
   - Evoluir a Biblioteca para seleção e filtragem por matérias e organizar cursos/mentorias, após definir seus modelos e permissões.
   - Completar gestão de aulas e cursos Premium depois de implementar o modelo de cursos.
3. **Expandir perfil, comunidade e atividades**
   - Construir a área de configurações e preferências da conta.
   - Evoluir os formulários e a composição de postagens no Feed.
   - Implementar modelos, autoria e acompanhamento de bancos de questões, preservando as cotas mensais já impostas no servidor.
4. **Adicionar recursos que dependem de regras ou serviços externos**
   - Definir emissão e verificação de diplomas/certificados.
   - Implementar checkout e confirmação de pagamento dos planos com um provedor escolhido.
   - Integrar autenticação OAuth (Google e/ou Microsoft) após configurar aplicações, credenciais e URLs de callback.
   - Definir armazenamento de arquivos e mídia antes de implementar uploads de conteúdo além da foto de perfil.

## Visão geral técnica

A aplicação é um projeto PHP server-side com MySQL/MariaDB, sessões e PDO. O banco de dados é a fonte de verdade para perfis, salas, atividades, ranking e dados do dashboard. A implementação do service worker é uma base inicial de PWA; sua instalação e comportamento offline ainda dependem de verificação em navegador compatível e ambiente apropriado.

## Requisitos

- PHP 8.0+ com `pdo_mysql`; verificado nesta revisão com PHP 8.0.30. PHP 8.1+ é recomendado para suporte ao tipo de retorno nativo `never`.
- MariaDB / MySQL 10.4+ ou equivalente
- Servidor web Apache com XAMPP, WAMP ou similar
- Navegador moderno com suporte a PWA e service workers

## Instalação local

1. Clone ou copie o projeto para a pasta do servidor web.
2. Inicie o Apache e o MySQL no XAMPP.
3. Crie o banco `sa_education` se ele ainda não existir.
4. Importe a estrutura inicial com o arquivo:
   - `db/sa_education.sql`
5. Acesse a aplicação em:
   - `http://localhost/meus%20projetos/S.A%20Education/`
6. Faça login em uma conta previamente criada ou registre um novo usuário.

### Configuração do banco

Em `development`, `conexao.php` pode criar o banco e carregar o schema-base se a tabela `usuarios` ainda não existir, conforme `DB_AUTO_CREATE` e `DB_AUTO_MIGRATE`; isso não executa migrações funcionais. Bancos novos e existentes precisam das migrações CLI, incluindo `db/migrate_permissions.php up`, antes de usar as rotas autenticadas. Em `production`, a aplicação exige configuração explícita e nunca cria banco nem aplica schema em uma requisição. A aplicação lê variáveis do processo PHP/servidor; não carrega `.env` sozinha. Consulte o [README na raiz](../README.md) para os parâmetros e procedimento de migração.

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_PORT`

Os scripts de migração e limpeza do diretório `db/` devem ser executados apenas por CLI. Revise o conteúdo e faça backup antes de usar `db/remove_profile_demo_data.php`, que remove fixtures específicos conhecidos.

### Catálogo e pesquisa

`salas` fornece `nome`, `prof`, `materia`, `descricao` e `privacidade`; participantes são contados por vínculos ativos em `salas_usuarios`. `publicacoes` fornece tipo, categoria, conteúdo, status, visibilidade e `is_premium_only`, mas não possui preço nem confirmação de pagamento. `biblioteca` fornece título, autor, tipo, descrição, URLs, disponibilidade e status. Para persistir matéria dos materiais, aplique `php db/add_biblioteca_materia.php`; é uma coluna nullable e não preenche dados antigos. Cursos e mentorias não possuem tabela ou tipo de publicação e não são simulados.

O Feed usa `publicacoes` para posts e requer `publicacao_curtidas`, `publicacao_salvamentos` e `publicacao_comentarios`; em bancos existentes, aplique `php db/add_feed_interactions.php`. As tabelas novas usam chaves únicas e FKs em cascata, sem reescrever publicações existentes.

## Estrutura principal

- `index.php` — página inicial autenticada
- `login.php` e `cadastro.php` — fluxo de acesso
- `perfil-dashboard.php` — painel do usuário
- `salas.php` e `sala.php` — gerenciamento e acesso a salas
- `feed.php` e `pesquisa.php` — conteúdo e busca
- `api/` — endpoints de dados e filtros
- `includes/` — autenticação, helpers, navegação, dados
- `db/sa_education.sql` — schema e dados iniciais
- `manifest.json` — manifesto do PWA
- `sw.js` — entrada do service worker no escopo raiz da aplicação
- `assets/js/sw.js` — service worker com cache controlado

## PWA

O manifesto usa `./index.php`, escopo relativo e ícones PNG de 192x192 e 512x512. `assets/js/app.js` registra o service worker; `assets/js/sw.js` limita o cache a uma lista explícita de recursos estáticos, não intercepta APIs nem documentos de navegação e prevê uma resposta offline genérica. A versão do cache estático desta entrega é `v8`; a ativação remove caches antigos do mesmo prefixo. As respostas privadas não são armazenadas. A presença dos arquivos foi verificada, mas instalação, ativação, atualização no navegador e uso offline ainda não foram aprovados por teste manual nesta entrega.

## Testes executados nesta versão

- Ambiente local: PHP 8.0.30; o banco utilizado pelo projeto é MySQL/MariaDB.
- Testes automatizados aprovados: `subject_icon_test.php`, `profile_validation_test.php`, `dashboard_data_test.php`, `feed_actions_test.php` e `catalog_search_test.php` (SQLite em memória onde aplicável).
- Sintaxe PHP aprovada nos arquivos relacionados à entrega; `node --check` aprovado para `app.js`, `sidebar.js`, `sw.js` e `perfil.js`.
- A lista do service worker contém 38 recursos e todos existem no projeto.
- As respostas repetidas de CSS e do service worker testadas no servidor local retornaram HTTP 200 e conteúdo idêntico.
- Testes de navegador autenticado, integração SMTP e integração com banco real não executados nesta etapa.
- `git diff --check` aprovado.

Não foram concluídos nesta entrega os testes visuais autenticados, a navegação completa em desktop/celular, a ativação e atualização do service worker no navegador nem a confirmação de persistência de uma foto após logout/login. Consulte a seção [Roadmap — próximas atualizações](#roadmap--próximas-atualizações) antes de considerar esses fluxos validados.

### Reproduzir no XAMPP e phpMyAdmin

No PowerShell, na pasta do projeto:

```powershell
$php = 'C:\xampp\php\php.exe'
& $php -v
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & $php -l $_.FullName }
& $php tests\catalog_search_test.php
& $php tests\dashboard_data_test.php
& $php tests\feed_actions_test.php
& $php tests\profile_validation_test.php
& $php tests\subject_icon_test.php
node --check assets\js\app.js
node --check assets\js\sw.js
node --check assets\js\sidebar.js
node --check assets\js\perfil.js
```

No phpMyAdmin, selecione `sa_education`, exporte backup antes de alterações e consulte `INFORMATION_SCHEMA` para conferir tabelas, índices e chaves estrangeiras. Não reaplique migrações sem comparar o schema: o banco usado nesta revisão já tem a coluna de matéria e as três tabelas do Feed. Para testes no browser, inicie Apache/MySQL, crie uma conta descartável pela interface e remova-a depois; não use dados reais em logs ou capturas compartilhadas.

## Limitações conhecidas

- O dump contém dados demonstrativos e não deve ser importado em produção sem revisão.
- OAuth Google retorna HTTP 501; Microsoft não tem fluxo implementado.
- Tabelas e rótulos Premium não implementam checkout ou confirmação de pagamento.
- Upload de foto de perfil está implementado. Upload e armazenamento de outros arquivos ou mídias, incluindo materiais e aulas, ainda não estão disponíveis.
- Cursos, mentorias, diplomas/certificados e as regras comerciais dos planos ainda precisam de modelos, fluxos e validação antes de serem oferecidos.
- Para usar autenticação em banco existente, executar `db/apply_profile_migration.php` e `db/migrate_security_features.php` via CLI após backup; o segundo não remove registros e informa índices ignorados por duplicatas.
- Reprocessamento de fotos em produção requer GD; habilite e teste a extensão antes de habilitar uploads. No runtime PHP testado, GD não está carregada.

## Dicas de publicação

- Hospede em um ambiente com suporte ao PHP e MySQL
- Certifique-se de que o caminho do site reflita o diretório real do projeto
- Em produção, use HTTPS para permitir o comportamento adequado do PWA e cookies seguros
- Mantém a estrutura de arquivos e nomes de imagens atualizados para evitar falhas no manifest/service worker

## Observação sobre dados e segurança

As páginas autenticadas e os endpoints de dados dependem das sessões do PHP e das verificações no servidor. O cache do service worker não guarda respostas privadas nem conteúdo individualizado do usuário para evitar vazamento de informações pessoais.
