# Redesign das páginas — S.A Education

> Documento de direção visual e funcional para orientar a reformulação das páginas existentes. Não representa, por si só, implementação das funcionalidades de backend.

## 1. Direção visual compartilhada

### Identidade
- Roxo principal: `#71308B`
- Roxo secundário: `#8E45A5`
- Lavanda de apoio: `#F0E5F5`
- Verde de ação: `#BFE7A9`
- Fundo off-white: `#FAF7F2`
- Superfície dos cartões: `#FFFDF9`
- Texto principal: `#211A25`
- Texto secundário: `#766E7A`
- Borda: `#E7E0D9`

Usar roxo para identidade, navegação ativa, links e ações secundárias. Reservar verde para a ação principal ou confirmação positiva; não pintar todos os botões de verde. Manter bastante off-white, cartões com bordas discretas, sombras suaves e raio de 14–18 px. Evitar gradientes pesados, contornos fortes, excesso de badges e ícones emoji.

### Tipografia e espaçamento
- Inter ou a fonte sem serifa já configurada no projeto.
- Título de página: 26–30 px desktop; 22–26 px mobile.
- Títulos de cartões: 14–17 px; corpo 12–14 px; metadados 10–12 px.
- Escala de espaçamento baseada em 4/8/12/16/24/32 px.
- Foco visível por teclado, contraste suficiente e alvos de toque com cerca de 44 px.

### Navegação
- Desktop: menu lateral persistente com ícone, rótulo e estado ativo.
- Mobile: cabeçalho compacto e navegação inferior persistente, incluindo Início, Salas, Cursos, Feed, Questões e Ranking. Seis destinos podem ser distribuídos em uma barra rolável ou em cinco destinos principais mais um item “Mais”, desde que todos continuem facilmente acessíveis e o comportamento seja consistente.
- Logo centralizado no cabeçalho superior, circular, sem o texto “S.A Education” ao lado, conforme orientação anterior.
- A guia rápida deve ser um controle independente, pequeno e sem fundo decorativo; abrir um drawer com animação discreta e fechar por botão, clique fora e Escape.

## 2. Feed — `feed.php`

### Objetivo
Ser o espaço de compartilhamento e descoberta de conhecimento, e não uma página de anúncios ou um mural genérico.

### Desktop
- Área de conteúdo principal com largura aproximada de 640–700 px.
- Coluna lateral opcional de 260–300 px para assuntos em alta e acesso a aulas gratuitas.
- No topo, compositor compacto “Compartilhe algo que aprendeu”, com ações de material, questão e aula gratuita.
- Cards de publicação com avatar, nome, tipo de usuário, horário, tipo de conteúdo, título, texto, matéria/hashtags e ações de curtir, comentar e salvar.
- Aulas publicadas no feed precisam estar marcadas como gratuitas; não mostrar preço em aula de feed.
- Menu de contexto por publicação para ações permitidas pelo proprietário/moderador.

### Mobile
- Uma única coluna, sem a coluna lateral.
- Compositor compacto e cards de largura total.
- Metadados discretos; ações com espaço suficiente para toque.
- A navegação inferior não deve cobrir a última publicação; reservar padding inferior.

### Estados
- Feed com publicações.
- Feed vazio com convite para explorar conteúdos.
- Carregamento e falha de carregamento.
- Ações indisponíveis devem estar desabilitadas ou explicar o motivo, não parecer funcionais sem backend.

### Dados
No código atual, `feed.php` define publicações em um array PHP e há conteúdo marcado como premium. Tratar isso como dado demonstrativo até que a fonte real seja verificada. Não apresentar a aula do feed como paga: as aulas publicadas no feed devem ser gratuitas. Antes de alterar o modelo, identificar como publicações e aulas são armazenadas e preservar registros existentes.

## 3. Encontrar salas — `salas.php`

### Objetivo
Ajudar estudantes a encontrar grupos de estudo e professores/instituições a gerir as próprias salas.

