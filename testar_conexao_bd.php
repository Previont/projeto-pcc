<?php
// Script de teste para verificar a conexão com o banco de dados
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE DE CONEXÃO COM BANCO DE DADOS ===\n\n";

// Inclui o arquivo de configuração
try {
    require_once 'configurações/configuraçõesdeconexão.php';
    echo "✅ Arquivo de configuração carregado com sucesso\n";
} catch (Exception $e) {
    echo "❌ Erro ao carregar configuração: " . $e->getMessage() . "\n";
    exit;
}

// Testa a conexão
try {
    echo "Testando conexão PDO...\n";
    echo "Servidor: " . $servidor . "\n";
    echo "Banco: " . $banco_de_dados . "\n";
    echo "Usuário: " . $usuario_bd . "\n\n";
    
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✅ Conexão PDO estabelecida com sucesso\n";
        
        // Testa se o banco de dados existe
        $stmt = $pdo->query("SHOW DATABASES");
        $bancos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Bancos de dados disponíveis:\n";
        foreach ($bancos as $banco) {
            echo "- " . $banco . "\n";
        }
        
        // Verifica se o banco cadastro_teste existe
        if (in_array('cadastro_teste', $bancos)) {
            echo "✅ Banco 'cadastro_teste' existe\n";
            
            // Seleciona o banco
            $pdo->exec("USE cadastro_teste");
            
            // Verifica as tabelas
            $stmt = $pdo->query("SHOW TABLES");
            $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "Tabelas no banco cadastro_teste:\n";
            foreach ($tabelas as $tabela) {
                echo "- " . $tabela . "\n";
            }
            
            // Verifica a estrutura da tabela campanhas
            if (in_array('campanhas', $tabelas)) {
                echo "✅ Tabela 'campanhas' existe\n";
                
                $stmt = $pdo->query("DESCRIBE campanhas");
                $colunas = $stmt->fetchAll();
                
                echo "Colunas da tabela campanhas:\n";
                foreach ($colunas as $coluna) {
                    echo "- " . $coluna['Field'] . " (" . $coluna['Type'] . ")\n";
                }
                
            } else {
                echo "❌ Tabela 'campanhas' não existe\n";
            }
            
            // Verifica a estrutura da tabela usuarios
            if (in_array('usuarios', $tabelas)) {
                echo "✅ Tabela 'usuarios' existe\n";
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
                $count = $stmt->fetchColumn();
                echo "Número de usuários: " . $count . "\n";
                
            } else {
                echo "❌ Tabela 'usuarios' não existe\n";
            }
            
        } else {
            echo "❌ Banco 'cadastro_teste' não existe\n";
        }
        
    } else {
        echo "❌ Falha na conexão PDO\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão PDO: " . $e->getMessage() . "\n";
    echo "Código do erro: " . $e->getCode() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>