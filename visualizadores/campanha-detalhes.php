<?php
session_start();
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// Verifica se o usuário está logado para exibir informações personalizadas.
$usuario_esta_logado = isset($_SESSION['id_usuario']);
$nome_usuario_logado = '';

if ($usuario_esta_logado) {
    try {
        $consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = :id");
        $consulta_usuario->execute([':id' => $_SESSION['id_usuario']]);
        $usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            $nome_usuario_logado = $usuario['nome_usuario'];
        }
    } catch (PDOException $e) {
        // Em caso de erro, o nome do usuário simplesmente não será exibido.
    }
}

// Obtém o ID da campanha a partir da URL. Se não existir, redireciona.
$id_campanha = $_GET['id'] ?? null;
if (!$id_campanha) {
    header('Location: minhas-campanhas.php');
    exit;
}

try {
    // Incrementa o contador de visualizações da campanha.
    $atualizar_visitas = $pdo->prepare("UPDATE campanhas SET visualizacoes = visualizacoes + 1 WHERE id = ?");
    $atualizar_visitas->execute([$id_campanha]);

    // Busca os detalhes da campanha, juntando com o nome do criador.
    $sql = "SELECT c.id, c.titulo, c.descricao, c.meta_arrecadacao, c.valor_arrecadado, c.data_criacao, u.nome_usuario
            FROM campanhas c
            JOIN usuarios u ON c.id_usuario = u.id
            WHERE c.id = ?";
    
    $consulta_campanha = $pdo->prepare($sql);
    $consulta_campanha->execute([$id_campanha]);
    $campanha = $consulta_campanha->fetch(PDO::FETCH_ASSOC);

    // Se a campanha não for encontrada, redireciona para minhas campanhas.
    if (!$campanha) {
        $_SESSION['erro_campanha'] = "Campanha não encontrada.";
        header('Location: minhas-campanhas.php');
        exit;
    }

    // Calcula a porcentagem do valor arrecadado em relação à meta.
    $porcentagem_arrecadada = 0;
    if ($campanha['meta_arrecadacao'] > 0) {
        $porcentagem_arrecadada = ($campanha['valor_arrecadado'] / $campanha['meta_arrecadacao']) * 100;
    }

    // Busca os itens da campanha (se existirem)
    $sql_itens = "SELECT nome_item, descricao_item, valor_fixo, url_imagem FROM itens_campanha WHERE id_campanha = ? ORDER BY id";
    $consulta_itens = $pdo->prepare($sql_itens);
    $consulta_itens->execute([$id_campanha]);
    $itens_campanha = $consulta_itens->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['erro_campanha'] = "Erro ao buscar detalhes da campanha: " . $e->getMessage();
    header('Location: minhas-campanhas.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($campanha['titulo']); ?></title>
    <link rel="stylesheet" href="../estilizações/estilos-campanha-detalhes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="../scripts/script-menu.js" defer></script>
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">Projeto PCC</a>
        </div>
        <div class="container-usuario">
            <?php if ($usuario_esta_logado): ?>
                <span class="nome-usuario"><?php echo htmlspecialchars($nome_usuario_logado); ?></span>
            <?php endif; ?>
            <div class="icone-usuario" id="iconeUsuario">
                <i class="fas fa-user"></i>
            </div>
            <div class="menu-usuario" id="menuUsuario">
                <?php if ($usuario_esta_logado): ?>
                    <a href="meu-perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                    <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                    <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                    <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php else: ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container-detalhes">
        <div class="cabecalho-detalhes">
            <h1><?php echo htmlspecialchars($campanha['titulo']); ?></h1>
            <p class="criador">Campanha criada por: <strong><?php echo htmlspecialchars($campanha['nome_usuario']); ?></strong></p>
            <p class="data-criacao">Criada em: <?php echo date('d/m/Y H:i', strtotime($campanha['data_criacao'])); ?></p>
        </div>
        
        <div class="detalhes-campanha">
            <h3>Descrição</h3>
            <p><?php echo nl2br(htmlspecialchars($campanha['descricao'])); ?></p>
        </div>

        <div class="progresso-campanha">
            <h3>Progresso da Arrecadação</h3>
            <div class="progresso-info">
                <span class="arrecadado">R$ <?php echo number_format($campanha['valor_arrecadado'], 2, ',', '.'); ?></span>
                <span class="meta">Meta: R$ <?php echo number_format($campanha['meta_arrecadacao'], 2, ',', '.'); ?></span>
            </div>
            <div class="barra-progresso">
                <div class="progresso-atual" style="width: <?php echo min(round($porcentagem_arrecadada, 2), 100); ?>%;"></div>
            </div>
            <div class="porcentagem"><?php echo round($porcentagem_arrecadada, 1); ?>%</div>
        </div>

        <?php if (!empty($itens_campanha)): ?>
        <div class="itens-campanha">
            <h3>Itens Oferecidos</h3>
            <p class="subtitulo-itens">Contribua especificamente para obter estes itens</p>
            <div class="grid-itens">
                <?php foreach ($itens_campanha as $item): ?>
                    <div class="item-card">
                        <?php if (!empty($item['url_imagem'])): ?>
                            <div class="item-imagem">
                                <img src="<?php echo htmlspecialchars($item['url_imagem']); ?>" alt="<?php echo htmlspecialchars($item['nome_item']); ?>" onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                        <div class="item-conteudo">
                            <h4><?php echo htmlspecialchars($item['nome_item']); ?></h4>
                            <p class="item-descricao"><?php echo htmlspecialchars($item['descricao_item']); ?></p>
                            <div class="item-valor">
                                <span class="valor">R$ <?php echo number_format($item['valor_fixo'], 2, ',', '.'); ?></span>
                                <button class="btn-selecionar-item" data-valor="<?php echo $item['valor_fixo']; ?>">
                                    Selecionar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="acoes-campanha">
            <button class="btn-apoiar">Apoiar esta Campanha</button>
            <a href="minhas-campanhas.php" class="btn-voltar">Voltar às Minhas Campanhas</a>
        </div>
    </div>
</body>
</html>