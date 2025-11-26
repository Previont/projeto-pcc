<?php
session_start();
/*
 Propósito: painel administrativo com estatísticas agregadas de usuários e campanhas.
 Funcionalidade: calcula totais de usuários, campanhas e soma arrecadada.
 Relacionados: `visualizadores/admin_gerenciar_campanhas.php`, `visualizadores/admin_gerenciar_usuarios.php`.
 Entradas: nenhuma direta; usa sessão e banco.
 Saídas: números resumidos exibidos no frontend.
 Exemplos: mostrar cards com "Total de Usuários", "Total de Campanhas", "Arrecadação Total".
 Boas práticas: tratar exceções de banco e garantir apenas acesso de administradores.
 Armadilhas: valores nulos em agregações — normalizar para 0 para evitar avisos.
*/

$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}


if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {

    header("Location: paginainicial.php");
    exit;
}


$id_usuario = $_SESSION['id_usuario'];
$consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = ?");
$consulta_usuario->execute([$id_usuario]);
$administrador = $consulta_usuario->fetch();


try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $total_usuarios = $stmt ? $stmt->fetchColumn() : 0;


    $stmt = $pdo->query("SELECT COUNT(*) FROM campanhas");
    $total_campanhas = $stmt ? $stmt->fetchColumn() : 0;


    $stmt = $pdo->query("SELECT SUM(valor_arrecadado) FROM campanhas");
    $total_arrecadado = $stmt ? $stmt->fetchColumn() : 0;
    

    $total_arrecadado = $total_arrecadado ?? 0;


    $total_usuarios = is_numeric($total_usuarios) ? (int)$total_usuarios : 0;
    $total_campanhas = is_numeric($total_campanhas) ? (int)$total_campanhas : 0;
    $total_arrecadado = is_numeric($total_arrecadado) ? (float)$total_arrecadado : 0.0;

} catch (PDOException $e) {

    $total_usuarios = 0;
    $total_campanhas = 0;
    $total_arrecadado = 0;
    $mensagem_erro = "Erro ao buscar as estatísticas: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-admin.css">
</head>
<body>

    <aside class="barra-lateral">
        <h2>
            <a href="paginainicial.php" class="titulo-admin-link" onclick="registrarClique('titulo_admin')" title="Ir para página inicial">
                Administração
            </a>
        </h2>
        <nav>
            <ul>
                <li><a href="paginainicial.php" class="menu-inicio" onclick="registrarClique('menu_inicio')" title="Ir para página inicial">Página Inicial</a></li>
                <li><a href="admin_dashboard.php">Painel</a></li>
                <li><a href="admin_gerenciar_usuarios.php">Gerenciar Usuários</a></li>
                <li><a href="admin_gerenciar_campanhas.php">Gerenciar Campanhas</a></li>
                <li><a href="configuracoes.php">Configurações</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </nav>
    </aside>

    <main class="conteudo-principal">
        <header class="cabecalho">
            <div class="alternar-menu">
                &#9776; 
            </div>
            <div class="info-usuario">
            <span>Olá, <?php echo htmlspecialchars($administrador['nome_usuario'] ?? 'Administrador'); ?></span>
                <a href="meu-perfil.php">Meu Perfil</a>
            </div>
        </header>

        <div class="container">
            <h1>Painel de Controle</h1>

            <?php if (isset($mensagem_erro)): ?>
                <div class="erro"><?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <section class="estatisticas">
                <div class="cartao">
                    <h3>Total de Usuários</h3>
                    <p><?php echo is_numeric($total_usuarios) ? number_format($total_usuarios, 0, ',', '.') : '0'; ?></p>
                </div>
                <div class="cartao">
                    <h3>Total de Campanhas</h3>
                    <p><?php echo is_numeric($total_campanhas) ? number_format($total_campanhas, 0, ',', '.') : '0'; ?></p>
                </div>
                <div class="cartao">
                    <h3>Arrecadação Total</h3>
                    <p>R$ <?php
                        $valor_exibicao = is_numeric($total_arrecadado) ? (float)$total_arrecadado : 0.0;
                        echo number_format($valor_exibicao, 2, ',', '.');
                    ?></p>
                </div>
            </section>
        </div>

    </main>

    <script src="../scripts/utils.js" defer></script>
    <script src="../scripts/script-admin.js"></script>
    <script>

        function registrarClique(elemento) {

            

            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    'event_category': 'Navegação',
                    'event_label': elemento
                });
            }
            

        }
        

        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('.titulo-admin-link, .menu-inicio');
            links.forEach(link => {
                link.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
        });
    </script>
</body>
</html>
