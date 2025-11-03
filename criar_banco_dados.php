<?php
// Script para criar o banco de dados se não existir
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CRIANDO BANCO DE DADOS E ESTRUTURA ===\n\n";

// Tentativa de conexão sem especificar o banco primeiro
try {
    $servidor = 'localhost';
    $usuario_bd = 'root';
    $senha_bd = '';
    
    // Conecta sem especificar banco para criar se necessário
    $pdo_temp = new PDO("mysql:host=$servidor;charset=utf8mb4", $usuario_bd, $senha_bd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Conexão estabelecida sem banco específico\n";
    
    // Cria o banco se não existir
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `cadastro_teste` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Banco 'cadastro_teste' criado/verificado\n";
    
    // Conecta ao banco específico
    $pdo = new PDO("mysql:host=$servidor;dbname=cadastro_teste;charset=utf8mb4", $usuario_bd, $senha_bd, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Conectado ao banco 'cadastro_teste'\n\n";
    
    // Lista de comandos SQL para criar as tabelas
    $sql_commands = [
        // Tabela usuarios
        "CREATE TABLE IF NOT EXISTS `usuarios` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `nome_usuario` VARCHAR(50) NOT NULL UNIQUE,
          `email` VARCHAR(100) NOT NULL UNIQUE,
          `senha` VARCHAR(255) NOT NULL,
          `tipo_usuario` ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
          `data_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",
        
        // Tabela campanhas
        "CREATE TABLE IF NOT EXISTS `campanhas` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `id_usuario` INT NOT NULL,
          `titulo` VARCHAR(100) NOT NULL,
          `descricao` TEXT NOT NULL,
          `url_imagem` VARCHAR(255) DEFAULT 'https://via.placeholder.com/300',
          `meta_arrecadacao` DECIMAL(10, 2) NOT NULL,
          `valor_arrecadado` DECIMAL(10, 2) DEFAULT 0.00,
          `visualizacoes` INT NOT NULL DEFAULT 0,
          `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;",
        
        // Tabela itens_campanha
        "CREATE TABLE IF NOT EXISTS `itens_campanha` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `id_campanha` INT NOT NULL,
          `nome_item` VARCHAR(100) NOT NULL,
          `descricao_item` TEXT NOT NULL,
          `valor_fixo` DECIMAL(10, 2) NOT NULL,
          `url_imagem` VARCHAR(255) DEFAULT NULL,
          `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`id_campanha`) REFERENCES `campanhas`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;",
        
        // Tabela enderecos
        "CREATE TABLE IF NOT EXISTS `enderecos` (
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
        ) ENGINE=InnoDB;",
        
        // Tabela metodos_pagamento
        "CREATE TABLE IF NOT EXISTS `metodos_pagamento` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `id_usuario` INT NOT NULL,
          `nome_titular` VARCHAR(255) NOT NULL,
          `ultimos_digitos` VARCHAR(4) NOT NULL,
          `data_validade` VARCHAR(5) NOT NULL,
          `cartao_hash` VARCHAR(255) NOT NULL,
          `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;"
    ];
    
    // Executa cada comando SQL
    foreach ($sql_commands as $index => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Comando SQL " . ($index + 1) . " executado com sucesso\n";
        } catch (PDOException $e) {
            echo "❌ Erro no comando SQL " . ($index + 1) . ": " . $e->getMessage() . "\n";
        }
    }
    
    // Verifica as tabelas criadas
    echo "\n--- TABELAS CRIADAS ---\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tabelas as $tabela) {
        echo "- " . $tabela . "\n";
    }
    
    // Verifica se há usuários (para testar)
    echo "\n--- VERIFICAÇÃO FINAL ---\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total_usuarios = $stmt->fetchColumn();
    echo "Total de usuários: " . $total_usuarios . "\n";
    
    if ($total_usuarios == 0) {
        echo "ℹ️  Nenhum usuário encontrado. Você pode precisar criar um usuário primeiro para testar a criação de campanhas.\n";
    }
    
    // Testa uma consulta simples na tabela campanhas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM campanhas");
    $total_campanhas = $stmt->fetchColumn();
    echo "Total de campanhas: " . $total_campanhas . "\n";
    
    echo "\n✅ Banco de dados configurado com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
}

echo "\n=== FIM DA CONFIGURAÇÃO ===\n";
?>