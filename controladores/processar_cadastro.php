<?php
session_start();

// Inclui o arquivo de configuração da conexão com o banco de dados.
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// Verifica se o método da requisição é POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove espaços em branco e obtém os dados do formulário.
    $nome_usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmacao_senha = $_POST['confirma_senha'] ?? '';

    // Validações dos dados de entrada.
    if (empty($nome_usuario) || empty($email) || empty($senha) || empty($confirmacao_senha)) {
        $_SESSION['erro_cadastro'] = "Todos os campos são obrigatórios.";
    } elseif ($senha !== $confirmacao_senha) {
        $_SESSION['erro_cadastro'] = "As senhas não coincidem.";
    } elseif (strlen($senha) < 6) {
        $_SESSION['erro_cadastro'] = "A senha deve ter no mínimo 6 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['erro_cadastro'] = "O formato do e-mail é inválido.";
    } elseif (strlen($nome_usuario) < 3) {
        $_SESSION['erro_cadastro'] = "O nome de usuário deve ter no mínimo 3 caracteres.";
    } else {
        try {
            // Prepara e executa a consulta para verificar se o usuário ou e-mail já existem.
            $consulta_usuario = $pdo->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? OR email = ?");
            $consulta_usuario->execute([$nome_usuario, $email]);
            $usuario_existente = $consulta_usuario->fetch();

            if ($usuario_existente) {
                // Se o usuário já existe, define uma mensagem de erro e um indicador na sessão.
                $_SESSION['erro_cadastro'] = "Este nome de usuário ou e-mail já está cadastrado no sistema.";
                $_SESSION['usuario_ja_existe'] = true;
            } else {
                // Gera o hash da senha para armazenamento seguro.
                $hash_senha = password_hash($senha, PASSWORD_DEFAULT);

                // Prepara e executa a inserção do novo usuário no banco de dados.
                $inserir_usuario = $pdo->prepare("INSERT INTO usuarios (nome_usuario, email, senha) VALUES (?, ?, ?)");
                $inserir_usuario->execute([$nome_usuario, $email, $hash_senha]);

                // Define uma mensagem de sucesso e redireciona para a página de login.
                $_SESSION['sucesso_cadastro'] = "Seu cadastro foi realizado com sucesso! Faça o login para continuar.";
                header('Location: ../visualizadores/login.php');
                exit();
            }
        } catch (PDOException $e) {
            // Em caso de erro de banco de dados, armazena a mensagem de erro.
            $_SESSION['erro_cadastro'] = "Erro ao processar o cadastro. Tente novamente. Detalhe: " . $e->getMessage();
        }
    }

    // Se houver algum erro de validação, redireciona de volta para a página de cadastro.
    header('Location: ../visualizadores/cadastro.php');
    exit();
} else {
    // Se o acesso não for via POST, redireciona para a página de cadastro.
    header('Location: ../visualizadores/cadastro.php');
    exit();
}
?>