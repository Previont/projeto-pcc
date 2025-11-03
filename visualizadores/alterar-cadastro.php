<?php
session_start();
// Inclui o arquivo de configuração da conexão com o banco de dados.
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// Se o usuário não estiver logado, redireciona para a página de login.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$usuario = null;

try {
    // Busca as informações atuais do usuário para preencher o formulário.
    $consulta = $pdo->prepare("SELECT nome_usuario, email FROM usuarios WHERE id = :id");
    $consulta->execute([':id' => $id_usuario]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em caso de erro, interrompe a execução e exibe uma mensagem.
    die("Erro ao carregar as informações do usuário para edição.");
}

// Se o usuário não for encontrado no banco de dados, interrompe a execução.
if (!$usuario) {
    die("Usuário não encontrado.");
}

// Obtém mensagens de erro ou sucesso da sessão (se existirem).
$mensagem_erro = $_SESSION['mensagem_erro'] ?? '';
$mensagem_sucesso = $_SESSION['mensagem_sucesso'] ?? '';
// Limpa as mensagens da sessão para que não sejam exibidas novamente.
unset($_SESSION['mensagem_erro'], $_SESSION['mensagem_sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Cadastro</title>
    <link rel="stylesheet" href="../estilizações/estilos-alterar-cadastro.css">
</head>
<body>
    <div class="container">
        <h1>Alterar Dados do Cadastro</h1>

        <?php if ($mensagem_erro): ?>
            <p class="mensagem erro"><?php echo htmlspecialchars($mensagem_erro); ?></p>
        <?php endif; ?>
        <?php if ($mensagem_sucesso): ?>
            <p class="mensagem sucesso"><?php echo htmlspecialchars($mensagem_sucesso); ?></p>
        <?php endif; ?>

        <form action="../controladores/processar_alteracao.php" method="post">
            <label for="nome_usuario">Nome de Usuário:</label>
            <input type="text" id="nome_usuario" name="nome_usuario" value="<?php echo htmlspecialchars($usuario['nome_usuario']); ?>" required>
            
            <label for="email_usuario">E-mail:</label>
            <input type="email" id="email_usuario" name="email_usuario" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
            
            <p class="info">Deixe os campos de senha em branco se não desejar alterá-la.</p>
            
            <label for="nova_senha">Nova Senha:</label>
            <input type="password" id="nova_senha" name="nova_senha">
            
            <label for="confirmar_senha">Confirmar Nova Senha:</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha">
            
            <button type="submit">Salvar Alterações</button>
        </form>
        
        <a href="paginainicial.php" class="link-voltar">Voltar para a Página Inicial</a>
    </div>
</body>
</html>