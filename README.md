# S.A Education

Plataforma web de educação com autenticação, salas de estudo, catálogo, ranking, perfil personalizado, dashboard e suporte inicial a PWA.

## Visão geral

A aplicação foi estruturada como um projeto PHP procedural com MySQL/MariaDB, usando sessões, PDO prepared statements e renderização server-side para cada página. O sistema usa o banco de dados como fonte de verdade para perfis, salas, exercícios, rankings e dados do dashboard.

### Estado funcional

| Estado | Recursos e evidência |
| --- | --- |
| Implementado e testado | Pesquisa/paginação de salas e catálogo, autorização de conteúdo, histórico vazio e real do dashboard, ações persistidas e autorização do Feed. Testes automatizados executados com SQLite em memória. |
| Implementado parcialmente | Cadastro, login/logout e perfil próprio foram percorridos com duas contas descartáveis; também foram verificados redirecionamento sem sessão, isolamento do perfil e uma tentativa de quiz persistida. Salas, biblioteca, ranking e feed existem, mas não há conteúdo local para validar todos os estados com dados. O código do service worker existe, porém o navegador local não apresentou worker ativo. |
| Pendente | Checkout/pagamentos, upload de arquivos, cursos/mentorias e fluxo Microsoft OAuth. |
| Bloqueado por configuração externa | OAuth Google depende de app/credenciais/callback; pagamentos e armazenamento remoto dependem de provedores e credenciais. Placeholders não contam como integração concluída. |

## Requisitos

- PHP 8.0+ com `pdo_mysql`; PHP CLI do XAMPP verificado nesta revisão: 8.0.30. O dump informa a versão usada na exportação pelo phpMyAdmin, não a versão mínima do projeto. O plano anterior exigia 8.1+ sem dependência exclusiva comprovada. `never` aparece em closures que sempre encerram com `exit`; PHP 8.1+ é recomendado para tipo de retorno nativo.
- MariaDB / MySQL 10.4+ ou equivalente
- Servidor web Apache com XAMPP, WAMP ou similar
- Navegador moderno com suporte a PWA e service workers

## Instalação local

1. Clone ou copie o projeto para a pasta do servidor web.
2. Inicie o Apache e o MySQL no XAMPP.
3. Crie o banco `sa_education` se ele ainda não existir.
4. Import a estrutura inicial com o arquivo:
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

O manifesto usa `./index.php`, escopo relativo e ícones PNG 192x192/512x512. `assets/js/app.js` registra o `sw.js` da raiz da aplicação; a implementação em `assets/js/sw.js` limita o cache a caminhos estáticos explícitos, não intercepta APIs nem documentos de navegação e prevê fallback offline genérico. O browser leu o manifesto e os ativos responderam HTTP 200, mas não apresentou worker ativo. Portanto, instalação e uso offline não estão aprovados. O evento `activate` remove caches antigos com o prefixo da aplicação; logout não limpa caches porque documentos e conteúdo pessoal não devem ser armazenados.

## Testes desta revisão

- PHP 8.0.30: sintaxe de todos os PHPs e três testes automatizados aprovados.
- JavaScript: `node --check` em `app.js`, `sw.js` e `feed.js` aprovado.
- MariaDB local 10.4.32: leitura de metadados confirmou 13 tabelas, 18 chaves estrangeiras e 42 entradas de índice; `biblioteca.materia` e as tabelas de interação do Feed já existem. Nenhuma migração foi executada.
- Navegador: cadastro, login, logout, acesso autenticado/não autenticado, perfil próprio e histórico vazio/com tentativa foram testados com contas descartáveis; registros temporários foram removidos. Pesquisa com/sem resultado, paginação, filtro de salas públicas/privadas, cabeçalhos `no-store`/`Vary: Cookie` e navegação desktop/mobile foram verificados. Perfil público de terceiros não existe no código. O worker não ficou ativo e o teste offline não foi concluído.

### Reproduzir no XAMPP e phpMyAdmin

No PowerShell, na pasta do projeto:

```powershell
$php = 'C:\xampp\php\php.exe'
& $php -v
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & $php -l $_.FullName }
& $php tests\catalog_search_test.php
& $php tests\dashboard_data_test.php
& $php tests\feed_actions_test.php
node --check assets\js\app.js
node --check assets\js\sw.js
node --check assets\js\feed.js
```

No phpMyAdmin, selecione `sa_education`, exporte backup antes de alterações e consulte `INFORMATION_SCHEMA` para conferir tabelas, índices e chaves estrangeiras. Não reaplique migrações sem comparar o schema: o banco usado nesta revisão já tem a coluna de matéria e as três tabelas do Feed. Para testes no browser, inicie Apache/MySQL, crie uma conta descartável pela interface e remova-a depois; não use dados reais em logs ou capturas compartilhadas.

## Limitações conhecidas

- O dump contém dados demonstrativos e não deve ser importado em produção sem revisão.
- OAuth Google retorna HTTP 501; Microsoft não tem fluxo implementado.
- Tabelas e rótulos Premium não implementam checkout ou confirmação de pagamento.
- URLs de arquivos existem no modelo, mas não há fluxo de upload/armazenamento remoto.
- `db/apply_profile_migration.php` não protege a adição repetida de FKs; inspecione o schema antes de executá-lo em banco existente.

## Dicas de publicação

- Hospede em um ambiente com suporte ao PHP e MySQL
- Certifique-se de que o caminho do site reflita o diretório real do projeto
- Em produção, use HTTPS para permitir o comportamento adequado do PWA e cookies seguros
- Mantém a estrutura de arquivos e nomes de imagens atualizados para evitar falhas no manifest/service worker

## Observação sobre dados e segurança

As páginas autenticadas e os endpoints de dados dependem das sessões do PHP e das verificações no servidor. O cache do service worker não guarda respostas privadas nem conteúdo individualizado do usuário para evitar vazamento de informações pessoais.
