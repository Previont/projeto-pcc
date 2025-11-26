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

// Objetivo desta página: apresentar o formulário para criar uma nova campanha
// Diagrama mental:
// [Sessão válida?] -> [Carregar nome do usuário] -> [Exibir mensagens] -> [Formulário de campanha + itens]
// Dica: use exemplos nos placeholders para guiar o preenchimento

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

exigirUsuarioAtivo($pdo);

$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = '';


try {
    $consulta = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = :id");
    $consulta->execute([':id' => $id_usuario]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nome_usuario = $usuario['nome_usuario'];
    }
} catch (PDOException $e) {

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
    <title>Criar Nova Campanha</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="../estilizações/estilos-criar-campanha.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Cabeçalho com logo e menu do usuário -->
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
                <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                <a href="criar_campanha.php" class="ativo"><i class="fas fa-plus"></i> Criar Campanha</a>
                <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal com formulário didático -->
    <main class="container">
        <h1>Crie sua Campanha</h1>
        <p class="subtitulo">Preencha os detalhes abaixo para lançar sua campanha.</p>

        <?php if ($erro): ?>
            <div class="mensagem-erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="mensagem-sucesso"><?php echo htmlspecialchars($sucesso); ?></div>
        <?php endif; ?>

        <!-- Formulário de criação de campanha
             Campos principais:
             - Título: nome que resume a causa
             - Descrição: conte a história e objetivo
             - Meta: valor a arrecadar (ex.: 5000.00)
             Erros comuns: meta negativa ou zero — valide antes de enviar. -->
        <form action="../controladores/processar_campanha.php" method="post" enctype="multipart/form-data">
            <label for="titulo">Título da Campanha</label>
            <input type="text" id="titulo" name="titulo" placeholder="Ex: Ajude a construir um novo abrigo" required>
            
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="4" placeholder="Conte a história por trás da sua campanha..." required></textarea>
            
            <label for="meta">Meta de Arrecadação (R$)</label>
            <input type="number" id="meta" name="meta" step="0.01" placeholder="Ex: 5000.00" required>

            <div class="form-group">
                <label>Imagem da Campanha (opcional)</label>
                <div class="upload-container">
                    <input type="file"
                           name="campanha_imagem"
                           id="campanha_imagem_input"
                           class="file-input"
                           accept="image/jpeg,image/jpg,image/png,image/gif">
                    <label for="campanha_imagem_input" class="file-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Escolher imagem</span>
                    </label>
                    <div class="file-info" style="display: none;">
                        <span class="file-name"></span>
                        <button type="button" class="btn-remover-imagem" title="Remover imagem">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="preview-imagem" style="display: none;">
                        <img src="" alt="Preview da imagem" class="preview-img">
                    </div>
                </div>
                <div class="erro-imagem" style="display: none;"></div>
            </div>
            
            
            <!-- Seção de itens: produtos ou recompensas da campanha -->
            <div class="itens-secao">
                <h3>Itens Oferecidos</h3>
                <p class="subtitulo-itens">Adicione itens específicos que podem ser oferecidos nesta campanha com valores fixos</p>
                
                <div id="lista-itens">
                    
                </div>
                
                <button type="button" id="adicionar-item" class="btn-adicionar-item">
                    <i class="fas fa-plus"></i> Adicionar Item
                </button>
            </div>
            
            <button type="submit">Criar Campanha</button>
        </form>
        <a href="paginainicial.php" class="link-voltar">Voltar</a>
    </main>

    
    <!-- Template reutilizável de item (clonado via JavaScript) -->
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
                    <div class="upload-container">
                        <input type="file"
                               name="itens[imagem][]"
                               class="file-input"
                               accept="image/jpeg,image/jpg,image/png,image/gif">
                        <label for="" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Escolher imagem</span>
                        </label>
                        <div class="file-info" style="display: none;">
                            <span class="file-name"></span>
                            <button type="button" class="btn-remover-imagem" title="Remover imagem">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="preview-imagem" style="display: none;">
                            <img src="" alt="Preview da imagem" class="preview-img">
                        </div>
                    </div>
                    <div class="erro-imagem" style="display: none;"></div>
                </div>
            </div>
        </div>
    </template>

    <script src="../scripts/utils.js" defer></script>
    <script src="../scripts/script-menu.js" defer></script>
    <script src="../scripts/itens-campanha-core.js" defer></script>
    <script src="../scripts/script-itens-campanha.js" defer></script>
</body>
</html>
