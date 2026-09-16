-- Execute uma vez em bancos criados antes do cadastro institucional.
ALTER TABLE usuarios
  MODIFY perfil ENUM('estudante','professor','instituicao') NOT NULL DEFAULT 'estudante';

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
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
