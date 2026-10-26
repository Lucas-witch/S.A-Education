# S.A Education

Plataforma educacional PHP/MySQL em desenvolvimento. A entrega atual é a **primeira versão de teste**; ela não está aprovada para produção até que sejam concluídas a configuração segura do ambiente e as validações autenticadas descritas abaixo.

## Arquitetura do repositório

- As implementações das páginas Web estão organizadas por módulo em `apps/web/pages/`. Os arquivos PHP na raiz são pontos de compatibilidade que preservam URLs, formulários e redirecionamentos existentes no XAMPP.
- O projeto continua usando a raiz do repositório como DocumentRoot. Ative `mod_rewrite` e `AllowOverride All` no Apache para que os caminhos públicos `assets/...` sejam atendidos por `apps/web/public/assets/`. A pasta de páginas internas bloqueia acesso HTTP direto; as páginas devem ser acessadas pelos pontos de entrada da raiz.
- O backend continua compartilhado: `conexao.php`, `includes/` e `api/` mantêm a conexão, autenticação, permissões e regras de domínio usadas pela Web e pela API. A API mobile versionada permanece em `api/v1/`.
- `mobile/` é um cliente Expo/React Native independente. Ele consome contratos reais da API e não contém credenciais do banco nem cópias das páginas PHP.
- Migrações e schema permanecem em `db/` para preservar os comandos de implantação e o bootstrap do XAMPP; dados de desenvolvimento ficam em `seed/`, testes PHP em `tests/` e uploads permanecem fora dos recursos estáticos.

Veja o [mapa de módulos e responsabilidades](docs/architecture/monorepo.md) antes de mover outros arquivos ou alterar o DocumentRoot.

## Recursos nesta versão

- Navegação compartilhada e responsiva com menu lateral.
- Contas de estudante/professor e fluxo de acesso institucional separado.
- Edição do perfil e foto, com validação de imagem no servidor.
- Recuperação de senha por link temporário e de uso único, enviada por SMTP configurável.
- Convites institucionais para contas cadastradas, com aceite autenticado, revogação e consultas institucionais isoladas por vínculo ativo, validação e assinatura.
- Biblioteca digital com submissão e revisão de materiais, leitor autenticado, armazenamento privado de documentos, anotações e progresso de leitura, e gestão de empréstimos institucionais.
- Feed único para publicações escritas com formatação, anexos autorizados e videoaulas; anexos respeitam a visibilidade e a autorização da publicação.
- Cadernos imprimíveis de conjuntos públicos de questões, que podem ser salvos como PDF pelo recurso de impressão do navegador.
- Ranking público somente para participantes opt-in, com username público e sem participantes fictícios.
- Aplicação PHP server-side, PDO e banco MySQL/MariaDB.

Checkout/pagamentos, OAuth e criação/gestão ampla de conteúdos institucionais permanecem indisponíveis. O catálogo de planos, limites, benefícios e cancelamento de assinatura está integrado; sem provedor de pagamentos, nenhuma contratação paga ou confirmação financeira é iniciada pela plataforma. O ranking só mostra participantes para os quais exista pontuação e consentimento; esta versão ainda não define um fluxo completo de pontuação do quiz.

## Requisitos

- PHP 8.0+ e extensões `pdo_mysql`, `openssl`, `fileinfo`, `mbstring`, `dom` e `zip`; GD é necessário para reprocessar imagens de perfil em produção.
- MySQL/MariaDB compatível com o schema, Apache ou servidor equivalente.
- SMTP com STARTTLS ou TLS implícito para entrega de links de recuperação e convites institucionais.

## Desenvolvimento local

