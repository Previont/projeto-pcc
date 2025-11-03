<?php
session_start();
// Inclui o arquivo de configuração da conexão com o banco de dados.
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Protege o script: verifica se o usuário está logado e se o método da requisição é POST.
if (!isset($_SESSION['id_usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../visualizadores/login.php");
    exit;
}

// Obtém os dados do formulário e remove espaços em branco.
$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = trim($_POST['nome_usuario']);
$email_usuario = trim($_POST['email_usuario']);
$nova_senha = $_POST['nova_senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';

// 1. Validação básica dos campos obrigatórios.
if (empty($nome_usuario) || empty($email_usuario)) {
    $_SESSION['mensagem_erro'] = "O nome de usuário e o e-mail são obrigatórios.";
    header("Location: ../visualizadores/alterar-cadastro.php");
    exit;
}

// Validação do formato de e-mail
if (!filter_var($email_usuario, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['mensagem_erro'] = "Por favor, forneça um endereço de e-mail válido.";
    header("Location: ../visualizadores/alterar-cadastro.php");
    exit;
}

try {
    // 2. Verifica se o novo nome de usuário ou e-mail já estão em uso por OUTRO usuário.
    $consulta = $pdo->prepare("SELECT id FROM usuarios WHERE (nome_usuario = :nome_usuario OR email = :email) AND id != :id");
    $consulta->execute([':nome_usuario' => $nome_usuario, ':email' => $email_usuario, ':id' => $id_usuario]);
    
    if ($consulta->fetch()) {
        $_SESSION['mensagem_erro'] = "O nome de usuário ou o e-mail já está em uso por outra conta.";
        header("Location: ../visualizadores/alterar-cadastro.php");
        exit;
    }

    // 3. Prepara a consulta de atualização.
    $partes_sql = ["nome_usuario = :nome_usuario", "email = :email"];
    $parametros = [':nome_usuario' => $nome_usuario, ':email' => $email_usuario, ':id' => $id_usuario];

    // 4. Se uma nova senha foi fornecida, valida e a adiciona à consulta.
    if (!empty($nova_senha)) {
        if ($nova_senha !== $confirmar_senha) {
            $_SESSION['mensagem_erro'] = "As novas senhas não coincidem.";
            header("Location: ../visualizadores/alterar-cadastro.php");
            exit;
        }
        // Adiciona a senha criptografada à consulta.
        $partes_sql[] = "senha = :senha";
        $parametros[':senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
    }

    // 5. Executa a atualização no banco de dados.
    $sql = "UPDATE usuarios SET " . implode(", ", $partes_sql) . " WHERE id = :id";
    $declaracao = $pdo->prepare($sql);
    $declaracao->execute($parametros);

    // Atualiza o nome de usuário na sessão, caso tenha sido alterado.
    $_SESSION['nome_usuario'] = $nome_usuario;

    $_SESSION['mensagem_sucesso'] = "Cadastro atualizado com sucesso!";
    header("Location: ../visualizadores/alterar-cadastro.php");
    exit;

} catch (PDOException $e) {
    // Em caso de erro no banco de dados, armazena a mensagem e redireciona.
    $_SESSION['mensagem_erro'] = "Erro no banco de dados: " . $e->getMessage();
    header("Location: ../visualizadores/alterar-cadastro.php");
    exit;
}
// Este script sempre redireciona antes de chegar aqui, então não há necessidade de HTML abaixo
?>