<?php
session_start();
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// 1. Verifica se o usuário está logado.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$usuario = null;

// 2. Busca os dados do usuário no banco de dados.
try {
    $consulta = $pdo->prepare("SELECT nome_usuario, email, data_registro FROM usuarios WHERE id = :id");
    $consulta->execute([':id' => $id_usuario]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em uma aplicação real, o erro seria registrado em logs.
    die("Erro ao buscar informações do perfil.");
}

// Se o usuário não for encontrado, encerra a execução.
if (!$usuario) {
    die("Usuário não encontrado.");
}

// Formata a data de cadastro para um formato mais amigável.
$data_cadastro = date("d/m/Y", strtotime($usuario['data_registro']));

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?php echo htmlspecialchars($usuario['nome_usuario']); ?></title>
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="../estilizações/estilos-perfil.css">
    <link rel="stylesheet" href="../estilizações/tema-escuro.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">Projeto PCC</a>
        </div>
        <div class="container-usuario">
            <?php if (isset($usuario) && $usuario): ?>
                <span class="nome-usuario"><?php echo htmlspecialchars($usuario['nome_usuario']); ?></span>
            <?php endif; ?>
            <div class="icone-usuario" id="iconeUsuario">
                <i class="fas fa-user"></i>
            </div>
            <div class="menu-usuario" id="menuUsuario">
                <a href="meu-perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>

    <main class="container-perfil">
        <div class="cartao-perfil">
            <div class="cabecalho-perfil">
                <i class="fas fa-user-circle fa-5x" style="color: #fff; margin-bottom: 15px;"></i>
                <h1 class="nome-perfil"><?php echo htmlspecialchars($usuario['nome_usuario']); ?></h1>
            </div>
            <div class="corpo-perfil">
                <div class="secao-perfil">
                    <h2><i class="fas fa-info-circle"></i> Informações</h2>
                    <p>Membro desde: <?php echo $data_cadastro; ?></p>
                </div>
                <div class="secao-perfil">
                    <h2><i class="fas fa-address-card"></i> Contato</h2>
                    <p class="contato-perfil">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($usuario['email']); ?>
                    </p>
                </div>
            </div>
            <div class="rodape-perfil">
                <a href="alterar-cadastro.php" class="btn-editar">Alterar Cadastro</a>
                <a href="paginainicial.php" class="btn-voltar">Voltar para a Página Inicial</a>
            </div>
        </div>
    </main>

    <script src="../scripts/script-menu.js" defer></script>
</body>
</html>