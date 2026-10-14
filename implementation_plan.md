## Plano de implementação e entrega — S.A Education

### Status do projeto

O projeto já está em uma arquitetura funcional e realista para ambiente web PHP + MySQL. A base mais importante foi concluída: autenticação, perfis, dashboard, salas, filtros de pesquisa, catálogo, ranking, navegação responsiva e PWA com manifest/service worker seguro.

### Arquitetura real

- Backend: PHP 8 + PDO MySQL
- Banco: MariaDB/MySQL, esquema carregado por `db/sa_education.sql`
- Sessões: segurança centralizada em `includes/helpers.php` e `includes/auth.php`
- Páginas: renderização server-side em PHP
- API: endpoints em `api/` para consulta e filtros
- Frontend: HTML, CSS e JS com comportamento responsivo
- PWA: `manifest.json` + `assets/js/sw.js` + registro em `assets/js/app.js`

### Funcionalidades concluídas

- Cadastro e login locais
- Validação de perfis
- Dashboard com dados reais do banco
- Acesso e listagem de salas
- Controle de salas privadas e membros ativos
- Pesquisa e catálogo de conteúdo
- Perfil com histórico e indicadores dos exercícios
- Estrutura mobile/desktop com navegação inferior
- Manifesto PWA e registro do service worker

### Funcionalidades pendentes / externas

- OAuth Google/Microsoft: dependente de credenciais externas
- Pagamento premium: depende de provedor externo
- Upload de arquivos e armazenamento em nuvem: ainda não implementado
- Publicação avançada de conteúdo multimídia: depende de infraestrutura adicional

### Requisitos de ambiente

- PHP 8.1+
- PDO MySQL habilitado
- MySQL / MariaDB ativo
- XAMPP, WAMP ou Apache/NGINX com PHP
- HTTPS recomendado para publicação e PWA

### Processo de instalação

1. Copiar o projeto para a pasta do servidor web
2. Iniciar Apache + MySQL no XAMPP
3. Criar ou garantir o banco `sa_education`
4. Importar `db/sa_education.sql` ou deixar `conexao.php` fazer a criação automática
5. Acessar a aplicação pelo caminho real do projeto
6. Registrar ou logar em conta existente

### Procedimento de teste

- Testar cadastro e login
- Verificar dashboard e histórico do usuário
- Validar acesso a salas públicas e privadas
- Verificar filtros de pesquisa e catálogo
- Confirmar layout desktop/mobile
- Validar manifest e registration do service worker
- Simular perda de conexão para verificar comportamento seguro do cache

### Regras de cache e segurança

- Ativos estáticos são armazenados em cache
- Dados pessoais e respostas autenticadas não devem entrar em cache
- Páginas protegidas devem responder pelo servidor e não por conteúdo armazenado em cache
- Em ambientes offline, o fallback deve ser apenas para shell público e páginas não personalizadas
- O service worker fica restrito ao escopo permitido pelo navegador quando o arquivo está em `assets/js/sw.js`; não é um installable completo do tipo "app shell em raiz" sem ajuste de servidor adicional

### Checklist de entrega

- [x] Banco, schema e dados reais configurados
- [x] Fluxos principais operando com autenticação
- [x] Permissões e salas privadas validadas no backend
- [x] Navegação e design responsivos
- [x] PWA com manifesto e registro em runtime
- [x] Documentação alinhada com a estrutura real
- [ ] OAuth externo e pagamentos ainda dependem de integração profissional

### Próximo passo recomendado

- Integrar um provedor de pagamento e OAuth somente após a aprovação da arquitetura atual
- Manter os testes realizados em ambiente local como referência para deploy futuro
- Garantir que qualquer nova funcionalidade siga o padrão de dados e permissões já validado no projeto