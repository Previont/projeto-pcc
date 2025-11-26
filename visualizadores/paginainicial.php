<?php
session_start();
$config_file = '../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

/*
 Propósito: página inicial com campanhas em destaque, recentes e listagem completa.
 Funcionalidade: renderiza carrossel de recentes e grade de campanhas; customiza menu conforme login/admin.
 Relacionados: `scripts/script-pagina-inicial.js` (carrossel), `estilizações/estilos-pagina-inicial.css`.
 Entradas: nenhuma direta; dados carregados via consultas no backend.
 Saídas: HTML das seções e logs defensivos para deduplicação.
 Exemplos: remover duplicatas no carrossel para evitar cartões repetidos.
 Boas práticas: limitar resultados e tratar deduplicação; lazy loading de imagens.
 Armadilhas: queries pesadas — paginar se a base crescer; checar nulidade de campos.
*/

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

// Busca as 3 campanhas mais visitadas (destaques)
$stmt_mais_visitadas = $pdo->query("SELECT * FROM campanhas WHERE ativa = 1 ORDER BY visualizacoes DESC LIMIT 3");
$campanhas_mais_visitadas = $stmt_mais_visitadas->fetchAll();

// Busca as campanhas mais recentes (carrossel)
$stmt_recentes = $pdo->query("SELECT * FROM campanhas WHERE ativa = 1 ORDER BY data_criacao DESC LIMIT 12");
$campanhas_recentes = $stmt_recentes->fetchAll();
// Deduplicação defensiva por ID para "Campanhas Recentes"
$__orig_count_recentes = is_array($campanhas_recentes) ? count($campanhas_recentes) : 0;
$__ids_vistos_recentes = [];
$campanhas_recentes = array_values(array_filter($campanhas_recentes, function($c) use (&$__ids_vistos_recentes) {
    $id = isset($c['id']) ? (int)$c['id'] : null;
    if (!$id) return false;
    if (isset($__ids_vistos_recentes[$id])) return false;
    $__ids_vistos_recentes[$id] = true;
    return true;
}));
$__final_count_recentes = count($campanhas_recentes);
if ($__final_count_recentes < $__orig_count_recentes) {
    error_log("[paginainicial] Removidas duplicatas em 'Campanhas Recentes': original={$__orig_count_recentes}, final={$__final_count_recentes}");
}

// Busca todas as campanhas (grade)
$stmt_todas = $pdo->query("SELECT * FROM campanhas WHERE ativa = 1 ORDER BY data_criacao DESC");
$todas_as_campanhas = $stmt_todas->fetchAll();

// Deduplicação defensiva por ID para "Todas as Campanhas"
$__orig_count_todas = is_array($todas_as_campanhas) ? count($todas_as_campanhas) : 0;
$__ids_vistos = [];
$todas_as_campanhas = array_values(array_filter($todas_as_campanhas, function($c) use (&$__ids_vistos) {
    $id = isset($c['id']) ? (int)$c['id'] : null;
    if (!$id) return false;
    if (isset($__ids_vistos[$id])) return false;
    $__ids_vistos[$id] = true;
    return true;
}));
$__final_count_todas = count($todas_as_campanhas);
if ($__final_count_todas < $__orig_count_todas) {
    error_log("[paginainicial] Removidas duplicatas em 'Todas as Campanhas': original={$__orig_count_todas}, final={$__final_count_todas}");
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'recentes') {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT id, titulo, valor_arrecadado, url_imagem, data_criacao FROM campanhas WHERE ativa = 1 ORDER BY data_criacao DESC LIMIT 20");
    $dados = $stmt->fetchAll();
    // Deduplica por ID preservando ordenação (primeira ocorrência)
    $ids = [];
    $unicos = [];
    foreach ($dados as $c) {
        $id = isset($c['id']) ? (int)$c['id'] : 0;
        if ($id > 0 && !isset($ids[$id])) {
            $ids[$id] = 1;
            $unicos[] = $c;
        }
    }
    echo json_encode($unicos);
    exit;
}

function imagemCampanhaSrc($url) {
    $default = '../uploads/imagem-padrao-item.png';
    if (!empty($url)) {
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        $fs = __DIR__ . '/../' . ltrim($url, '/');
        if (is_file($fs)) {
            return '../' . ltrim($url, '/');
        }
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>origoidea</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-pagina-inicial.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="<?php echo $is_admin ? 'admin-mode' : ''; ?>">
    <header>
        <div class="logo">
            <a href="paginainicial.php">origoidea</a>
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
            <a href="admin_dashboard.php">Painel de Administração</a>
                    <?php endif; ?>
                    <a href="meu-perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                    <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                    <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                    <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php else: ?>
            <a href="login.php">Entrar</a>
                    <a href="cadastro.php">Cadastre-se</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal: destaques, carrossel de recentes e grade completa -->
    <main>
        <section class="destaque-principal">
            <h1>Destaques</h1>
            <div class="container-destaques">
                <?php foreach ($campanhas_mais_visitadas as $projeto): ?>
                    <div class="card-destaque" data-id="<?php echo (int)$projeto['id']; ?>">
                        <img src="<?php echo htmlspecialchars(imagemCampanhaSrc($projeto['url_imagem'] ?? '')); ?>" alt="Imagem da Campanha" loading="lazy" onerror="this.onerror=null;this.src='../uploads/imagem-padrao-item.png'">
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
                    <div class="card-carrossel" data-id="<?php echo (int)$projeto['id']; ?>">
                        <img src="<?php echo htmlspecialchars(imagemCampanhaSrc($projeto['url_imagem'] ?? '')); ?>" alt="Imagem da Campanha" loading="lazy" onerror="this.onerror=null;this.src='../uploads/imagem-padrao-item.png'">
                        <h3><?php echo htmlspecialchars($projeto['titulo']); ?></h3>
                        <p>Arrecadado: R$ <?php echo number_format($projeto['valor_arrecadado'], 2, ',', '.'); ?></p>
                        <a href="campanha-detalhes.php?id=<?php echo $projeto['id']; ?>" class="botao-detalhes">Ver Mais</a>
                    </div>
                <?php endforeach; ?>
                </div>
                <button class="botao-carrossel prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
                <button class="botao-carrossel next" aria-label="Próximo"><i class="fas fa-chevron-right"></i></button>
            </div>
        </section>

        <section class="todas-as-campanhas">
            <h2>Todas as Campanhas</h2>
            <div class="grade-campanhas">
                <?php foreach ($todas_as_campanhas as $projeto): ?>
                    <div class="card-campanha" data-id="<?php echo (int)$projeto['id']; ?>">
                        <img src="<?php echo htmlspecialchars(imagemCampanhaSrc($projeto['url_imagem'] ?? '')); ?>" alt="Imagem da Campanha" loading="lazy" onerror="this.onerror=null;this.src='../uploads/imagem-padrao-item.png'">
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
            <button class="botao-carrossel prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
            <button class="botao-carrossel next" aria-label="Próximo"><i class="fas fa-chevron-right"></i></button>
        </section>
    </main>

    <footer>
        <p>&copy; 2025 origoidea. Todos os direitos reservados.</p>
    </footer>

    <script src="../scripts/utils.js"></script>
    <script src="../scripts/script-menu.js"></script>
    <script src="../scripts/script-pagina-inicial.js"></script>
</body>
</html>