1. Use `.env.example` como referência e defina as variáveis no ambiente do PHP/Apache (por exemplo, `APP_ENV=development` e `SESSION_COOKIE_SECURE=false`). A aplicação **não carrega arquivos `.env` automaticamente**. Em acesso local por `localhost` ou `127.0.0.1`, a URL de redefinição/convite pode ser derivada da requisição; em produção, `APP_BASE_URL` HTTPS é obrigatória.
2. Em desenvolvimento, os padrões do XAMPP continuam disponíveis. Inicie Apache e MySQL e abra o diretório do projeto pelo servidor local.
3. Para um banco novo, importe `db/sa_education.sql` ou permita a inicialização local configurando `DB_AUTO_CREATE=true` e `DB_AUTO_MIGRATE=true`. A criação/importação do schema-base não executa as migrações funcionais.
4. Em banco novo ou existente, execute as migrações CLI. Em banco existente, faça backup antes:

   ```powershell
   $env:APP_ENV = 'development'
   $env:DB_HOST = '127.0.0.1'
   $env:DB_NAME = 'sa_education'
   $env:DB_USER = 'root'
   $env:DB_PASS = ''
   & 'C:\xampp\php\php.exe' db\apply_profile_migration.php
   & 'C:\xampp\php\php.exe' db\migrate_security_features.php
   & 'C:\xampp\php\php.exe' db\migrate_permissions.php up
   & 'C:\xampp\php\php.exe' db\migrate_admin_rbac.php up
   & 'C:\xampp\php\php.exe' db\migrate_institutional_portal.php up
   & 'C:\xampp\php\php.exe' db\migrate_digital_library.php up
   & 'C:\xampp\php\php.exe' db\migrate_user_preferences.php
   & 'C:\xampp\php\php.exe' db\migrate_video_learning.php up
   & 'C:\xampp\php\php.exe' db\migrate_feed_media.php up
   & 'C:\xampp\php\php.exe' db\migrate_question_catalog.php
   & 'C:\xampp\php\php.exe' db\migrate_admin_console.php up
   & 'C:\xampp\php\php.exe' db\migrate_feed_catalog.php up
   & 'C:\xampp\php\php.exe' db\migrate_room_communities.php
   & 'C:\xampp\php\php.exe' db\migrate_plans_system.php up
   ```

   `migrate_plans_system.php` depende de `migrate_permissions.php up`. Ela adiciona nome comercial, descrição, periodicidade, benefícios configuráveis, limites institucionais e metadados de transação/cancelamento sem substituir preços ou dados existentes; também cria o histórico de eventos e registra o estado inicial das assinaturas existentes. Execute-a em banco novo e existente, após backup. A migração é idempotente e não oferece rollback para evitar perda de histórico e configuração.

   O Feed depende de `migrate_feed_media.php up` para os metadados de anexos, de `migrate_admin_console.php up` para `publicacoes.is_official` e das migrações `migrate_institutional_portal.php up` e `migrate_room_communities.php` para as tabelas e colunas usadas na autorização de publicações institucionais e de sala. A migração `migrate_feed_catalog.php up` cria o índice usado pela ordenação e paginação do Feed; ela é idempotente e não altera publicações existentes. O catálogo exige `migrate_question_catalog.php`, que cria as tabelas e o cadastro-base de matérias/etapas sem criar questões de exemplo. Execute estas migrações após o schema-base e com backup; não ative conteúdo estático para contornar migrações pendentes.

   Os comandos de rollback destrutivos para estas duas migrações exigem a opção explícita:

   ```powershell
   & 'C:\xampp\php\php.exe' db\migrate_permissions.php down --confirm-data-loss
   & 'C:\xampp\php\php.exe' db\migrate_digital_library.php down --confirm-data-loss
   ```

   Faça backup antes da migração de permissões: professores existentes passam a aguardar aprovação e instituições existentes começam sem validação. Os rollbacks de permissões e biblioteca são destrutivos e não atômicos no MySQL/MariaDB; só podem ser chamados com `--confirm-data-loss`. O rollback de permissões pode remover catálogo/assinaturas/auditoria/vínculos/metadados de questões e campos associados; ele não mantém registro de propriedade dos objetos, portanto não o execute quando houver dúvida sobre tabelas/colunas preexistentes. O rollback da Biblioteca remove apenas tabelas/colunas que a migração registrou como criadas por ela; instalações antigas sem registro de propriedade recusam o rollback. Ambos devem ser testados em cópia do banco e não substituem backup. As migrações de ranking verificam duplicatas e não removem registros. Defina essas variáveis também no processo CLI do PHP; variáveis configuradas somente no Apache não são herdadas pelo terminal.

