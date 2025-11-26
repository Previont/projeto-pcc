<?php
session_start();

/*
 Objetivo: receber dados do formulário de cadastro e criar um novo usuário com segurança.
 
 Termos:
 - "Validação": conferir se os dados fazem sentido (ex.: e-mail válido, senhas iguais).
 - "Prepared statement": forma segura de enviar dados para o banco, evitando ataques.
 - "Hash de senha": guarda a senha embaralhada, como um cofre que só confere a chave.

 Diagrama mental:
 [Formulário] -> [Validações] -> [Checar se já existe] -> [Criar usuário] -> [Mensagem de sucesso]

 Dicas e erros comuns:
 - Senhas diferentes: sempre confira os dois campos antes de enviar.
 - E-mail com espaço: use trim para limpar entradas.
 - Repetir usuário: o sistema bloqueia duplicidade e avisa.
*/

// Inclui o arquivo de configuração da conexão com o banco de dados.
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Verifica se o método da requisição é POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Utilitários compartilhados (ex.: redirect)
    require_once __DIR__ . '/../configurações/utils.php';
    if (!function_exists('redirect')) { throw new Exception('Função redirect não disponível - verifique inclusões'); }

    // Coleta e higieniza os dados do formulário
    $nome_usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmacao_senha = $_POST['confirma_senha'] ?? '';

    // Validações de entrada com retornos imediatos (mais simples para quem está começando)
    if ($nome_usuario === '' || $email === '' || $senha === '' || $confirmacao_senha === '') {
        redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'Todos os campos são obrigatórios.');
    }
    if ($senha !== $confirmacao_senha) {
        redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'As senhas não coincidem.');
    }
    if (strlen($senha) < 6) {
        redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'A senha deve ter no mínimo 6 caracteres.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'O formato do e-mail é inválido.');
    }
    if (strlen($nome_usuario) < 3) {
        redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'O nome de usuário deve ter no mínimo 3 caracteres.');
    }

    try {
        try {
            // Passo 1: verificar duplicidade (já existe?)
            $consulta_usuario = $pdo->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? OR email = ?");
            $consulta_usuario->execute([$nome_usuario, $email]);
            $usuario_existente = $consulta_usuario->fetch();

            if ($usuario_existente) {
                // Já existe: enviamos uma mensagem amigável e voltamos para o formulário
                $_SESSION['usuario_ja_existe'] = true;
                redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'Este nome de usuário ou e-mail já está cadastrado no sistema.');
            } else {
                // Passo 2: gerar o hash da senha (nunca salve senha em texto puro!)
                $hash_senha = password_hash($senha, PASSWORD_DEFAULT);

                // Passo 3: inserir o novo usuário
                $inserir_usuario = $pdo->prepare("INSERT INTO usuarios (nome_usuario, email, senha) VALUES (?, ?, ?)");
                $inserir_usuario->execute([$nome_usuario, $email, $hash_senha]);

                // Exemplo prático: após cadastrar, vá para o login
                redirect('../visualizadores/login.php', 'sucesso_cadastro', 'Seu cadastro foi realizado com sucesso! Faça o login para continuar.');
            }
        } catch (PDOException $e) {
            // Tratamento de erro de banco: informe ao usuário de forma simples
            redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'Erro ao processar o cadastro. Tente novamente. Detalhe: ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        redirect('../visualizadores/cadastro.php', 'erro_cadastro', 'Erro no sistema: ' . $e->getMessage());
    }
} else {
    // Acesso direto (GET): redireciona para a tela de cadastro
    header('Location: ../visualizadores/cadastro.php');
    exit();
}
?>
