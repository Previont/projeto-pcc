<?php
session_start();
// Recupera a mensagem de erro da sessão, se houver.
$mensagem_erro = $_SESSION['erro'] ?? '';
// Limpa a mensagem de erro da sessão para que não seja exibida novamente.
unset($_SESSION['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Projeto PCC</title>
    <link rel="stylesheet" href="../estilizações/estilos-login.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1><a href="paginainicial.php">Projeto PCC</a></h1>
        </div>
        <nav>
            <a href="paginainicial.php">Página Inicial</a>
        </nav>
    </header>

    <main>
        <div class="container-login">
            <h1>Login</h1>
            <?php if ($mensagem_erro): ?>
                <div class="mensagem-erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
            <?php endif; ?>
            
            <form action="../controladores/processar_login.php" method="post">
                <div class="grupo-formulario">
                    <label for="usuario">Usuário ou Email:</label>
                    <input type="text" id="usuario" name="usuario" required>
                </div>
                <div class="grupo-formulario">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <button type="submit">Entrar</button>
            </form>
            <a href="cadastro.php" class="link-cadastro">Não tem uma conta? Cadastre-se</a>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Projeto PCC. Todos os direitos reservados.</p>
    </footer>
</body>
</html>