5. Configure as variáveis `SMTP_*` descritas abaixo para que os links de redefinição e os convites institucionais sejam realmente enviados. Cadastre contas descartáveis para validar os fluxos; não use dados reais em testes, logs ou capturas compartilhadas.

## Configuração segura de implantação

Defina `APP_ENV=production`, `APP_BASE_URL` em HTTPS, credenciais específicas do banco com privilégio mínimo, `APP_SECRET` aleatório com pelo menos 32 caracteres e cookies `SESSION_COOKIE_SECURE=true`. Configure ainda `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL` e `SMTP_FROM_NAME` no ambiente, nunca no Git.

Para Gmail, use `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587` e `SMTP_ENCRYPTION=tls`. Ative a verificação em duas etapas na conta Google e gere uma senha de app para `SMTP_PASSWORD`; use o endereço completo da conta em `SMTP_USERNAME` e `SMTP_FROM_EMAIL`. Configure esses valores no ambiente do Apache e reinicie o serviço. Não use a senha normal da conta nem compartilhe a senha de app no chat ou no repositório. Se o envio de um convite falhar, ele é marcado como não enviado e a tela exibe o erro; não é criado um vínculo de membro sem aceite.

Após as migrações, crie uma conta comum com credenciais individuais e confirme sua titularidade e `usuarios.id` (`SELECT id, username, perfil FROM usuarios WHERE username = 'usuario_escolhido'`). Em shell local controlado, atribua a função inicial sem criar senha padrão ou expor um formulário público:

```powershell
$userId = 123 # Substitua pelo usuarios.id verificado da conta escolhida.
& 'C:\xampp\php\php.exe' db\provision_admin.php grant $userId platform_admin
```

O comando solicita um motivo e confirmação literal `CONFIRMAR`, exige conta existente de estudante ou professor e grava evento de auditoria de origem CLI. Não passe senhas pelo terminal. A conta continuará usando o login normal e não receberá Premium por causa da função. Opcionalmente, configure `APP_ADMIN_USER_ID` para uma conta confirmada no ambiente do servidor: esse ID é o superadministrador de recuperação, com acesso integral e independente das atribuições RBAC. Mantenha esse segredo operacional fora do código. O painel de funções fica em `admin-acessos.php`; a administração principal fica em `admin.php`.

O RBAC possui funções `platform_admin`, `moderator`, `content_reviewer` e `auditor`, com permissões verificadas em cada página e operação protegida. O superadministrador pode atribuir funções administrativas pelo painel; somente o ID configurado em `APP_ADMIN_USER_ID` pode conceder `platform_admin` pelo painel. Moderadores revisam denúncias de salas e podem ocultar publicações denunciadas, com justificativa; revisores analisam a fila da Biblioteca e gerenciam conteúdo oficial; auditores leem os registros administrativos. O painel principal mantém as operações globais de usuários/instituições, planos, assinaturas e auditoria restritas a `platform_admin` ou `APP_ADMIN_USER_ID`. A conta institucional não pode receber função administrativa. O painel calcula indicadores das tabelas existentes, sem valores demonstrativos. A autoria de questões/categorias oficiais, bloqueios gerais de conta, notificações de decisão e recurso de moderação ainda não têm fluxos próprios nesta versão; o catálogo de questões continua exibindo somente conjuntos públicos publicados já existentes.

