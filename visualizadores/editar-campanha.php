<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Verificação de segurança
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$id_campanha = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Verifica se o ID da campanha é válido
if (!$id_campanha) {
    $_SESSION['erro_campanha'] = "ID da campanha inválido.";
    header('Location: minhas-campanhas.php');
    exit;
}

// Busca os dados da campanha
try {
    $consulta = $pdo->prepare("SELECT * FROM campanhas WHERE id = ? AND id_usuario = ?");
    $consulta->execute([$id_campanha, $id_usuario]);
    $campanha = $consulta->fetch(PDO::FETCH_ASSOC);
    
    if (!$campanha) {
        $_SESSION['erro_campanha'] = "Campanha não encontrada ou você não tem permissão para editá-la.";
        header('Location: minhas-campanhas.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['erro_campanha'] = "Erro ao buscar dados da campanha.";
    header('Location: minhas-campanhas.php');
    exit;
}

// Recupera mensagens de erro ou sucesso
$erro = $_SESSION['erro_campanha'] ?? '';
$sucesso = $_SESSION['sucesso_campanha'] ?? '';
unset($_SESSION['erro_campanha'], $_SESSION['sucesso_campanha']);

$nome_usuario = '';
// Busca o nome do usuário para exibição no cabeçalho
try {
    $consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = :id");
    $consulta_usuario->execute([':id' => $id_usuario]);
    $usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nome_usuario = $usuario['nome_usuario'];
    }
} catch (PDOException $e) {
    // Em caso de erro, o nome do usuário não será exibido.
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Campanha</title>
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="../estilizações/estilos-criar-campanha.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">Projeto PCC</a>
        </div>
        <div class="container-usuario">
            <?php if (!empty($nome_usuario)): ?>
                <span class="nome-usuario"><?php echo htmlspecialchars($nome_usuario); ?></span>
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

    <main class="container">
        <h1>Editar Campanha</h1>
        <p class="subtitulo">Atualize os detalhes da sua campanha.</p>

        <?php if ($erro): ?>
            <div class="mensagem-erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="mensagem-sucesso"><?php echo htmlspecialchars($sucesso); ?></div>
        <?php endif; ?>

        <form action="../controladores/processar_alteracao_campanha.php" method="post">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id_campanha" value="<?php echo $campanha['id']; ?>">
            
            <label for="titulo">Título da Campanha</label>
            <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($campanha['titulo']); ?>" required>
            
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="4" required><?php echo htmlspecialchars($campanha['descricao']); ?></textarea>
            
            <label for="meta">Meta de Arrecadação (R$)</label>
            <input type="number" id="meta" name="meta" step="0.01" value="<?php echo $campanha['meta_arrecadacao']; ?>" required>
            
            <div class="botoes-acoes">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="minhas-campanhas.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <script src="../scripts/script-menu.js" defer></script>
</body>
</html>