### Cabeçalho e pesquisa
- Título “Encontre sua próxima sala” e subtítulo breve.
- Barra de busca minimalista com marcador literal `#` nas posições reservadas para futuros ícones.
- Campo de busca por nome, matéria, assunto e palavras-chave.
- Filtros de matéria, modalidade de acesso (pública/privada) e, quando suportado pelos dados, nível de ensino e atividade recente.
- Botão de filtros no mobile que abre painel inferior ou drawer, com resumo dos filtros ativos e ação “Limpar filtros”.

### Lista de resultados
Cada sala deve apresentar:
- Ícone ou ilustração de matéria existente (não inventar imagens ausentes).
- Nome da sala, descrição breve, professor/responsável, matéria e quantidade real de participantes.
- Tags de assunto e badge de acesso.
- Ação “Ver sala” para pública; para privada, “Ver acesso” ou “Solicitar acesso”, de acordo com as regras existentes.
- Se houver custo de entrada, mostrar preço e condições antes da adesão. Não inferir que toda sala privada é paga; privacidade e preço são propriedades distintas.

### Desktop/mobile
- Desktop: lista em cartões horizontais, filtros na linha superior.
- Mobile: cartões empilhados; filtros em painel; ações principais em largura confortável.
- Estados de busca sem resultados, carregamento, erro e ausência de salas.

### Dados e regras
O código já diferencia a interface de estudante, professor e instituição e aponta para rotas de criação/entrada. Preservar essa lógica. Verificar a implementação dos filtros antes de assumir que são funcionais e confirmar no servidor as permissões de entrada em sala privada.

## 4. Biblioteca de conteúdos — `biblioteca.php`

### Objetivo
Organizar materiais educacionais para consulta e estudo, com descoberta por formato e disciplina.

### Estrutura
- Cabeçalho “Biblioteca de conteúdos” e texto explicativo.
- Busca por título, autor, matéria e palavra-chave; marcador `#` nos espaços de ícone que serão definidos posteriormente.
- Filtros por tipo: Todos, Livros, Apostilas, PDFs, Artigos e Videoaulas.
- Filtros adicionais por matéria, nível de ensino e gratuito/pago somente quando houver dados reais para suportá-los.
- Grade de cartões com capa, tipo de material, título, autor, matéria e ação contextual (“Abrir material”, “Ler online” ou “Baixar”).
- Não utilizar capas genéricas como se fossem capas reais de livros. Se não houver imagem, criar capa tipográfica simples com a matéria/título e paleta da marca.
- Permitir alternar para lista em telas pequenas, se a grade ficar apertada.

### Layout
- Desktop: 3 ou 4 colunas, dependendo da largura disponível; conteúdo máximo controlado.
- Tablet: 2 colunas.
- Mobile: uma ou duas colunas conforme largura e legibilidade; priorizar título e ação sem truncamento excessivo.

### Estados e dados
A lista de livros/materiais em `biblioteca.php` atualmente é definida em um array PHP. Tratar os itens como demonstração até confirmar a persistência. Criar estados de biblioteca vazia, sem resultados, carregamento e falha. Não prometer download/leitura se a rota e o arquivo não existirem.

## 5. Perfil público — `perfil-dashboard.php` e rotas relacionadas

### Hierarquia visual
- Logo circular centralizado no topo, sem nome escrito ao lado.
- Perfil em estilo de rede social: foto grande e circular, nome abaixo da foto, `@username`, tipo de perfil e biografia real, se existir.
- Abaixo das informações, indicadores relevantes e atividade recente.
- Para professor/instituição, exibir matérias ou áreas de atuação reais; para estudante, interesses ou matérias, apenas se estiverem cadastrados.
- Ações de “Editar perfil” somente para o proprietário; para outros usuários, ações adequadas como visualizar perfil e, se existir no sistema, seguir/conectar.
- Não exibir botões ou selos “verificado” sem um mecanismo real de verificação.

