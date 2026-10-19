## Plano de implementação e entrega — S.A Education

### Status verificado nesta revisão

Não considerar todos os módulos homologados. Os testes automatizados de catálogo/autorização, histórico do dashboard e ações do Feed passaram; o fluxo browser autenticado, o worker ativo e a operação offline não foram concluídos.

### Arquitetura real

- Backend: PHP 8.0+ + PDO MySQL (PHP CLI local do XAMPP: 8.0.30)
- Banco: MariaDB/MySQL, esquema carregado por `db/sa_education.sql`
- Sessões: segurança centralizada em `includes/helpers.php` e `includes/auth.php`
- Páginas: renderização server-side em PHP
- API: endpoints em `api/` para consulta e filtros
- Frontend: HTML, CSS e JS com comportamento responsivo
- PWA: `manifest.json` + `assets/js/sw.js` + registro em `assets/js/app.js`

### Classificação

- **Implementado e testado:** pesquisa/paginação de catálogo e salas; autorização por vínculo, propriedade e visibilidade; histórico vazio/real; persistência e autorização de ações do Feed; sintaxe PHP e JavaScript.
- **Implementado parcialmente:** cadastro, autenticação, logout e perfil próprio foram percorridos com duas contas descartáveis; redirecionamento sem sessão, isolamento entre perfis e persistência de uma tentativa real também foram verificados. Salas, biblioteca, ranking e Feed têm código real, mas a base local não tem materiais/publicações e os fluxos completos de autorização não foram todos percorridos no browser.
- **Implementado parcialmente:** manifesto e worker existem, caminhos estáticos responderam HTTP 200 e os ícones foram ajustados. O navegador criou registro sem worker ativo; instalação/offline não estão validados.
- **Pendente:** pagamentos/checkout, confirmação de assinatura, upload/armazenamento remoto, Microsoft OAuth e cursos/mentorias.
- **Bloqueado por configuração externa:** OAuth Google exige client ID/secret e callback configurado; pagamentos e armazenamento dependem de provedores e credenciais. O callback Google atual é placeholder HTTP 501.

### Requisitos de ambiente

- PHP 8.0+ com `pdo_mysql`; PHP CLI do XAMPP local verificado como 8.0.30. `never` é usado como tipo em closures que sempre executam `exit`; PHP 8.1+ é recomendado para semântica nativa desse tipo de retorno.
- PDO MySQL habilitado
- MySQL / MariaDB ativo
- XAMPP, WAMP ou Apache/NGINX com PHP
- HTTPS recomendado para publicação e PWA

### Processo de instalação e migrações

1. Faça backup do banco antes de importar ou alterar schema.
2. Inicie Apache e MySQL no XAMPP; importe `db/sa_education.sql` pelo phpMyAdmin em banco de desenvolvimento vazio.
3. Revise os dados demonstrativos do dump antes de qualquer uso em produção.
4. Em banco existente, inspecione `INFORMATION_SCHEMA`. `db/add_biblioteca_materia.php` e `db/add_feed_interactions.php` verificam os elementos ausentes; `db/apply_profile_migration.php` pode falhar se as FKs já existirem, pois seus `ALTER TABLE ... ADD CONSTRAINT` não são repetíveis.
5. Nesta revisão, nenhuma migração foi executada: o banco local já tem `biblioteca.materia`, tabelas de interação, 18 FKs e 42 entradas de índice.
6. Use contas descartáveis para testes e acesse pelo caminho real da pasta em `htdocs`.

### Evidência de teste

- Passaram `tests/catalog_search_test.php`, `tests/dashboard_data_test.php` e `tests/feed_actions_test.php`.
- Todos os PHPs passaram `php -l` no PHP 8.0.30; `app.js`, `sw.js` e `feed.js` passaram `node --check`.
- Browser: cadastro/login/logout com contas descartáveis, rota protegida, perfil próprio, isolamento entre contas e tentativa de quiz persistida; pesquisa com/sem resultados, filtros/paginação, tela desktop e navegação mobile. Manifesto/ativos estáticos acessíveis. O registro não chegou a worker ativo; offline não validado.

### Regras de cache e segurança

- Ativos estáticos são armazenados em cache
- Dados pessoais e respostas autenticadas não devem entrar em cache
- Páginas protegidas devem responder pelo servidor e não por conteúdo armazenado em cache
- Em ambientes offline, o fallback deve ser apenas para shell público e páginas não personalizadas
- O service worker fica restrito ao escopo permitido pelo navegador quando o arquivo está em `assets/js/sw.js`; não é um installable completo do tipo "app shell em raiz" sem ajuste de servidor adicional

### Checklist de entrega

- [x] Testes automatizados de catálogo, histórico e ações do Feed
- [x] Sintaxe PHP/JavaScript e consulta read-only ao MariaDB local
- [x] Revisão visual do login desktop/mobile
- [ ] Ciclo browser autenticado e autorização entre contas
- [ ] Worker ativo, instalação e modo offline
- [ ] OAuth, pagamentos e armazenamento remoto

### Próximo passo recomendado

- Integrar um provedor de pagamento e OAuth somente após a aprovação da arquitetura atual
- Manter os testes realizados em ambiente local como referência para deploy futuro
- Garantir que qualquer nova funcionalidade siga o padrão de dados e permissões já validado no projeto