O painel revisa perfis docentes e instituições com justificativas registradas na auditoria, permite cadastrar e editar nomes, descrições, benefícios, periodicidade, preços, limites e disponibilidade no catálogo `product_plans`, e consulta assinaturas e seu histórico. Limites institucionais de membros/convites e turmas são validados no servidor; não há armazenamento institucional centralizado com quota implementada. Nenhuma ação administrativa confirma pagamento ou ativa Premium. A conta pode cancelar sua própria assinatura pendente/ativa; o cancelamento é imediato, registrado e impede que uma assinatura anterior volte a conceder acesso. O acesso é resolvido no servidor a partir da assinatura mais recente, confirmada, ativa, vigente e compatível com o tipo de conta. Confirme preços herdados do catálogo antes de qualquer lançamento; preços não são definidos pelo código de interface. Sem credenciais, SDK e endpoint de webhook de um provedor, contratação e renovação pagas permanecem indisponíveis. Professores aprovados podem receber uma permissão separada, concedida pelo administrador e registrada em auditoria, para produzir publicações oficiais; isso não os torna administradores. A autoria permanece vinculada à conta que publicou. As publicações oficiais usam o Feed existente, têm selo de identificação e guardam snapshots de criação, edição e arquivamento. Materiais da biblioteca continuam usando a revisão existente em `admin-biblioteca-revisao.php`.

Após backup, aplique `php db/migrate_admin_console.php up` antes de usar a produção de conteúdo oficial. A migração acrescenta `publicacoes.is_official` (com valor inicial falso para conteúdo antigo) e cria `official_content_history`, `official_content_editors` e o registro interno de propriedade `admin_console_migration_objects`. Ela não reescreve publicações existentes e recusa assumir objetos preexistentes sem registro de propriedade válido. O rollback exige backup e `php db/migrate_admin_console.php down --confirm-data-loss`; ele apaga os marcadores oficiais, o histórico, a lista de editores e o registro da migração, mas mantém as publicações. O DDL MariaDB/MySQL não é atômico; valide primeiro em uma cópia do banco.

`admin-sandbox.php` fornece uma pré-visualização administrativa de permissões com planos reais do catálogo, incluindo perfis pendentes, planos gratuitos/Premium e categorias institucionais. É deliberadamente somente leitura: não impersona uma sessão da aplicação nem habilita ações de escrita, não muda a sessão de permissões do administrador e não cria assinaturas, transações ou comprovantes. Cada início, troca e encerramento é auditado; a sessão de simulação expira após 30 minutos. Use homologação com dados fictícios para testar operações destrutivas ou o comportamento completo de páginas como outro perfil. As alterações em funções administrativas ficam em tabelas próprias e também na auditoria administrativa; reatribuições não alteram contas existentes até ação explícita de um administrador autorizado.

### Configurações e acessibilidade

A página autenticada `configuracoes.php` persiste tema, escala de fonte, contraste, redução de movimento, sublinhado de links e espaçamento de leitura em `user_preferences`, por meio da migração `db/migrate_user_preferences.php`. Execute essa migração após backup e antes de habilitar a página. A preferência é associada ao ID da conta, também para sessões institucionais; o tema automático acompanha cada dispositivo. O menu global contém o acesso à página. A edição de nome, nome de usuário, e-mail, foto e participação no ranking continua exclusivamente em `perfil-dashboard.php`.

Notificações, preferências de aprendizagem, autenticação em dois fatores, política de privacidade e exclusão de conta não têm integração funcional neste momento; a interface informa essa limitação em vez de apresentar controles inoperantes. O encerramento de outras sessões incrementa `auth_version`; a plataforma não exibe uma lista de sessões ativas.

