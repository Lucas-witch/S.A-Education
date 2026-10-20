# S.A Education — primeira versão de teste

Relatório da primeira versão de teste da plataforma S.A Education. Esta entrega reúne a navegação compartilhada, o perfil do usuário, as principais páginas acadêmicas e uma primeira etapa de estabilização visual. O projeto continua em desenvolvimento: recursos descritos como próximos passos não devem ser considerados disponíveis nesta versão.

**Versão:** primeira versão de teste · **Relatório atualizado:** 19 de outubro de 2026 · **Estado:** em validação; não aprovada para produção.

## Relatório da versão

### O que foi feito nesta etapa

- **Menu lateral:** fechado por padrão; pode ser aberto pelo botão do cabeçalho e fechado pelo botão próprio, pelo backdrop ou pela tecla `Escape`. O controle sincroniza foco, estado ARIA e bloqueio/restauração da rolagem. Em telas móveis, o painel sobrepõe o conteúdo e a navegação inferior não fica visível ao mesmo tempo.
- **Navegação compartilhada:** centralizados os links de Início, Salas, Biblioteca/Cursos, Feed, Questões, Ranking e Meu perfil. Os rótulos da navegação móvel são derivados da mesma lista para evitar diferenças em relação ao menu.
- **Perfil:** implementada edição de nome, e-mail e nome de usuário, com validação de unicidade, proteção CSRF, uso do usuário autenticado e persistência via PDO. A foto aceita JPEG, PNG e WebP até 5 MB, é validada no servidor e pode ser pré-visualizada, alterada ou removida. `usuarios.perfil_imagem` é a fonte canônica e os dados exibidos são sincronizados com a sessão.
- **Estilos compartilhados:** removidas redefinições do shell que alteravam a geometria do cabeçalho e da navegação entre páginas; organizadas regras e breakpoints globais, preservando estilos específicos de páginas.
- **Atualização de recursos:** service worker avançado para o cache estático `v7`, com precache de caminhos explícitos, invalidação de caches estáticos anteriores e registro sem cache HTTP do script do worker. Documentos de navegação e respostas privadas não são armazenados pelo cache estático.
- **Ícones e salas:** integrados os novos ícones à navegação e às matérias; a página inicial lista salas com vínculo ativo do usuário em vez de usar a lista geral. Entradas de salas de demonstração foram removidas do SQL/seeder e da base local usada no desenvolvimento.
- **Planos:** adicionada uma página informativa que apresenta os planos e recursos cadastrados. Não há checkout nem confirmação de pagamento nesta versão.

### Validação executada nesta entrega

| Verificação | Resultado |
| --- | --- |
| Testes automatizados PHP de ícones, perfil, dashboard, Feed e catálogo | Aprovados |
| Sintaxe PHP dos arquivos relacionados | Aprovada |
| Sintaxe JavaScript (`app.js`, `sidebar.js`, `sw.js` e `perfil.js`) | Aprovada |
| `git diff --check` | Aprovado |
| Arquivos declarados no precache do service worker | 38 caminhos verificados; nenhum ausente |
| Respostas repetidas das folhas CSS e do service worker no servidor local | HTTP 200 e conteúdo idêntico nas requisições executadas |
| Acesso às páginas sem sessão | Início, Biblioteca, Salas, Feed, Questões e Perfil redirecionaram para login; Ranking respondeu HTTP 200 |

**Limites da validação:** os testes automatizados não substituem a navegação visual autenticada. Nesta etapa não foram concluídos testes de navegador com usuário autenticado, comparação das sete páginas em desktop e celular, atualização real do service worker ativo, fluxo completo de upload após logout/login nem teste em navegadores diferentes. Portanto, a estabilidade visual e a navegação autenticada ainda precisam de validação manual.

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
   - Implementar publicação e acompanhamento de aulas, incluindo limites por plano quando as regras comerciais forem aprovadas.
3. **Expandir perfil, comunidade e atividades**
   - Construir a área de configurações e preferências da conta.
   - Evoluir os formulários e a composição de postagens no Feed.
   - Ampliar atividades e questões com histórico, acompanhamento e regras de progressão definidos.
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

O arquivo `conexao.php` tenta criar o banco automaticamente se ele não existir e também carrega o esquema quando a tabela `usuarios` ainda não está presente. As configurações podem ser ajustadas por variáveis de ambiente:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_PORT`

Em bancos que já foram inicializados com o histórico demonstrativo do perfil, faça backup e execute `php db/remove_profile_demo_data.php`. O script remove somente tentativas e participações que correspondam exatamente aos fixtures conhecidos; não apaga usuários, salas nem outros registros.

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

O manifesto usa `./index.php`, escopo relativo e ícones PNG de 192x192 e 512x512. `assets/js/app.js` registra o service worker; `assets/js/sw.js` limita o cache a uma lista explícita de recursos estáticos, não intercepta APIs nem documentos de navegação e prevê uma resposta offline genérica. A versão do cache estático desta entrega é `v7`; a ativação remove caches antigos do mesmo prefixo. As respostas privadas não são armazenadas. A presença dos arquivos e a estabilidade das respostas HTTP foram verificadas, mas instalação, ativação, atualização no navegador e uso offline ainda não foram aprovados por teste manual nesta entrega.

## Testes executados nesta versão

- Ambiente local: PHP 8.0.30; o banco utilizado pelo projeto é MySQL/MariaDB.
- Testes automatizados aprovados: `subject_icon_test.php`, `profile_validation_test.php`, `dashboard_data_test.php`, `feed_actions_test.php` e `catalog_search_test.php` (SQLite em memória onde aplicável).
- Sintaxe PHP aprovada nos arquivos relacionados à entrega; `node --check` aprovado para `app.js`, `sidebar.js`, `sw.js` e `perfil.js`.
- A lista do service worker contém 38 recursos e todos existem no projeto.
- As respostas repetidas de CSS e do service worker testadas no servidor local retornaram HTTP 200 e conteúdo idêntico.
- Sem sessão autenticada, Início, Biblioteca, Salas, Feed, Questões e Meu perfil redirecionaram para `login.php`; Ranking respondeu HTTP 200. Esses resultados não validam a experiência autenticada.
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
- `db/apply_profile_migration.php` não protege a adição repetida de FKs; inspecione o schema antes de executá-lo em banco existente.

## Dicas de publicação

- Hospede em um ambiente com suporte ao PHP e MySQL
- Certifique-se de que o caminho do site reflita o diretório real do projeto
- Em produção, use HTTPS para permitir o comportamento adequado do PWA e cookies seguros
- Mantém a estrutura de arquivos e nomes de imagens atualizados para evitar falhas no manifest/service worker

## Observação sobre dados e segurança

As páginas autenticadas e os endpoints de dados dependem das sessões do PHP e das verificações no servidor. O cache do service worker não guarda respostas privadas nem conteúdo individualizado do usuário para evitar vazamento de informações pessoais.
