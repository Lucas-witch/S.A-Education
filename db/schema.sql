-- Schema for S.A Education

CREATE TABLE IF NOT EXISTS planos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(30) NOT NULL UNIQUE,
  preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  descricao TEXT DEFAULT NULL,
  pode_postar_aulas_ilimitadas BOOLEAN NOT NULL DEFAULT FALSE,
  pode_criar_salas BOOLEAN NOT NULL DEFAULT FALSE,
  pode_criar_comunidades_privadas BOOLEAN NOT NULL DEFAULT FALSE,
  pode_postar_artigos BOOLEAN NOT NULL DEFAULT TRUE,
  limite_video_aula_free INT NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO planos (id, nome, preco, descricao, pode_postar_aulas_ilimitadas, pode_criar_salas, pode_criar_comunidades_privadas, pode_postar_artigos, limite_video_aula_free)
VALUES
  (1, 'free', 0.00, 'Conta gratuita para leitura e publicações limitadas.', FALSE, FALSE, FALSE, TRUE, 1),
  (2, 'premium', 29.90, 'Conta premium com publicação ilimitada, salas e comunidades privadas.', TRUE, TRUE, TRUE, TRUE, NULL)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  preco = VALUES(preco),
  descricao = VALUES(descricao),
  pode_postar_aulas_ilimitadas = VALUES(pode_postar_aulas_ilimitadas),
  pode_criar_salas = VALUES(pode_criar_salas),
  pode_criar_comunidades_privadas = VALUES(pode_criar_comunidades_privadas),
  pode_postar_artigos = VALUES(pode_postar_artigos),
  limite_video_aula_free = VALUES(limite_video_aula_free);

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_completo VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  username VARCHAR(50) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  perfil ENUM('estudante','professor','instituicao') NOT NULL DEFAULT 'estudante',
  plano_id INT NOT NULL DEFAULT 1,
  perfil_imagem VARCHAR(255) DEFAULT NULL,
  oauth_provider VARCHAR(50) DEFAULT NULL,
  oauth_id VARCHAR(255) DEFAULT NULL,
  premium_ativo BOOLEAN NOT NULL DEFAULT FALSE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_plano
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS instituicoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  nome VARCHAR(180) NOT NULL,
  tipo ENUM('fundamental','medio','faculdade','universidade','outro') NOT NULL,
  natureza ENUM('publica','particular') NOT NULL,
  responsavel VARCHAR(150) NOT NULL,
  telefone VARCHAR(30) DEFAULT NULL,
  cidade VARCHAR(120) NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_instituicoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS biblioteca (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  autor VARCHAR(150) DEFAULT NULL,
  tipo ENUM('livro','pdf','apostila','artigo','material') NOT NULL DEFAULT 'livro',
  descricao TEXT DEFAULT NULL,
  capa_url VARCHAR(255) DEFAULT NULL,
  arquivo_url VARCHAR(255) DEFAULT NULL,
  disponibilidade ENUM('emprestimo','gratis','leitura_online') NOT NULL DEFAULT 'gratis',
  status ENUM('ativo','indisponivel','em_revisao') NOT NULL DEFAULT 'ativo',
  instituicao_id INT DEFAULT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_biblioteca_instituicao
    FOREIGN KEY (instituicao_id) REFERENCES instituicoes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emprestimos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  livro_id INT NOT NULL,
  usuario_id INT NOT NULL,
  status ENUM('ativo','devolvido','atrasado') NOT NULL DEFAULT 'ativo',
  data_emprestimo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_devolucao TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_emprestimos_livro
    FOREIGN KEY (livro_id) REFERENCES biblioteca(id) ON DELETE CASCADE,
  CONSTRAINT fk_emprestimos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  prof VARCHAR(120) DEFAULT NULL,
  alunos INT DEFAULT 0,
  materia VARCHAR(80) DEFAULT NULL,
  icone VARCHAR(255) DEFAULT NULL,
  codigo VARCHAR(16) DEFAULT NULL,
  privacidade ENUM('publica','privada') DEFAULT 'publica',
  descricao TEXT DEFAULT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS publicacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  instituicao_id INT DEFAULT NULL,
  sala_id INT DEFAULT NULL,
  tipo ENUM('video_aula','artigo','redacao','pdf','livro','post','material') NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  resumo TEXT DEFAULT NULL,
  conteudo LONGTEXT DEFAULT NULL,
  capa_url VARCHAR(255) DEFAULT NULL,
  arquivo_url VARCHAR(255) DEFAULT NULL,
  video_url VARCHAR(255) DEFAULT NULL,
  categoria VARCHAR(80) DEFAULT NULL,
  visibilidade ENUM('publica','privada','sala','instituicao') NOT NULL DEFAULT 'publica',
  status ENUM('rascunho','publicado','arquivado') NOT NULL DEFAULT 'publicado',
  is_premium_only BOOLEAN NOT NULL DEFAULT FALSE,
  publicado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_publicacoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_publicacoes_instituicao
    FOREIGN KEY (instituicao_id) REFERENCES instituicoes(id) ON DELETE SET NULL,
  CONSTRAINT fk_publicacoes_sala
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ranking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  pontos INT DEFAULT 0,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Observação de UI:
-- O rodapé para mobile deve conter: Feed, Biblioteca, Início, Exercícios.
-- Em desktop, esse conjunto pode ficar oculto no menu principal para preservar a navegação tradicional.

