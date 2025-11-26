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

// Objetivo: permitir editar uma campanha existente com itens e imagens.
// Diagrama mental:
// [Sessão] -> [Carregar campanha] -> [Editar campos] -> [Gerenciar itens] -> [Salvar]

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

exigirUsuarioAtivo($pdo);

$id_usuario = $_SESSION['id_usuario'];
$id_campanha = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);


if (!$id_campanha) {
    $_SESSION['erro_campanha'] = "ID da campanha inválido.";
    header('Location: minhas-campanhas.php');
    exit;
}


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


    $itens_campanha = [];
    try {
        $consulta_itens = $pdo->prepare("SELECT * FROM itens_campanha WHERE id_campanha = ? ORDER BY id");
        $consulta_itens->execute([$id_campanha]);
        $itens_campanha = $consulta_itens->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {

        $itens_campanha = [];
    }


$erro = $_SESSION['erro_campanha'] ?? '';
$sucesso = $_SESSION['sucesso_campanha'] ?? '';
unset($_SESSION['erro_campanha'], $_SESSION['sucesso_campanha']);

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Campanha</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="../estilizações/estilos-criar-campanha.css">
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
                <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal com formulário de edição -->
    <main class="container">
        <h1>Editar Campanha</h1>
        <p class="subtitulo">Atualize os detalhes da sua campanha.</p>

        <?php if ($erro): ?>
            <div class="mensagem-erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="mensagem-sucesso"><?php echo htmlspecialchars($sucesso); ?></div>
        <?php endif; ?>

        <!-- Formulário de edição: os valores atuais são carregados para facilitar a atualização -->
        <form action="../controladores/processar_alteracao_campanha.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id_campanha" value="<?php echo $campanha['id']; ?>">
            
            <label for="titulo">Título da Campanha</label>
            <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($campanha['titulo']); ?>" required>
            
            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao" rows="4" required><?php echo htmlspecialchars($campanha['descricao']); ?></textarea>
            
            <label for="meta">Meta de Arrecadação (R$)</label>
            <input type="number" id="meta" name="meta" step="0.01" value="<?php echo $campanha['meta_arrecadacao']; ?>" required>
            
            
            <div class="itens-secao">
                <h3>Itens Oferecidos</h3>
                <p class="subtitulo-itens">Gerencie os itens específicos que podem ser oferecidos nesta campanha</p>
                
                <div id="lista-itens">
                    
                    <?php if (!empty($itens_campanha)): ?>
                        <?php foreach ($itens_campanha as $index => $item): ?>
                            <div class="item-campanha" data-item-id="<?php echo $item['id']; ?>">
                                <div class="item-header">
                                    <h4>Item <?php echo $index + 1; ?></h4>
                                    <button type="button" class="btn-remover-item" title="Remover item">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <div class="item-campos">
                                    <div class="form-group">
                                        <label>Nome do Item</label>
                                        <input type="text"
                                               name="itens[nome][]"
                                               value="<?php echo htmlspecialchars($item['nome_item']); ?>"
                                               placeholder="Ex: Camiseta personalizada"
                                               required>
                                        <input type="hidden" name="itens[id_item][]" value="<?php echo $item['id']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Descrição do Item</label>
                                        <textarea name="itens[descricao][]"
                                                  rows="3"
                                                  placeholder="Descrição detalhada do item..."
                                                  required><?php echo htmlspecialchars($item['descricao_item']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Valor Fixo (R$)</label>
                                        <input type="number"
                                               name="itens[valor][]"
                                               step="0.01"
                                               value="<?php echo number_format($item['valor_fixo'], 2, '.', ''); ?>"
                                               placeholder="Ex: 25.00"
                                               required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Imagem do Item (opcional)</label>
                                        <?php if ($item['url_imagem']): ?>
                                            <div class="imagem-atual">
                                                <p class="info-imagem">Imagem atual:</p>
                                                <img src="../<?php echo htmlspecialchars($item['url_imagem']); ?>"
                                                     alt="Imagem do item"
                                                     class="imagem-preview-item"
                                                     style="max-width: 200px; max-height: 150px; border: 1px solid #555; border-radius: 4px; padding: 5px; margin-bottom: 10px;">
                                                <div class="opcoes-imagem">
                                                    <label class="checkbox-manter">
                                                        <input type="checkbox" name="itens[manter_imagem][]" value="<?php echo $item['id']; ?>" checked>
                                                        Manter imagem atual
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
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
                                        <?php if ($item['url_imagem']): ?>
                                            <input type="hidden" name="itens[imagem_atual][]" value="<?php echo htmlspecialchars($item['url_imagem']); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="adicionar-item" class="btn-adicionar-item">
                    <i class="fas fa-plus"></i> Adicionar Item
                </button>
            </div>
            
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

    
    <template id="template-item">
        <div class="item-campanha novo-item">
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
                    <input type="hidden" name="itens[id_item][]" value="">
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
    <script src="../scripts/script-itens-campanha-edicao.js" defer></script>
</body>
</html>
