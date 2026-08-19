CREATE DATABASE IF NOT EXISTS sa_education
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sa_education;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    username VARCHAR(40) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('estudante', 'professor') NOT NULL DEFAULT 'estudante',
    interesses TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
