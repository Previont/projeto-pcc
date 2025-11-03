-- Script para inserir usuário administrador
-- Execute este script no banco de dados 'cadastro_teste'

USE cadastro_teste;

-- Insere usuário administrador
INSERT INTO usuarios (id, nome_usuario, email, senha, tipo_usuario) 
VALUES (
    1,
    'admin',
    'admin@projeto.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Senha: password
    'admin'
) 
ON DUPLICATE KEY UPDATE 
    nome_usuario = VALUES(nome_usuario),
    email = VALUES(email),
    senha = VALUES(senha),
    tipo_usuario = VALUES(tipo_usuario);

-- Verifica se o usuário foi inserido
SELECT id, nome_usuario, email, tipo_usuario, data_registro 
FROM usuarios 
WHERE id = 1;

-- Dados do usuário admin criado:
-- ID: 1
-- Nome: admin
-- Email: admin@projeto.com  
-- Senha: password (hash)
-- Tipo: admin