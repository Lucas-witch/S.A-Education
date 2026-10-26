# Arquitetura e responsabilidades

O repositório mantém uma migração gradual: a implementação das páginas Web foi organizada sem trocar o DocumentRoot do XAMPP nem alterar os URLs públicos existentes.

## Aplicações

### Web

As implementações PHP estão em `apps/web/pages/`, agrupadas por responsabilidade:

| Módulo | Conteúdo | Implementações movidas da raiz |
| --- | --- | --- |
| `home/` | Página inicial | `index.php` |
| `auth/` | Login, cadastro, logout, recuperação de conta e convites | `aceitar-convite-instituicao.php`, `cadastro.php`, `cadastro-instituicao.php`, `login.php`, `login-instituicao.php`, `logout.php`, `processa_cadastro.php`, `processa_cadastro_instituicao.php`, `processa_login.php`, `processa_login_instituicao.php`, `processa_redefinir_senha.php`, `redefinir-senha.php` |
| `feed/` | Feed e entrega de mídia autorizada | `feed.php`, `feed-media.php` |
| `questions/` | Catálogo e exportação de questões | `processa_quiz.php`, `quiz.php`, `quiz-exportar.php` |
| `learning/` | Salas, pesquisa, ranking e videoaulas | `criar-sala.php`, `entrar-sala.php`, `pesquisa.php`, `ranking.php`, `sala.php`, `salas.php`, `videoaula.php` |
| `books/` | Biblioteca, leitor, empréstimos, arquivos e submissão de materiais | `biblioteca.php`, `biblioteca-arquivo.php`, `biblioteca-editor.php`, `biblioteca-emprestimos.php`, `biblioteca-leitor.php`, `processa_material_biblioteca.php` |
| `plans/` | Catálogo de planos | `planos.php` |
| `profile/` | Perfil e preferências/configurações | `configuracoes.php`, `perfil-dashboard.php`, `processa_configuracoes.php`, `processa_perfil.php` |
| `institutions/` | Portal institucional e gestão de membros | `instituicao-dashboard.php`, `instituicao-portal.php`, `processa_membros_instituicao.php` |
| `administration/` | Administração, auditoria, permissões e moderação | `admin.php`, `admin-acessos.php`, `admin-auditoria.php`, `admin-biblioteca-revisao.php`, `admin-conteudo-oficial.php`, `admin-moderacao.php`, `admin-permissoes.php`, `admin-sandbox.php` |

Os arquivos PHP correspondentes na raiz são pontos de entrada compatíveis. Eles carregam `apps/web/bootstrap.php` e encaminham a execução para o módulo, mantendo o `PHP_SELF` e o endereço usados por links, formulários e redirecionamentos. Não remova esses pontos de entrada até que os consumidores dos URLs legados sejam migrados.

Os recursos da Web ficam em `apps/web/public/assets/`. O `.htaccess` da raiz reescreve internamente o prefixo `assets/` para esse local. O acesso aos arquivos PHP em `apps/web/pages/` é bloqueado diretamente pelo `.htaccess` local; use sempre os pontos de entrada da raiz.

### Mobile

`mobile/` contém o cliente Android Expo/React Native: telas, navegação, componentes, tema, serviços de API, testes e armazenamento seguro da sessão. Ele usa os endpoints versionados em `/api/v1/`; o estado real de cada fluxo está documentado em `mobile/README.md`. A organização não significa que todos os módulos já estejam disponíveis no aplicativo.

### Backend compartilhado

- `conexao.php`: configuração e conexão PDO, preservada na raiz para compatibilidade com scripts existentes e o bootstrap do ambiente.
- `includes/`: autenticação, autorização, serviços e componentes compartilhados pelas páginas e endpoints.
- `api/`: endpoints legados; `api/v1/` fornece os contratos versionados consumidos pelo mobile e delega a lógica às funcionalidades existentes.
- `db/`: schema e migrações. A localização atual é mantida porque os comandos CLI, o carregamento do schema e a documentação de implantação dependem desses caminhos.
- `seed/`: dados de desenvolvimento. `uploads/` não é parte dos recursos públicos e deve continuar sujeito às regras de autorização do módulo que o utiliza.

## URLs e execução no XAMPP

Mantenha a raiz do checkout como DocumentRoot para conservar URLs como `/login.php`, `/feed.php` e `/api/v1/auth.php`. O Apache precisa permitir `.htaccess`, ter `mod_rewrite` habilitado e suportar as diretivas de autorização usadas no projeto. O rewrite de `assets/` mantém os caminhos HTML/CSS atuais, incluindo o logotipo oficial `assets/images/logo-app-512.png`.

O backend, banco e sessão continuam compartilhados entre Web e Mobile. O aplicativo mobile não deve acessar MySQL diretamente; configure apenas a URL pública da API no ambiente Expo. Consulte a seção de desenvolvimento deste README e `mobile/README.md` para os requisitos e comandos de cada aplicação.

## Limites da migração

As páginas Web e os recursos estáticos foram organizados com pontos de entrada legados. `conexao.php`, `includes/`, `api/`, `db/`, `seed/` e `uploads/` permanecem nos locais atuais para não quebrar consumidores de caminho nem comandos de migração. Qualquer reorganização futura desses diretórios deve incluir atualização coordenada dos includes, endpoints, testes, scripts CLI e documentação, sem apagar dados nem executar rollback destrutivo.
