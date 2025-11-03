<?php
session_start();
// Inclui o arquivo de configuração da conexão com o banco de dados.
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// Verifica se o usuário está logado e se é um administrador.
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    // Se não for um administrador, redireciona para a página inicial.
    header("Location: paginainicial.php");
    exit;
}

// Busca o nome do administrador logado.
$id_usuario = $_SESSION['id_usuario'];
$consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = ?");
$consulta_usuario->execute([$id_usuario]);
$administrador = $consulta_usuario->fetch();

// Lógica para buscar estatísticas do sistema.
try {
    // Contagem total de usuários cadastrados.
    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

    // Contagem total de campanhas criadas.
    $total_campanhas = $pdo->query("SELECT COUNT(*) FROM campanhas")->fetchColumn();

    // Soma do valor total arrecadado em todas as campanhas.
    $total_arrecadado = $pdo->query("SELECT SUM(arrecadado) FROM campanhas")->fetchColumn();
    // Garante que o valor seja 0 se não houver arrecadações.
    $total_arrecadado = $total_arrecadado ?? 0;

} catch (PDOException $e) {
    // Em caso de erro na busca, define valores padrão e uma mensagem de erro.
    $total_usuarios = 'N/D';
    $total_campanhas = 'N/D';
    $total_arrecadado = 'N/D';
    $mensagem_erro = "Erro ao buscar as estatísticas: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="../estilizações/estilos-admin.css">
</head>
<body>

    <aside class="barra-lateral">
        <h2>Administração</h2>
        <nav>
            <ul>
                <li><a href="#">Painel</a></li>
                <li><a href="#">Gerenciar Usuários</a></li>
                <li><a href="#">Gerenciar Campanhas</a></li>
                <li><a href="configuracoes.php">Configurações</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </nav>
    </aside>

    <main class="conteudo-principal">
        <header class="cabecalho">
            <div class="alternar-menu">
                &#9776; <!-- Ícone de hambúrguer -->
            </div>
            <div class="info-usuario">
                <span>Olá, <?php echo htmlspecialchars($administrador['nome_usuario'] ?? 'Admin'); ?></span>
                <a href="meu-perfil.php">Meu Perfil</a>
            </div>
        </header>

        <h1>Painel de Controle</h1>

        <?php if (isset($mensagem_erro)): ?>
            <div class="erro"><?php echo $mensagem_erro; ?></div>
        <?php endif; ?>

        <section class="estatisticas">
            <div class="cartao">
                <h3>Total de Usuários</h3>
                <p><?php echo $total_usuarios; ?></p>
            </div>
            <div class="cartao">
                <h3>Total de Campanhas</h3>
                <p><?php echo $total_campanhas; ?></p>
            </div>
            <div class="cartao">
                <h3>Arrecadação Total</h3>
                <p>R$ <?php echo number_format($total_arrecadado, 2, ',', '.'); ?></p>
            </div>
        </section>

    </main>

    <script src="../scripts/script-admin.js"></script>
</body>
</html>