<?php
session_start();

/*
 Objetivo: processar o login do usuário com segurança e clareza.
 
 Termos:
 - "Sessão": uma memória temporária no servidor que guarda quem é você entre páginas.
 - "PDO": biblioteca do PHP para conversar com o banco de dados de forma segura.
 - "Hash de senha": versão embaralhada da senha; não é possível recuperar a senha original.

 Diagrama mental:
 [Formulário] -> [Validação] -> [Consulta ao banco] -> [Verificação de senha] -> [Sessão preenchida] -> [Redirecionar]

 Erros comuns:
 - Enviar formulário sem preencher campos: evitado com validação logo no início.
 - Tentar SQL sem conexão válida: evitado com assertiva de PDO.
 - Tratar senha em texto puro: usamos password_verify para comparar com o hash.
*/

// Inclui o arquivo de configuração da conexão com o banco de dados.
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Utilitários compartilhados (ex.: redirect, assertPdo)
require_once __DIR__ . '/../configurações/utils.php';

// O login deve chegar via POST (enviado por formulário)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta os dados informados e remove espaços extras
    $identificador_login = trim($_POST['usuario'] ?? ''); // pode ser usuário OU e-mail
    $senha_login = $_POST['senha'] ?? '';

    // Validação inicial: campos obrigatórios
    if ($identificador_login === '' || $senha_login === '') {
        redirect('../visualizadores/login.php', 'erro', 'Por favor, preencha todos os campos.');
    }

    try {
        // Confirma que $pdo está disponível e é uma conexão válida
        assertPdo($pdo);

        // Prepara a consulta para buscar o usuário por nome de usuário OU por e-mail
        $consulta = $pdo->prepare("SELECT id, nome_usuario, email, senha, tipo_usuario, ativo FROM usuarios WHERE nome_usuario = :usuario OR email = :email");

        // Executa a consulta enviando os parâmetros de forma segura (evita SQL injection)
        $resultado = $consulta->execute([
            ':usuario' => $identificador_login,
            ':email' => $identificador_login
        ]);

        if (!$resultado) {
            throw new RuntimeException('Falha na execução da query');
        }

        // Busca o primeiro usuário encontrado
        $usuario = $consulta->fetch();

        if ($usuario) {
            if ((int)($usuario['ativo'] ?? 1) === 0) {
                redirect('../visualizadores/login.php', 'erro', 'Usuário desativado. Entre em contato com o administrador.');
            }
            // Compara a senha digitada com o hash armazenado (ex.: "porta trancada" vs "chave")
            if (password_verify($senha_login, $usuario['senha'])) {
                // Preenche a sessão para reconhecer o usuário nas próximas páginas
                $_SESSION['id_usuario'] = $usuario['id'];
                $_SESSION['nome_usuario'] = $usuario['nome_usuario'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario']; // 'admin' ou 'usuario'

                // Exemplo prático: após logar, enviar para a página inicial
                header("Location: ../visualizadores/paginainicial.php");
                exit;
            } else {
                // Senha incorreta: dica — verifique Caps Lock e se cadastrou corretamente
                redirect('../visualizadores/login.php', 'erro', 'Senha inválida. Por favor, tente novamente.');
            }
        } else {
            // Usuário não encontrado: talvez digitou e-mail em vez de usuário? Tente o outro campo.
            redirect('../visualizadores/login.php', 'erro', 'Usuário não encontrado. Verifique o nome de usuário ou e-mail inserido.');
        }
    } catch (Throwable $e) {
        // Tratamento geral: registra o erro e devolve uma mensagem amigável
        error_log('Erro de login: ' . $e->getMessage());
        redirect('../visualizadores/login.php', 'erro', 'Erro no sistema: ' . $e->getMessage());
    }
} else {
    // Acesso direto (GET): redireciona para a tela de login
    header("Location: ../visualizadores/login.php");
    exit;
}
?>
