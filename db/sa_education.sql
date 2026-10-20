-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/10/2026 às 21:48
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sa_education`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `biblioteca`
--

CREATE TABLE `biblioteca` (
  `id` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `autor` varchar(150) DEFAULT NULL,
  `tipo` enum('livro','pdf','apostila','artigo','material') NOT NULL DEFAULT 'livro',
  `materia` varchar(80) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `capa_url` varchar(255) DEFAULT NULL,
  `arquivo_url` varchar(255) DEFAULT NULL,
  `disponibilidade` enum('emprestimo','gratis','leitura_online') NOT NULL DEFAULT 'gratis',
  `status` enum('ativo','indisponivel','em_revisao') NOT NULL DEFAULT 'ativo',
  `instituicao_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `emprestimos`
--

CREATE TABLE `emprestimos` (
  `id` int(11) NOT NULL,
  `livro_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status` enum('ativo','devolvido','atrasado') NOT NULL DEFAULT 'ativo',
  `data_emprestimo` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_devolucao` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `instituicoes`
--

CREATE TABLE `instituicoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nome` varchar(180) NOT NULL,
  `tipo` enum('fundamental','medio','faculdade','universidade','outro') NOT NULL,
  `natureza` enum('publica','particular') NOT NULL,
  `responsavel` varchar(150) NOT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `cidade` varchar(120) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos`
--

CREATE TABLE `planos` (
  `id` int(11) NOT NULL,
  `nome` varchar(30) NOT NULL,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descricao` text DEFAULT NULL,
  `pode_postar_aulas_ilimitadas` tinyint(1) NOT NULL DEFAULT 0,
  `pode_criar_salas` tinyint(1) NOT NULL DEFAULT 0,
  `pode_criar_comunidades_privadas` tinyint(1) NOT NULL DEFAULT 0,
  `pode_postar_artigos` tinyint(1) NOT NULL DEFAULT 1,
  `limite_video_aula_free` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `planos`
--

INSERT INTO `planos` (`id`, `nome`, `preco`, `descricao`, `pode_postar_aulas_ilimitadas`, `pode_criar_salas`, `pode_criar_comunidades_privadas`, `pode_postar_artigos`, `limite_video_aula_free`, `criado_em`) VALUES
(1, 'free', 0.00, 'Conta gratuita para leitura e publicações limitadas.', 0, 0, 0, 1, 1, '2026-09-28 19:16:52'),
(2, 'premium', 29.90, 'Conta premium com publicação ilimitada, salas e comunidades privadas.', 1, 1, 1, 1, NULL, '2026-09-28 19:16:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `publicacoes`
--

CREATE TABLE `publicacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `instituicao_id` int(11) DEFAULT NULL,
  `sala_id` int(11) DEFAULT NULL,
  `tipo` enum('video_aula','artigo','redacao','pdf','livro','post','material') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `resumo` text DEFAULT NULL,
  `conteudo` longtext DEFAULT NULL,
  `capa_url` varchar(255) DEFAULT NULL,
  `arquivo_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `visibilidade` enum('publica','privada','sala','instituicao') NOT NULL DEFAULT 'publica',
  `status` enum('rascunho','publicado','arquivado') NOT NULL DEFAULT 'publicado',
  `is_premium_only` tinyint(1) NOT NULL DEFAULT 0,
  `publicado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `ranking`
--

CREATE TABLE `ranking` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `pontos` int(11) DEFAULT 0,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `salas`
--

CREATE TABLE `salas` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `prof` varchar(120) DEFAULT NULL,
  `alunos` int(11) DEFAULT 0,
  `materia` varchar(80) DEFAULT NULL,
  `icone` varchar(255) DEFAULT NULL,
  `codigo` varchar(16) DEFAULT NULL,
  `privacidade` enum('publica','privada') DEFAULT 'publica',
  `descricao` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--
-- Estrutura para tabela `salas_usuarios`
--

CREATE TABLE `salas_usuarios` (
  `id` int(11) NOT NULL,
  `sala_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `papel` enum('aluno','professor','moderador') NOT NULL DEFAULT 'aluno',
  `status` enum('ativo','removido') NOT NULL DEFAULT 'ativo',
  `entrou_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- --------------------------------------------------------

--
-- Estrutura para tabela `tentativas_atividade`
--

CREATE TABLE `tentativas_atividade` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('exercicio','quiz') NOT NULL,
  `atividade_id` int(11) DEFAULT NULL,
  `categoria` varchar(80) DEFAULT 'geral',
  `respostas_corretas` int(11) NOT NULL DEFAULT 0,
  `respostas_incorretas` int(11) NOT NULL DEFAULT 0,
  `total_respostas` int(11) NOT NULL DEFAULT 0,
  `percentual_acerto` decimal(5,2) NOT NULL DEFAULT 0.00,
  `concluido` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome_completo` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('estudante','professor','instituicao') NOT NULL DEFAULT 'estudante',
  `plano_id` int(11) NOT NULL DEFAULT 1,
  `perfil_imagem` varchar(255) DEFAULT 'assets/images/icons/user-icon.png',
  `pontos` int(11) NOT NULL DEFAULT 0,
  `moedas` int(11) NOT NULL DEFAULT 0,
  `seguidores` int(11) NOT NULL DEFAULT 0,
  `grupos` int(11) NOT NULL DEFAULT 0,
  `horas_aulas` int(11) NOT NULL DEFAULT 0,
  `livros_lidos` int(11) NOT NULL DEFAULT 0,
  `quizzes_pontos` int(11) NOT NULL DEFAULT 0,
  `atividades_pontos` int(11) NOT NULL DEFAULT 0,
  `artigos_pontuacao` int(11) DEFAULT NULL,
  `escritos_pontuacao` int(11) DEFAULT NULL,
  `ensaios_pontuacao` int(11) DEFAULT NULL,
  `redacoes_pontuacao` int(11) DEFAULT NULL,
  `acertos_timeline` json DEFAULT NULL,
  `erros_timeline` json DEFAULT NULL,
  `oauth_provider` varchar(50) DEFAULT NULL,
  `oauth_id` varchar(255) DEFAULT NULL,
  `premium_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices de tabela `salas_usuarios`
--
ALTER TABLE `salas_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_salas_usuarios_sala` (`sala_id`),
  ADD KEY `fk_salas_usuarios_usuario` (`usuario_id`),
  ADD UNIQUE KEY `uniq_sala_usuario` (`sala_id`, `usuario_id`, `status`);

--
-- Índices de tabela `tentativas_atividade`
--
ALTER TABLE `tentativas_atividade`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tentativas_usuario` (`usuario_id`),
  ADD KEY `idx_tentativas_periodo` (`usuario_id`, `concluido`, `criado_em`);

--
-- Índices de tabela `biblioteca`
--
ALTER TABLE `biblioteca`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_biblioteca_instituicao` (`instituicao_id`);

--
-- Índices de tabela `emprestimos`
--
ALTER TABLE `emprestimos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_emprestimos_livro` (`livro_id`),
  ADD KEY `fk_emprestimos_usuario` (`usuario_id`);

--
-- Índices de tabela `instituicoes`
--
ALTER TABLE `instituicoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `publicacoes`
--
ALTER TABLE `publicacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_publicacoes_usuario` (`usuario_id`),
  ADD KEY `fk_publicacoes_instituicao` (`instituicao_id`),
  ADD KEY `fk_publicacoes_sala` (`sala_id`);

--
-- Índices de tabela `ranking`
--
ALTER TABLE `ranking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ranking_usuario` (`usuario_id`);

--
-- Índices de tabela `salas`
--
ALTER TABLE `salas`
  ADD PRIMARY KEY (`id`);

-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_usuarios_plano` (`plano_id`);

-- Interações persistentes do Feed; publicacoes/usuarios já têm chaves primárias.

CREATE TABLE `publicacao_curtidas` (
  `publicacao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`publicacao_id`, `usuario_id`),
  KEY `idx_publicacao_curtidas_usuario` (`usuario_id`),
  CONSTRAINT `fk_publicacao_curtidas_publicacao` FOREIGN KEY (`publicacao_id`) REFERENCES `publicacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_publicacao_curtidas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `publicacao_salvamentos` (
  `publicacao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`publicacao_id`, `usuario_id`),
  KEY `idx_publicacao_salvamentos_usuario` (`usuario_id`),
  CONSTRAINT `fk_publicacao_salvamentos_publicacao` FOREIGN KEY (`publicacao_id`) REFERENCES `publicacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_publicacao_salvamentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `publicacao_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `publicacao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `conteudo` varchar(2000) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_publicacao_comentarios_feed` (`publicacao_id`, `criado_em`, `id`),
  KEY `idx_publicacao_comentarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_publicacao_comentarios_publicacao` FOREIGN KEY (`publicacao_id`) REFERENCES `publicacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_publicacao_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `biblioteca`
--
ALTER TABLE `biblioteca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `emprestimos`
--
ALTER TABLE `emprestimos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `instituicoes`
--
ALTER TABLE `instituicoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `publicacoes`
--
ALTER TABLE `publicacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ranking`
--
ALTER TABLE `ranking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `salas`
--
ALTER TABLE `salas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `salas_usuarios`
--
ALTER TABLE `salas_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tentativas_atividade`
--
ALTER TABLE `tentativas_atividade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `biblioteca`
--
ALTER TABLE `biblioteca`
  ADD CONSTRAINT `fk_biblioteca_instituicao` FOREIGN KEY (`instituicao_id`) REFERENCES `instituicoes` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `emprestimos`
--
ALTER TABLE `emprestimos`
  ADD CONSTRAINT `fk_emprestimos_livro` FOREIGN KEY (`livro_id`) REFERENCES `biblioteca` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_emprestimos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `instituicoes`
--
ALTER TABLE `instituicoes`
  ADD CONSTRAINT `fk_instituicoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `publicacoes`
--
ALTER TABLE `publicacoes`
  ADD CONSTRAINT `fk_publicacoes_instituicao` FOREIGN KEY (`instituicao_id`) REFERENCES `instituicoes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_publicacoes_sala` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_publicacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `ranking`
--
ALTER TABLE `ranking`
  ADD CONSTRAINT `fk_ranking_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `salas_usuarios`
--
ALTER TABLE `salas_usuarios`
  ADD CONSTRAINT `fk_salas_usuarios_sala` FOREIGN KEY (`sala_id`) REFERENCES `salas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_salas_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tentativas_atividade`
--
ALTER TABLE `tentativas_atividade`
  ADD CONSTRAINT `fk_tentativas_atividade_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