`db/migrate_permissions.php up` cria o catálogo `product_plans`, que guarda os preços mensais definidos, limites de professores gratuitos, estados de aprovação/validação, assinaturas, auditoria e as tabelas de membros/convites institucionais. Reexecutar `up` preserva alterações de preço feitas no catálogo. Convites só podem ser enviados por instituição validada e com assinatura ativa; a pessoa convidada precisa aceitar autenticada com a conta cadastrada correspondente. Nenhuma assinatura é ativada sem confirmação de pagamento registrada por uma integração autorizada; não há checkout/provedor integrado nesta versão.

### Catálogo de questões

O botão Questões abre `quiz.php`, que organiza o catálogo por 13 matérias e áreas de conhecimento, permite filtrar por etapa sem duplicar matérias e mostra imagens locais já usadas pelo projeto. As quantidades exibidas vêm somente de conjuntos públicos publicados; não há banco de questões preenchido nesta versão. A antiga pergunta fixa era uma demonstração, não um quiz real, e não gera mais tentativas.

Após backup do banco, execute `php db/migrate_question_catalog.php` pelo CLI. A migração é aditiva e idempotente: cria o catálogo compartilhado de matérias/etapas, conjuntos de questões, pool de questões, alternativas e associação ordenada entre conjuntos e questões; insere os registros iniciais das 13 matérias sem sobrescrever dados existentes. Não remove publicações nem registros da tabela `tentativas_atividade`. Essa tabela existente continua sendo o histórico agregado; não se cria outra tabela de tentativas. A execução de questões, criação de conteúdo, simulados personalizados e aplicação efetiva das cotas durante uma futura autoria ainda não estão habilitadas. Os limites de criação existentes para professores gratuitos e Premium permanecem inalterados.

Conjuntos públicos publicados com questões publicadas podem ser preparados para impressão em `quiz-exportar.php` e salvos como PDF pelo navegador. O documento não inclui gabarito ou explicações. A exportação depende de o usuário ter acesso autenticado ao conjunto público; conjuntos privados, rascunhos e questões não publicadas não são exportados. O schema atual de questões não armazena imagens por questão ou alternativa, portanto esses elementos não aparecem no caderno.

### Salas, comunidades e Feed

Salas continuam armazenadas em `salas`, com participantes em `salas_usuarios`; não foi criado um segundo sistema de comunidades. A criação exige autorização calculada no servidor a partir de Premium ativo e aprovação do professor, validação e assinatura institucional ativa ou ID administrativo configurado. Participação é verificada separadamente. Salas públicas permitem entrada autenticada; salas privadas aceitam código de convite e solicitações avaliadas pelos responsáveis, inclusive novos pedidos após recusas anteriores; salas institucionais exigem vínculo ativo com a própria instituição e validação/assinatura vigente. Responsáveis e moderadores podem gerenciar participantes e solicitações. Denúncias de salas, publicações e comentários ficam pendentes para análise administrativa.

Discussões e compartilhamento de materiais reutilizam `publicacoes`, comentários e interações do Feed: publicações vinculadas a uma sala usam `visibilidade = sala` e ficam disponíveis somente a participantes autorizados. “Abrir discussões da sala” abre o Feed filtrado por essa sala. Não há mensagens em tempo real nem serviço externo. A imagem da sala continua usando o ícone local associado à matéria; upload de capa customizada não foi implementado.

Após backup, execute `php db/migrate_room_communities.php` via CLI antes de usar os novos fluxos. A migração acrescenta `criador_id`, `institution_id`, `categoria` e `area_conhecimento` à tabela `salas`, amplia o ENUM de `privacidade` para `instituicao`, cria `room_join_requests` e `room_reports` e, em tabelas já existentes, acrescenta os campos de alvo e revisor das denúncias. Ela remove índices únicos antigos que impediriam novos ciclos de solicitação ou denúncias por conteúdo e os substitui por índices adequados; não exclui registros. Não há rollback automatizado; restaure um backup consistente para reverter. Teste em cópia MySQL/MariaDB e verifique duplicatas antes da migração. Salas antigas permanecem; as que não têm `criador_id` só podem ser moderadas por participantes com papel de professor/moderador ou pela administração.

