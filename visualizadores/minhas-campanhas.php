<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) { require_once $config_file; } else { die('Erro: Arquivo de configuração não encontrado em ' . $config_file); }
require_once __DIR__ . '/../configurações/utils.php';
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Objetivo: listar campanhas do usuário com status e ações rápidas (ver, editar, excluir).
// Diagrama mental:
// [Sessão válida?] -> [Buscar campanhas] -> [Calcular progresso] -> [Render cards] -> [Ações]

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

exigirUsuarioAtivo($pdo);

$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = '';


try {
    $consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = :id");
    $consulta_usuario->execute([':id' => $id_usuario]);
    $usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nome_usuario = $usuario['nome_usuario'];
    }
} catch (PDOException $e) {

}


try {
    $sql = "SELECT id, titulo, descricao, meta_arrecadacao, valor_arrecadado, data_criacao 
            FROM campanhas 
            WHERE id_usuario = :id_usuario 
            ORDER BY data_criacao DESC";
    $consulta_campanhas = $pdo->prepare($sql);
    $consulta_campanhas->execute([':id_usuario' => $id_usuario]);
    $campanhas = $consulta_campanhas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $campanhas = [];

}


$erro = $_SESSION['erro_campanha'] ?? '';
$sucesso = $_SESSION['sucesso_campanha'] ?? '';
unset($_SESSION['erro_campanha'], $_SESSION['sucesso_campanha']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Campanhas</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="../estilizações/estilos-minhas-campanhas.css">
    <link rel="stylesheet" href="../estilizações/tema-escuro.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">origoidea</a>
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
                <a href="minhas-campanhas.php" class="ativo"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal com listagem e ações -->
    <main class="container">
        <div class="cabecalho-pagina">
            <h1><i class="fas fa-heart"></i> Minhas Campanhas</h1>
            <a href="criar_campanha.php" class="btn-primary">
                <i class="fas fa-plus"></i> Nova Campanha
            </a>
        </div>
        
        <?php if ($erro): ?>
            <div class="mensagem mensagem-erro">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="mensagem mensagem-sucesso">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>
        
        <div class="container-campanhas">
            <?php if (!empty($campanhas)): ?>
                <?php foreach ($campanhas as $campanha): 

                    $porcentagem = ($campanha['meta_arrecadacao'] > 0) ? 
                        ($campanha['valor_arrecadado'] / $campanha['meta_arrecadacao']) * 100 : 0;
                    

                    $status = 'Ativa';
                    if ($porcentagem >= 100) {
                        $status = 'Meta Atingida';
                    } elseif ($campanha['valor_arrecadado'] > 0) {
                        $status = 'Em Andamento';
                    }
                ?>
                    <div class="card-campanha">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($campanha['titulo']); ?></h3>
                            <span class="status status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>">
                                <?php echo $status; ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <p class="descricao"><?php echo htmlspecialchars(substr($campanha['descricao'], 0, 120)); ?>...</p>
                            
                            <div class="progresso-container">
                                <div class="progresso-info">
                                    <span class="arrecadado">R$ <?php echo number_format($campanha['valor_arrecadado'], 2, ',', '.'); ?></span>
                                    <span class="meta">de R$ <?php echo number_format($campanha['meta_arrecadacao'], 2, ',', '.'); ?></span>
                                </div>
                                <div class="barra-progresso">
                                    <div class="progresso-atual" style="width: <?php echo min($porcentagem, 100); ?>%;"></div>
                                </div>
                                <div class="porcentagem"><?php echo round($porcentagem, 1); ?>%</div>
                            </div>
                            
                            <div class="info-meta">
                                <span><i class="fas fa-calendar"></i> Criada em <?php echo date('d/m/Y', strtotime($campanha['data_criacao'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <a href="campanha-detalhes.php?id=<?php echo $campanha['id']; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> Ver Detalhes
                            </a>
                            <a href="editar-campanha.php?id=<?php echo $campanha['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <button class="btn btn-danger" onclick="confirmarExclusao(<?php echo $campanha['id']; ?>, '<?php echo htmlspecialchars($campanha['titulo']); ?>')">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sem-campanhas">
                    <i class="fas fa-heart-broken"></i>
                    <h3>Nenhuma campanha encontrada</h3>
                    <p>Você ainda não criou nenhuma campanha.</p>
                    <a href="criar_campanha.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Criar Primeira Campanha
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="botoes-navegacao">
            <a href="paginainicial.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Página Inicial
            </a>
        </div>
    </main>

    
    <div id="modal-exclusao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão</h3>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir a campanha <strong id="nome-campanha"></strong>?</p>
                <p class="aviso">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-actions">
                <form id="form-exclusao" method="post" action="../controladores/processar_alteracao_campanha.php" style="display: inline;">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id_campanha" id="id-campanha-excluir">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sim, Excluir
                    </button>
                </form>
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <script src="../scripts/utils.js" defer></script>
    <script src="../scripts/script-menu.js" defer></script>
    <script>
        function confirmarExclusao(id, nome) {
            document.getElementById('id-campanha-excluir').value = id;
            document.getElementById('nome-campanha').textContent = nome;
            document.getElementById('modal-exclusao').style.display = 'flex';
        }

        function fecharModal() {
            document.getElementById('modal-exclusao').style.display = 'none';
        }


        window.onclick = function(event) {
            const modal = document.getElementById('modal-exclusao');
            if (event.target === modal) {
                fecharModal();
            }
        }


        setTimeout(function() {
            const mensagens = document.querySelectorAll('.mensagem');
            mensagens.forEach(function(mensagem) {
                mensagem.style.opacity = '0';
                setTimeout(function() {
                    mensagem.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>
