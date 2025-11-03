<?php
// Definições para a conexão com o banco de dados
$servidor = 'localhost';
$banco_de_dados = 'cadastro_teste'; 
$usuario_bd = 'root';
$senha_bd = '';
$conjunto_de_caracteres = 'utf8mb4';

// DSN (Data Source Name) para a conexão PDO
$dsn = "mysql:host=$servidor;dbname=$banco_de_dados;charset=$conjunto_de_caracteres";

// Opções de configuração para a conexão PDO
$opcoes_pdo = [
    // Define o modo de relatório de erros para exceções.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Define o modo de busca padrão para arrays associativos.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Desativa a emulação de prepared statements para usar a funcionalidade nativa do driver.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Tenta estabelecer a conexão com o banco de dados usando as configurações definidas.
    $pdo = new PDO($dsn, $usuario_bd, $senha_bd, $opcoes_pdo);
} catch (\PDOException $excecao) {
    // Em caso de falha na conexão, lança uma nova exceção para interromper o script.
    // É uma boa prática registrar o erro em um arquivo de log em um ambiente de produção.
    // error_log('Erro de conexão com o banco de dados: ' . $excecao->getMessage());
    throw new \PDOException($excecao->getMessage(), (int)$excecao->getCode());
}
?>