### Biblioteca digital e empréstimos

A Biblioteca aceita rascunhos e materiais enviados para revisão humana, com metadados de autoria/origem, declaração de direitos, evidência para conteúdo de terceiros e texto sanitizado no servidor. A edição de texto oferece títulos, parágrafos, listas, citações, alinhamento, links, tabelas, cores e tamanhos de fonte. São aceitos PDF, EPUB, DOCX, PPTX, XLSX, ODT, ODP, ODS, TXT, RTF e CSV, limitados a 20 MB por arquivo; estruturas ZIP de Office/OpenDocument/EPUB são verificadas antes do armazenamento. Esses arquivos são mantidos fora do diretório público, identificados por chave aleatória e servidos por rota autenticada que revalida caminho, tamanho e hash. PDFs podem ser lidos inline; outros formatos são baixados após a autorização. Isso reduz o acesso direto ao arquivo original, mas não impede capturas de tela, fotografias, gravação ou reprodução manual.

Em desenvolvimento XAMPP, o diretório privado padrão é `C:\xampp\sa-education-private-library`, fora de `htdocs`. Em produção, defina `PRIVATE_LIBRARY_STORAGE_PATH` para um diretório gravável fora do document root pelo usuário do servidor web. Mantenha `file_uploads=On`, configure `upload_max_filesize` e `post_max_size` acima do limite do arquivo (20 MB) e garanta que `upload_tmp_dir` seja gravável. Requisições acima do limite do PHP recebem uma mensagem específica em vez de falharem silenciosamente na validação CSRF.

O leitor registra posição e anotações por conta e material, e salva separadamente tamanho da fonte, espaçamento e modo de leitura. PDFs ficam em iframe autenticado; a posição dentro do visualizador PDF nativo não é rastreada pela aplicação. As anotações não são compartilhadas. O leitor pode exibir uma marca d'água com o nome da sessão, que também não impede cópia por captura de tela.

Instituições validadas e com assinatura ativa podem cadastrar exemplares físicos ou digitais vinculados a materiais institucionais aprovados. A instituição deve confirmar e registrar evidência de autorização legal antes de cadastrar um exemplar digital; isso é uma declaração administrativa, não uma verificação jurídica automática. Solicitações de leitores precisam ser aprovadas pela instituição, que define o prazo, registra a devolução e gerencia disponibilidade. Empréstimo de exemplar físico não desbloqueia arquivo digital.

Após backup, aplique `php db/migrate_digital_library.php up` em bancos novos ou existentes antes de usar leitor, anotações ou empréstimos. Essa migração acrescenta preferências de leitura, estado de solicitação, campos de autorização digital e permite datas nulas enquanto o empréstimo aguarda decisão. Não use `down` sem backup: além de remover dados de progresso, anotações e empréstimos, o DDL do MariaDB não é atômico. Não há dependências externas de editor ou gerador PDF: o editor é próprio, e a criação do PDF usa a opção de impressão do navegador.

### Feed de publicações e videoaulas

Publicações escritas, artigos e vídeos são criados e editados no Feed. O editor do Feed permite formatação de texto para artigos e publicações longas, enquanto a categoria de aula aceita link externo ou vídeo MP4/WEBM enviado (até 20 MB). Publicações também podem receber documentos e imagens dos formatos aceitos no Feed; os arquivos ficam no armazenamento privado e a rota de mídia verifica novamente autenticação, visibilidade da publicação e hash. Não há transcodificação, CDN ou serviço de streaming: para vídeos maiores, use um provedor de mídia autorizado e um link HTTPS. O limite local também depende das diretivas de envio do PHP.

Após backup, execute `php db/migrate_video_learning.php up` para os campos e tabelas usados pelas videoaulas e `php db/migrate_feed_media.php up` para os metadados opcionais dos anexos do Feed. As migrações são aditivas e preservam posts e URLs existentes. A migração de mídia não pode ser revertida enquanto houver anexos registrados.

