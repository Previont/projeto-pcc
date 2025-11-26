CREATE DATABASE `cadastro_teste` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `cadastro_teste`;

CREATE TABLE `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome_usuario` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `tipo_usuario` ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `data_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `campanhas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `titulo` VARCHAR(100) NOT NULL,
  `descricao` TEXT NOT NULL,
  `url_imagem` VARCHAR(255) DEFAULT 'https://via.placeholder.com/300',
  `meta_arrecadacao` DECIMAL(10, 2) NOT NULL,
  `valor_arrecadado` DECIMAL(10, 2) DEFAULT 0.00,
  `visualizacoes` INT NOT NULL DEFAULT 0,
  `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_campanha_usuario_titulo` (`id_usuario`, `titulo`),
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `enderecos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `cep` VARCHAR(10) NOT NULL,
  `logradouro` VARCHAR(255) NOT NULL,
  `numero` VARCHAR(10) NOT NULL,
  `complemento` VARCHAR(100) DEFAULT NULL,
  `bairro` VARCHAR(100) NOT NULL,
  `cidade` VARCHAR(100) NOT NULL,
  `estado` VARCHAR(2) NOT NULL,
  `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `metodos_pagamento` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `nome_titular` VARCHAR(255) NOT NULL,
  `ultimos_digitos` VARCHAR(4) NOT NULL,
  `data_validade` VARCHAR(5) NOT NULL,
  `cartao_hash` VARCHAR(255) NOT NULL,
  `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `itens_campanha` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_campanha` INT NOT NULL,
  `nome_item` VARCHAR(100) NOT NULL,
  `descricao_item` TEXT NOT NULL,
  `valor_fixo` DECIMAL(10, 2) NOT NULL,
  `url_imagem` VARCHAR(255) DEFAULT NULL,
  `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_campanha`) REFERENCES `campanhas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

UPDATE usuarios SET tipo_usuario = 'admin' WHERE nome_usuario = 'admin';