# S.A Education

Plataforma web de educação com autenticação, salas de estudo, catálogo, ranking, perfil personalizado, dashboard e suporte inicial a PWA.

## Visão geral

A aplicação foi estruturada como um projeto PHP procedural com MySQL/MariaDB, usando sessões, PDO prepared statements e renderização server-side para cada página. O sistema usa o banco de dados como fonte de verdade para perfis, salas, exercícios, rankings e dados do dashboard.

### Funcionalidades implementadas

- Autenticação de usuários com login e senha
- Cadastro de estudantes, professores e instituições
- Dashboard de perfil com dados reais do banco
- Lista de salas, filtros e acesso controlado para salas privadas
- Feed e pesquisa com filtros por categoria e visibilidade
- Biblioteca e ranking
- Navegação lateral e inferior responsiva
- PWA com manifest e service worker seguro

### Funcionalidades pendentes ou externas

- Login OAuth com Google/Microsoft ainda não configurado
- Provedor de pagamentos / assinatura premium ainda não implementado
- Armazenamento de arquivos em nuvem ainda não configurado
- Integrações externas dependem de credenciais e ambiente de produção

## Requisitos

- PHP 8.1+ com extensão PDO MySQL habilitada
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
- `assets/js/sw.js` — service worker com cache controlado

## PWA e instalação

O projeto inclui manifesto e serviço de cache compatível com ambiente local e subdiretórios. O arquivo `manifest.json` aponta para `./index.php`, usa escopo local e referencia o ícone real disponível em `assets/images/logo-app.png`.

O navegador exige que o Service Worker esteja em um escopo que não exceda o diretório do script. Como o worker está em `assets/js/sw.js`, o registro válido fica restrito ao escopo do próprio diretório de assets. Isso evita prometer uma instalação completa de app em um escopo maior do que o navegador permite. A estratégia de cache evita guardar conteúdo privado ou respostas autenticadas do usuário:

- cache de ativos estáticos: CSS, JS, imagens, manifest
- HTML de navegação: utiliza rede e fallback seguro sem armazenar páginas personalizadas
- API e dados protegidos: não entram em cache
- páginas autenticadas: não são armazenadas em cache para evitar vazamento de identidade e dados do usuário

## Testes recomendados

1. Login com usuário válido e inválido
2. Cadastro com duplicidade de e-mail/usuário
3. Visualização de dashboard e dados do perfil
4. Acesso a sala pública versus privada
5. Uso de filtros de pesquisa e catálogo
6. Navegação mobile com bottom nav
7. Verificação do PWA em navegador moderno
8. Teste de rede indisponível para confirmar que não há exposição de dados privados em cache

## Limitações conhecidas

- A integração OAuth com Google/Microsoft está documentada como pendente
- O fluxo de pagamento premium ainda não está implementado
- O projeto não depende de “dados mockados” para dados críticos; quando a base estiver indisponível, as páginas devem responder conforme o comportamento do backend

## Dicas de publicação

- Hospede em um ambiente com suporte ao PHP e MySQL
- Certifique-se de que o caminho do site reflita o diretório real do projeto
- Em produção, use HTTPS para permitir o comportamento adequado do PWA e cookies seguros
- Mantém a estrutura de arquivos e nomes de imagens atualizados para evitar falhas no manifest/service worker

## Observação sobre dados e segurança

As páginas autenticadas e os endpoints de dados dependem das sessões do PHP e das verificações no servidor. O cache do service worker não guarda respostas privadas nem conteúdo individualizado do usuário para evitar vazamento de informações pessoais.