### Portal institucional acadêmico

O portal institucional usa as contas, validações, membros, convites, assinaturas e catálogo de planos existentes. Os valores mensais permanecem configuráveis em `product_plans`; o portal não confirma pagamentos nem acrescenta benefícios, cotas ou faixas de usuários. Após backup e depois de `db/migrate_permissions.php up`, aplique `php db/migrate_institutional_portal.php up`.

O núcleo acadêmico organiza unidades hierárquicas (campus, faculdade/escola, departamento ou área), cursos/programas, períodos letivos, turmas, disciplinas, matrículas, atribuições de professores, avaliações, notas e auditoria institucional. Cada registro é associado à instituição e as relações entre hierarquia, turmas e disciplinas usam chaves compostas para impedir referências cruzadas. Gestores organizam a instituição; professores aprovados veem somente turmas/disciplinas atribuídas e estudantes vinculados veem suas turmas, avaliações publicadas e apenas as próprias notas já publicadas. A gestão e o professor responsável podem associar publicações já existentes e materiais de biblioteca aprovados a uma disciplina; estudantes veem os vínculos apenas nas turmas em que estão matriculados. Os leitores e o player continuam revalidando o acesso ao abrir cada recurso. O vínculo com a instituição e a assinatura ativa são revalidados no servidor; notas não publicadas permanecem privadas. A suspensão administrativa também bloqueia os escopos centrais de Feed, salas, busca, biblioteca e videoaulas. Aprovação/rejeição e assinatura expirada continuam sendo estados distintos.

Abra `instituicao-portal.php` pela navegação para a área acadêmica. Convites e revogações de estudantes/professores continuam no painel institucional; o portal acadêmico exige vínculo ativo. A gestão ou o professor atribuído também pode publicar uma videoaula institucional diretamente vinculada à disciplina. Publicações e materiais da biblioteca não são duplicados: a gestão ou o professor responsável os associa à turma/disciplina e o estudante matriculado os abre pelos leitores existentes. O player autentica a página, não a URL de mídia hospedada por terceiros; não use URL que exija proteção contra download ou compartilhamento. As avaliações atuais organizam instruções e prazos e permitem registro/publicação manual de notas; aplicação online com respostas de estudantes, correção automática e tentativas ainda não foi implementada. Salas de estudo atuais continuam sendo comunidades independentes, não turmas acadêmicas. O esquema atual de videoaulas não oferece videoconferência; existe uma interface de provedor sem adaptador, e nenhuma aula ao vivo é simulada.

A migração `db/migrate_institutional_portal.php` mantém registro dos objetos que criou e inclui `institution_learning_resources`, que referencia publicações e materiais existentes sem replicá-los. Para rollback, faça backup e execute `php db/migrate_institutional_portal.php down --confirm-data-loss`; os vínculos curriculares e registros acadêmicos criados por ela serão removidos e o DDL do MariaDB não é atômico. Teste em uma cópia do banco antes de aplicar em produção. Políticas de retenção/exclusão de dados acadêmicos, tentativas e correção de avaliações, regras de avaliação por instituição, integração comercial de pagamentos e escolha/configuração do provedor de aulas ao vivo continuam pendentes de decisões administrativas e pedagógicas.

### Videoaulas e anúncios

As aulas continuam usando as publicações `video_aula` existentes no Feed. O editor agora guarda etapa de ensino, área, disciplina, tema/subtópico, nome de curso/sequência, materiais complementares, exercícios relacionados e URL de legendas. A reprodução autenticada usa `videoaula.php?id=...`; o progresso é salvo no servidor e a página retoma a posição, sem declarar a aula concluída apenas por abri-la. O player atual aceita arquivos de vídeo diretos compatíveis com HTML5 (MP4, WebM e Ogg), oferece controles nativos, legendas WebVTT e navegação para a próxima aula com o mesmo título de curso.

