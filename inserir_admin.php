<?php
// Script para inserir usuário administrador no banco de dados
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== INSERINDO USUÁRIO ADMINISTRADOR ===\n\n";

try {
    // Inclui a configuração do banco
    require_once 'configurações/configuraçõesdeconexão.php';
    echo "✅ Arquivo de configuração carregado\n";
    
    // Dados do usuário administrador
    $nome_usuario = 'admin';
    $email = 'admin@projeto.com';
    $senha_plain = 'password'; // Senha em texto claro
    $tipo_usuario = 'admin';
    
    // Gera hash da senha usando password_hash()
    $senha_hash = password_hash($senha_plain, PASSWORD_DEFAULT);
    
    echo "Dados do usuário admin:\n";
    echo "- ID: 1\n";
    echo "- Nome: " . $nome_usuario . "\n";
    echo "- Email: " . $email . "\n";
    echo "- Senha: " . $senha_plain . " (hash: " . substr($senha_hash, 0, 20) . "...)\n";
    echo "- Tipo: " . $tipo_usuario . "\n\n";
    
    // Primeiro, verifica se já existe um usuário com ID 1
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE id = 1");
    $stmt_check->execute();
    $existe = $stmt_check->fetch();
    
    if ($existe) {
        echo "🔄 Atualizando usuário existente com ID 1...\n";
        
        // Atualiza o usuário existente
        $stmt_update = $pdo->prepare("
            UPDATE usuarios 
            SET nome_usuario = ?, email = ?, senha = ?, tipo_usuario = ? 
            WHERE id = 1
        ");
        
        $resultado = $stmt_update->execute([
            $nome_usuario,
            $email,
            $senha_hash,
            $tipo_usuario
        ]);
        
        if ($resultado) {
            echo "✅ Usuário administrador atualizado com sucesso!\n";
        } else {
            echo "❌ Erro ao atualizar usuário administrador\n";
        }
    } else {
        echo "➕ Inserindo novo usuário administrador...\n";
        
        // Insere novo usuário
        $stmt_insert = $pdo->prepare("
            INSERT INTO usuarios (id, nome_usuario, email, senha, tipo_usuario) 
            VALUES (1, ?, ?, ?, ?)
        ");
        
        $resultado = $stmt_insert->execute([
            $nome_usuario,
            $email,
            $senha_hash,
            $tipo_usuario
        ]);
        
        if ($resultado) {
            echo "✅ Usuário administrador inserido com sucesso!\n";
        } else {
            echo "❌ Erro ao inserir usuário administrador\n";
        }
    }
    
    // Verifica se o usuário foi criado corretamente
    echo "\n--- VERIFICAÇÃO FINAL ---\n";
    $stmt_verify = $pdo->prepare("SELECT id, nome_usuario, email, tipo_usuario, data_registro FROM usuarios WHERE id = 1");
    $stmt_verify->execute();
    $admin = $stmt_verify->fetch();
    
    if ($admin) {
        echo "✅ Usuário encontrado na base de dados:\n";
        echo "- ID: " . $admin['id'] . "\n";
        echo "- Nome: " . $admin['nome_usuario'] . "\n";
        echo "- Email: " . $admin['email'] . "\n";
        echo "- Tipo: " . $admin['tipo_usuario'] . "\n";
        echo "- Data de registro: " . $admin['data_registro'] . "\n";
    } else {
        echo "❌ Usuário não encontrado na base de dados\n";
    }
    
    // Testa a autenticação da senha
    echo "\n--- TESTE DE AUTENTICAÇÃO ---\n";
    $stmt_password = $pdo->prepare("SELECT senha FROM usuarios WHERE id = 1");
    $stmt_password->execute();
    $senha_db = $stmt_password->fetchColumn();
    
    if (password_verify($senha_plain, $senha_db)) {
        echo "✅ Teste de senha aprovado - autenticação funcionando\n";
    } else {
        echo "❌ Teste de senha falhou - há um problema com o hash da senha\n";
    }
    
    // Exibe todos os usuários para referência
    echo "\n--- TODOS OS USUÁRIOS ---\n";
    $stmt_all = $pdo->query("SELECT id, nome_usuario, email, tipo_usuario FROM usuarios");
    $usuarios = $stmt_all->fetchAll();
    
    if (empty($usuarios)) {
        echo "Nenhum usuário encontrado na base de dados\n";
    } else {
        foreach ($usuarios as $usuario) {
            echo "- ID: " . $usuario['id'] . " | Nome: " . $usuario['nome_usuario'] . " | Email: " . $usuario['email'] . " | Tipo: " . $usuario['tipo_usuario'] . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro de banco de dados: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
}

echo "\n=== FIM DO PROCESSO ===\n";
echo "\n📝 CREDENCIAIS DE ACESSO:\n";
echo "URL: http://seu-projeto/login.php\n";
echo "Usuário: admin\n";
echo "Senha: password\n";
?>