<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Se o usuário não estiver logado, redireciona para a página de login.
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = '';

// Busca o nome do usuário logado para exibição.
try {
    $consulta = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = :id");
    $consulta->execute([':id' => $id_usuario]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nome_usuario = $usuario['nome_usuario'];
    }
} catch (PDOException $e) {
    // Em caso de erro, o nome do usuário não será exibido.
}

// Recupera e limpa as mensagens da sessão.
$erro = $_SESSION['erro'] ?? '';
$sucesso = $_SESSION['sucesso'] ?? '';
unset($_SESSION['erro'], $_SESSION['sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Campanha</title>
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
                <a href="criar_campanha.php" class="ativo"><i class="fas fa-plus"></i> Criar Campanha</a>
                <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>

    <main class="container">
        <h1>Crie sua Campanha</h1>
        <p class="subtitulo">Preencha os detalhes abaixo para lançar sua campanha.</p>

        <?php if ($erro): ?>
            <div class="mensagem-erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="mensagem-sucesso"><?php echo htmlspecialchars($sucesso); ?></div>
        <?php endif; ?>

        <form action="../controladores/processar_campanha.php" method="post">
            <label for="titulo">Título da Campanha</label>
            <input type="text" id="titulo" name="titulo" placeholder="Ex: Ajude a construir um novo abrigo" required>
            
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="4" placeholder="Conte a história por trás da sua campanha..." required></textarea>
            
            <label for="meta">Meta de Arrecadação (R$)</label>
            <input type="number" id="meta" name="meta" step="0.01" placeholder="Ex: 5000.00" required>
            
            <label for="meta">Meta de Arrecadação (R$)</label>
            <input type="number" id="meta" name="meta" step="0.01" placeholder="Ex: 5000.00" required>
            
            <!-- Seção de Itens da Campanha -->
            <div class="itens-secao">
                <h3>Itens Oferecidos</h3>
                <p class="subtitulo-itens">Adicione itens específicos que podem ser oferecidos nesta campanha com valores fixos</p>
                
                <div id="lista-itens">
                    <!-- Itens serão adicionados dinamicamente aqui -->
                </div>
                
                <button type="button" id="adicionar-item" class="btn-adicionar-item">
                    <i class="fas fa-plus"></i> Adicionar Item
                </button>
            </div>
            
            <button type="submit">Criar Campanha</button>
        </form>
        <a href="paginainicial.php" class="link-voltar">Voltar</a>
    </main>

    <!-- Template para novos itens -->
    <template id="template-item">
        <div class="item-campanha">
            <div class="item-header">
                <h4>Novo Item</h4>
                <button type="button" class="btn-remover-item" title="Remover item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="item-campos">
                <div class="form-group">
                    <label>Nome do Item</label>
                    <input type="text" name="itens[nome][]" placeholder="Ex: Camiseta personalizada" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição do Item</label>
                    <textarea name="itens[descricao][]" rows="3" placeholder="Descrição detalhada do item..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Valor Fixo (R$)</label>
                    <input type="number" name="itens[valor][]" step="0.01" placeholder="Ex: 25.00" required>
                </div>
                
                <div class="form-group">
                    <label>Imagem do Item (opcional)</label>
                    <input type="url" name="itens[imagem][]" placeholder="https://exemplo.com/imagem.jpg">
                </div>
            </div>
        </div>
    </template>

    <script src="../scripts/script-menu.js" defer></script>
    <script src="../scripts/script-itens-campanha.js" defer></script>
</body>
</html>