Videoaulas legadas que já exigem Premium continuam visíveis e reproduzíveis somente por pessoas com entitlement Premium (ou pela própria autoria); videoaulas privadas preservam a visibilidade ao serem editadas. Novas publicações criadas pelo formulário "Aula gratuita" continuam públicas e gratuitas.

Execute `db/migrate_video_learning.php up` após backup antes de usar o editor/player em um banco existente. O `up` registra quais colunas e tabelas ele próprio criou; objetos que já existiam não são considerados propriedade desta migração. Para reverter, faça backup e execute `php db/migrate_video_learning.php down --confirm-data-loss`. O `down` exige essa confirmação e remove somente objetos registrados como criados por esta migração; instalações antigas sem esse registro recusam o rollback por segurança. O histórico de progresso e intervalos pode ser perdido, e o DDL de MariaDB é não atômico. A publicação por Feed não cria entidade de cursos: a associação de sequência usa o campo de curso nas publicações já existentes. Há um catálogo independente de questões/quizzes, mas ainda não uma entidade de curso que vincule esse catálogo ou crie avaliações de curso. Também não há provedor de streaming privado nesta versão.

O heartbeat é autenticado por sessão, protegido contra repetição concorrente e contabiliza tempo de parede somente quando a posição do vídeo também avança; saltos incompatíveis com o tempo decorrido são ignorados. Cada conta tem uma única sessão ativa por aula, e reabrir a página renova o token sem apagar tempo já reproduzido. Esses controles limitam abuso casual, mas não provam que um dispositivo exibiu cada quadro: um cliente modificado pode falsificar eventos. Não use essa contagem como prova de presença ou avaliação.

Não existe provedor de anúncios configurado no repositório. A experiência mostra explicitamente **modo de teste**, registra os marcos de 10 minutos como `not_configured` e continua o vídeo sem anunciar que um anúncio real foi exibido; falhas/ausência do provedor não bloqueiam a aula. A interface `VideoAdProvider` em `includes/video_learning.php` isola status, solicitação do intervalo e verificação de recibo para um futuro adaptador. Será necessário implementar e validar o SDK/recibo do fornecedor escolhido antes de contar impressões reais ou bloquear a retomada durante um anúncio. Até haver confirmação comercial, contas Premium também mantêm os intervalos; somente defina `VIDEO_PREMIUM_AD_FREE=true` quando a política for oficialmente aprovada. Administradores são excluídos dos intervalos. Anúncios não são inseridos em exercícios nem no leitor da Biblioteca.

As videoaulas existentes armazenam URL em `publicacoes.video_url`. O player protege o acesso à página com sessão/permissões, mas uma URL externa permanente fornecida pelo autor pode ser observada no navegador depois da autorização. Para mídia privada, use um provedor com URLs assinadas/temporárias e integração server-side; não coloque chaves, tokens permanentes ou credenciais no campo de URL. A reprodução atual não faz proxy de mídia e não transforma automaticamente links de YouTube/Vimeo em player rastreável.

Em produção, a aplicação não cria banco nem aplica schema automaticamente. Execute as migrações depois de backup e revisão do resultado. A configuração de SMTP não foi testada com um provedor real nesta entrega.

## Testes

Use o PHP do XAMPP:

```powershell
$php = 'C:\xampp\php\php.exe'
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & $php -l $_.FullName }
Get-ChildItem .\tests\*.php | ForEach-Object { & $php $_.FullName }
Get-ChildItem .\assets\js\*.js | ForEach-Object { node --check $_.FullName }
```

Os testes automatizados não substituem a validação em navegador autenticado, MySQL/MariaDB, envio SMTP ou teste de Service Worker em desktop e celular. Consulte [o relatório e roadmap da primeira versão de teste](./Markdowns/README.md) para as evidências, limitações e próximos passos.
