<?php
session_start();
// Obtém a mensagem de erro e o status de usuário existente da sessão.
$mensagem_erro = $_SESSION['erro_cadastro'] ?? null;
$usuario_ja_existe = $_SESSION['usuario_ja_existe'] ?? false;
// Limpa as mensagens da sessão para não serem exibidas novamente.
unset($_SESSION['erro_cadastro'], $_SESSION['usuario_ja_existe']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Projeto PCC</title>
    <link rel="stylesheet" href="../estilizações/estilos-cadastro.css">
    <script src="../scripts/script-cadastro.js" defer></script>
</head>
<body>
    <header>
        <div class="logo">
            <h1>Projeto PCC</h1>
        </div>
        <nav>
            <a href="paginainicial.php">Página Inicial</a>
        </nav>
    </header>
    <main>
        <div class="container-cadastro">
            <h1>Cadastro de Novo Usuário</h1>

            <?php if ($mensagem_erro): ?>
                <div class="alerta-erro">
                    <p><?php echo htmlspecialchars($mensagem_erro); ?></p>
                    <?php if ($usuario_ja_existe): ?>
                        <a href="login.php" class="botao-login">Ir para a página de login</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="../controladores/processar_cadastro.php" method="post">
                <div class="grupo-formulario">
                    <label for="usuario">Usuário:</label>
                    <input type="text" id="usuario" name="usuario" required>
                    <div id="erro-usuario" class="erro-em-tempo-real" style="display: none;"></div>
                </div>
                <div class="grupo-formulario">
                    <label for="email">E-mail:</label>
                    <input type="email" id="email" name="email" required>
                    <div id="erro-email" class="erro-em-tempo-real" style="display: none;"></div>
                </div>
                <div class="grupo-formulario">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <div class="grupo-formulario">
                    <label for="confirma_senha">Confirmação de Senha:</label>
                    <input type="password" id="confirma_senha" name="confirma_senha" required>
                </div>
                <button type="submit">Cadastrar</button>
            </form>
            <a href="login.php" class="link-login">Já tem uma conta? Faça Login</a>
        </div>
    </main>
    <footer>
        <p>&copy; 2025 Projeto PCC. Todos os direitos reservados.</p>
    </footer>
</body>
</html>