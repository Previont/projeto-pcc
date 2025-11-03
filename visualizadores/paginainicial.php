<?php
session_start();
require_once '../modelos/configuraçõesdeconexão.php';

// Verifica se o usuário está logado
$usuario_esta_logado = isset($_SESSION['id_usuario']);
$nome_usuario = '';
$is_admin = false;

if ($usuario_esta_logado) {
    $user_id = $_SESSION['id_usuario'];

    // Busca o nome do usuário no banco de dados
    $stmt = $pdo->prepare("SELECT nome_usuario, tipo_usuario FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $nome_usuario = $usuario['nome_usuario'];
        // Verifica se o usuário é administrador
        if (isset($usuario['tipo_usuario']) && $usuario['tipo_usuario'] === 'admin') {
            $is_admin = true;
        }
    }
}

// Busca as 3 campanhas mais visitadas
$stmt_mais_visitadas = $pdo->query("SELECT * FROM campanhas ORDER BY visualizacoes DESC LIMIT 3");
$campanhas_mais_visitadas = $stmt_mais_visitadas->fetchAll();

// Busca as 3 campanhas mais recentes
$stmt_recentes = $pdo->query("SELECT * FROM campanhas ORDER BY data_criacao DESC LIMIT 3");
$campanhas_recentes = $stmt_recentes->fetchAll();

// Busca todas as campanhas
$stmt_todas = $pdo->query("SELECT * FROM campanhas ORDER BY data_criacao DESC");
$todas_as_campanhas = $stmt_todas->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projeto PCC</title>
    <link rel="stylesheet" href="../estilizações/estilos-pagina-inicial.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="<?php echo $is_admin ? 'admin-mode' : ''; ?>">
    <header>
        <div class="logo">
            <a href="paginainicial.php">Projeto PCC</a>
        </div>
        <div class="container-usuario">
            <?php if ($usuario_esta_logado): ?>
                <span class="nome-usuario"><?php echo htmlspecialchars($nome_usuario); ?></span>
            <?php endif; ?>
            <div class="icone-usuario" id="iconeUsuario">
                <i class="fas fa-user"></i>
            </div>
            <div class="menu-usuario" id="menuUsuario">
                <?php if ($usuario_esta_logado): ?>
                    <?php if ($is_admin): ?>
                        <a href="admin_dashboard.php">Painel Admin</a>
                    <?php endif; ?>
                    <a href="meu-perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                    <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                    <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                    <a href="pagamento.php"><i class="fas fa-credit-card"></i> Fazer Pagamento</a>
                    <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="cadastro.php">Cadastre-se</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <section class="destaque-principal">
            <h1>Destaques</h1>
            <div class="container-destaques">
                <?php foreach ($campanhas_mais_visitadas as $projeto): ?>
                    <div class="card-destaque">
                        <img src="<?php echo htmlspecialchars($projeto['url_imagem']); ?>" alt="Imagem do Projeto">
                        <div class="conteudo-card">
                            <h2><?php echo htmlspecialchars($projeto['titulo']); ?></h2>
                            <p><?php echo htmlspecialchars(substr($projeto['descricao'], 0, 100)); ?>...</p>
                            <div class="progresso">
                                <?php
                                $porcentagem = 0;
                                if ($projeto['meta_arrecadacao'] > 0) {
                                    $porcentagem = ($projeto['valor_arrecadado'] / $projeto['meta_arrecadacao']) * 100;
                                }
                                ?>
                                <div class="barra-progresso" style="width: <?php echo $porcentagem; ?>%;"></div>
                            </div>
                            <div class="detalhes-arrecadacao">
                                <span><?php echo number_format($porcentagem, 2, ',', '.'); ?>%</span>
                                <span>Arrecadado: R$ <?php echo number_format($projeto['valor_arrecadado'], 2, ',', '.'); ?></span>
                            </div>
                            <a href="campanha-detalhes.php?id=<?php echo $projeto['id']; ?>" class="botao-detalhes">Ver Detalhes</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="carrossel-secao">
            <h2>Campanhas Recentes</h2>
            <div class="carrossel">
                <div class="carrossel-container">
                    <?php foreach ($campanhas_recentes as $projeto): ?>
                        <div class="card-carrossel">
                            <img src="<?php echo htmlspecialchars($projeto['url_imagem']); ?>" alt="Imagem do Projeto">
                            <h3><?php echo htmlspecialchars($projeto['titulo']); ?></h3>
                            <p>Arrecadado: R$ <?php echo number_format($projeto['valor_arrecadado'], 2, ',', '.'); ?></p>
                            <a href="campanha-detalhes.php?id=<?php echo $projeto['id']; ?>" class="botao-detalhes">Ver Mais</a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="botao-carrossel prev"><i class="fas fa-chevron-left"></i></button>
                <button class="botao-carrossel next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </section>

        <section class="todas-as-campanhas">
            <h2>Todas as Campanhas</h2>
            <div class="grade-campanhas">
                <?php foreach ($todas_as_campanhas as $projeto): ?>
                    <div class="card-campanha">
                        <img src="<?php echo htmlspecialchars($projeto['url_imagem']); ?>" alt="Imagem do Projeto">
                        <div class="info-campanha">
                            <h3><?php echo htmlspecialchars($projeto['titulo']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($projeto['descricao'], 0, 80)); ?>...</p>
                            <div class="progresso">
                                <?php
                                $porcentagem = 0;
                                if ($projeto['meta_arrecadacao'] > 0) {
                                    $porcentagem = ($projeto['valor_arrecadado'] / $projeto['meta_arrecadacao']) * 100;
                                }
                                ?>
                                <div class="barra-progresso" style="width: <?php echo $porcentagem; ?>%;"></div>
                            </div>
                            <div class="detalhes-arrecadacao">
                                <span><?php echo number_format($porcentagem, 2, ',', '.'); ?>%</span>
                                <span>R$ <?php echo number_format($projeto['valor_arrecadado'], 2, ',', '.'); ?></span>
                            </div>
                            <a href="campanha-detalhes.php?id=<?php echo $projeto['id']; ?>" class="botao-saiba-mais">Saiba Mais</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; 2024 Projeto PCC. Todos os direitos reservados.</p>
    </footer>

    <script src="../scripts/script-menu.js"></script>
    <script src="../scripts/script-pagina-inicial.js"></script>
</body>
</html>