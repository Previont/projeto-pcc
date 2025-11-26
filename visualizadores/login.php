<?php
session_start();
// Objetivo desta página: exibir o formulário de login e mostrar mensagens de erro/sucesso.
// Dica: usamos sessão para transportar mensagens entre requisições (como um bilhete temporário).
$mensagem_erro = $_SESSION['erro'] ?? '';
unset($_SESSION['erro']); // limpa para não repetir mensagem ao recarregar
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Entrar - origoidea</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-login.css">
    <script src="../scripts/utils.js" defer></script>
</head>
<body>
    <!-- Cabeçalho da página: logo e navegação básica -->
    <header>
        <div class="logo">
            <h1><a href="paginainicial.php">origoidea</a></h1>
        </div>
        <nav>
            <a href="paginainicial.php">Página Inicial</a>
        </nav>
    </header>

    <!-- Conteúdo principal: cartão com formulário de login -->
    <main>
        <div class="container-login">
        <h1>Entrar</h1>
            <?php if ($mensagem_erro): ?>
                <!-- Mensagem de erro didática: exemplo de feedback ao usuário -->
                <div class="mensagem-erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
            <?php endif; ?>
            
            <!-- Formulário de login
                 Campos obrigatórios:
                 - Usuário ou e-mail: como identificar a conta
                 - Senha: a chave de acesso
                 Erro comum: digitar e-mail no campo de usuário — ambos funcionam aqui. -->
            <form action="../controladores/processar_login.php" method="post">
                <div class="grupo-formulario">
            <label for="usuario">Usuário ou E-mail:</label>
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

    <!-- Rodapé simples com direitos autorais -->
    <footer>
        <p>&copy; 2025 origoidea. Todos os direitos reservados.</p>
    </footer>
</body>
</html>