### Indicadores e gráficos
- Ocultar indicadores cujo valor seja zero, conforme requisito do produto.
- Gráfico de acertos/erros somente quando houver ao menos uma tentativa concluída e dados confiáveis.
- Usar valores reais do banco; nunca preencher o gráfico com dados fictícios para “decorar” a página.
- Se não houver histórico, ocultar o gráfico e priorizar uma mensagem/ação de incentivo para resolver a primeira questão.
- Salas recentes apenas se o usuário realmente participar de salas.

### Atividade
- Abas: Publicações, Materiais e Salas, somente para tipos de conteúdo existentes.
- Cards compactos com título, tipo, matéria e data real.
- Não revelar dados privados de outros usuários nem incluir informações de conta como e-mail na visualização pública, salvo decisão explícita de produto e permissão apropriada.

### Dados
O arquivo de perfil já lê parte dos dados da sessão e usa valores padrão para alguns indicadores. Antes de implementar, verificar quais valores vêm do banco e eliminar defaults fictícios da apresentação. Validar também caminhos de avatar e ícones de disciplina.

## 6. Sistema responsivo

Usar a mesma estrutura PHP/HTML e adaptar a apresentação por CSS, sem duplicar as páginas para desktop e mobile.

Pontos de teste:
- 360 px e 390 px: celular estreito e comum.
- 768 px: tablet.
- 1024 px: laptop/tablet horizontal.
- 1440 px: desktop.

Requisitos:
- Sem rolagem horizontal involuntária.
- Cabeçalho, menus e modais não cobrem conteúdo.
- Cards e inputs encolhem sem overflow.
- Navegação inferior considera safe-area e padding inferior.
- Foco por teclado e estados hover/focus/active consistentes.
- Preferir animações discretas e respeitar `prefers-reduced-motion`.

## 7. Implementação no repositório

Arquivos principais já identificados:
- `feed.php`
- `salas.php`
- `biblioteca.php`
- `perfil-dashboard.php`
- `assets/css/estilo.css`
- `includes/header.php`
- `includes/auth.php`
- `includes/data.php`

Regras de implementação:
1. Ler o arquivo completo e os estilos relacionados antes de editar.
2. Reutilizar o cabeçalho, navegação e tokens existentes; remover estilos inline duplicados quando for seguro.
3. Separar tokens e componentes comuns de regras específicas da página.
4. Manter PHP, HTML, CSS e JavaScript atuais; não migrar para React ou outro framework sem justificativa.
5. Não recriar ou apagar tabelas com dados válidos. Toda alteração de esquema deve ser uma migração incremental.
6. Consultas com PDO e parâmetros preparados; escapar saída HTML; confirmar permissões no servidor.
7. Não declarar funcionalidades operacionais apenas porque há um botão. Ligar cada ação a uma rota existente e testada ou deixá-la claramente desabilitada.
8. Ao final de cada página, testar sintaxe PHP, console, imagens, links, responsividade e estados vazios.

## 8. Critérios de aceite

- [ ] Feed com hierarquia clara e aulas do feed identificadas como gratuitas.
- [ ] Busca de salas com filtros reais e regras distintas para privacidade e cobrança.
- [ ] Biblioteca com filtros, tipos de material e estados vazios/erro.
- [ ] Perfil com foto grande, nome e informações abaixo; sem indicadores fictícios.
- [ ] Gráfico só aparece quando há histórico real.
- [ ] Estatísticas zeradas e salas recentes inexistentes ficam ocultas.
- [ ] Logo circular no centro do topo, sem rótulo textual ao lado.
- [ ] Desktop e mobile compartilham a mesma base e mantêm navegação acessível.
- [ ] Marcadores `#` permanecem nas posições reservadas para ícones futuros da pesquisa.
- [ ] Nenhuma página sugere acesso, pagamento, leitura ou interação que não funcione.
- [ ] Testes e limitações documentados no relatório final.
