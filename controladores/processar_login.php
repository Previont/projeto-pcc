<?php
session_start();
// Inclui o arquivo de configuração da conexão com o banco de dados.
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// Verifica se o método da requisição é POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém o identificador de login (pode ser nome de usuário ou e-mail) e a senha.
    $identificador_login = trim($_POST['usuario'] ?? '');
    $senha_login = $_POST['senha'] ?? '';

    // Valida se os campos não estão vazios.
    if (empty($identificador_login) || empty($senha_login)) {
        $_SESSION['erro'] = 'Por favor, preencha todos os campos.';
        header("Location: ../visualizadores/login.php");
        exit;
    }

    try {
        // Debug: Verificar conexão
        if (!$pdo) {
            throw new Exception('Conexão com banco de dados falhou');
        }

        // Prepara a consulta para encontrar o usuário pelo nome de usuário ou e-mail.
        $consulta = $pdo->prepare("SELECT id, nome_usuario, email, senha, tipo_usuario FROM usuarios WHERE nome_usuario = :usuario OR email = :email");

        // Executa a consulta com o identificador fornecido.
        $resultado = $consulta->execute([
            ':usuario' => $identificador_login,
            ':email' => $identificador_login
        ]);

        if (!$resultado) {
            throw new Exception('Falha na execução da query');
        }

        $usuario = $consulta->fetch();

        if ($usuario) {
            // Se o usuário for encontrado, verifica se a senha está correta.
            if (password_verify($senha_login, $usuario['senha'])) {
                // Se a senha estiver correta, armazena os dados do usuário na sessão.
                $_SESSION['id_usuario'] = $usuario['id'];
                $_SESSION['nome_usuario'] = $usuario['nome_usuario'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario']; // 'admin' ou 'usuario'

                // Redireciona para a página inicial do sistema.
                header("Location: ../visualizadores/paginainicial.php");
                exit;
            } else {
                // Se a senha for inválida, define uma mensagem de erro.
                $_SESSION['erro'] = 'Senha inválida. Por favor, tente novamente.';
                header("Location: ../visualizadores/login.php");
                exit;
            }
        } else {
            // Se o usuário não for encontrado, define uma mensagem de erro.
            $_SESSION['erro'] = 'Usuário não encontrado. Verifique o nome de usuário ou e-mail inserido.';
            header("Location: ../visualizadores/login.php");
            exit;
        }
    } catch (PDOException $e) {
        // Em caso de erro de banco de dados, armazena a mensagem e redireciona.
        $_SESSION['erro'] = 'Erro de banco de dados: ' . $e->getMessage();
        error_log('Erro de login: ' . $e->getMessage()); // Para registro de erros
        header("Location: ../visualizadores/login.php");
        exit;
    } catch (Exception $e) {
        // Em caso de outros erros, armazena a mensagem e redireciona.
        $_SESSION['erro'] = 'Erro no sistema: ' . $e->getMessage();
        error_log('Erro geral de login: ' . $e->getMessage()); // Para registro de erros
        header("Location: ../visualizadores/login.php");
        exit;
    }
} else {
    // Se o acesso não for via POST, redireciona para a página de login.
    header("Location: ../visualizadores/login.php");
    exit;
}
?>