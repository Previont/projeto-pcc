<?php
session_start();
// Inclui o arquivo de configuração da conexão com o banco de dados.
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}
/*
 Propósito: painel para consulta de logs de atividades com filtros.
 Funcionalidade: pesquisa por ação, usuário, termo e intervalo de datas; exibe tabela com resultados.
 Relacionados: `controladores/campanha-status.php` (gera logs), `visualizadores/admin_gerenciar_campanhas.php`.
 Entradas: filtros via GET (`filtro_acao`, `pesquisa`, `data_inicio`, `data_fim`).
 Saídas: tabela HTML com até 500 registros recentes.
 Exemplos: filtrar por "campanha_ativada" para ver alterações de status.
 Boas práticas: limitar result set e usar `LEFT JOIN` para exibir nome do usuário.
 Armadilhas: consultas sem filtros — podem gerar listas grandes; paginar quando necessário.
*/

// Verifica se o usuário está logado e se é um administrador.
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: paginainicial.php");
    exit;
}

// Busca o nome do administrador logado.
$id_usuario = $_SESSION['id_usuario'];
$consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = ?");
$consulta_usuario->execute([$id_usuario]);
$administrador = $consulta_usuario->fetch();

// Parâmetros de filtragem
$filtro_acao = $_GET['filtro_acao'] ?? 'todos';
$termo_pesquisa = trim($_GET['pesquisa'] ?? '');
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

try {
    $sql = "SELECT l.*, u.nome_usuario as nome_usuario_logado 
            FROM log_atividades l 
            LEFT JOIN usuarios u ON l.id_usuario = u.id 
            WHERE 1=1";
    $params = [];
    
    if ($filtro_acao !== 'todos') {
        $sql .= " AND l.acao = ?";
        $params[] = $filtro_acao;
    }
    
    if (!empty($termo_pesquisa)) {
        $sql .= " AND (l.detalhes LIKE ? OR u.nome_usuario LIKE ?)";
        $params[] = "%$termo_pesquisa%";
        $params[] = "%$termo_pesquisa%";
    }
    
    if (!empty($data_inicio)) {
        $sql .= " AND DATE(l.data_criacao) >= ?";
        $params[] = $data_inicio;
    }
    
    if (!empty($data_fim)) {
        $sql .= " AND DATE(l.data_criacao) <= ?";
        $params[] = $data_fim;
    }
    
    $sql .= " ORDER BY l.data_criacao DESC LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $logs = [];
    $mensagem_erro = "Erro ao buscar logs: " . $e->getMessage();
}

// Buscar tipos de ações disponíveis para o filtro
try {
    $stmt = $pdo->query("SELECT DISTINCT acao FROM log_atividades ORDER BY acao");
    $acoes_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $acoes_disponiveis = [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Atividades - Painel Administrativo</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-admin.css">
    <style>
        .filtros-logs {
            background-color: var(--cor-card);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid var(--cor-borda);
        }
        
        .form-filtros-logs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .campo-data {
            padding: 10px;
            border: 1px solid var(--cor-borda);
            border-radius: 6px;
            background-color: var(--cor-fundo);
            color: var(--cor-texto);
            font-size: 14px;
        }
        
        .stats-logs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .cartao-stat {
            background-color: var(--cor-card);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--cor-borda);
            text-align: center;
        }
        
        .cartao-stat h4 {
            margin: 0 0 10px 0;
            color: var(--cor-primaria);
        }
        
        .cartao-stat .numero {
            font-size: 2em;
            font-weight: bold;
            color: var(--cor-texto);
        }
        
        .tabela-logs {
            background-color: var(--cor-card);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--cor-borda);
        }
        
        .tabela-logs table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tabela-logs th,
        .tabela-logs td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--cor-borda);
        }
        
        .tabela-logs th {
            background-color: var(--cor-sidebar);
            color: var(--cor-primaria);
            font-weight: bold;
        }
        
        .tabela-logs tr:hover {
            background-color: rgba(66, 165, 245, 0.1);
        }
        
        .badge-acao {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-usuario-desativado {
            background-color: var(--cor-erro);
            color: white;
        }
        
        .badge-usuario-ativado {
            background-color: var(--cor-sucesso);
            color: white;
        }
        
        .badge-usuario-editado {
            background-color: var(--cor-primaria);
            color: white;
        }
        
        .logs-empty {
            text-align: center;
            padding: 40px;
            color: var(--cor-texto-secundario);
            font-style: italic;
        }
    </style>
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
                <li><a href="admin_logs.php" class="ativo">Logs de Atividades</a></li>
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
            <h1>Logs de Atividades</h1>

            <?php if (isset($mensagem_erro)): ?>
                <div class="erro"><?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <!-- Estatísticas dos Logs -->
            <div class="stats-logs">
                <?php
                try {
                    // Total de logs
                    $stmt = $pdo->query("SELECT COUNT(*) FROM log_atividades");
                    $total_logs = $stmt->fetchColumn();
                    
                    // Logs de desativação
                    $stmt = $pdo->query("SELECT COUNT(*) FROM log_atividades WHERE acao = 'usuario_desativado'");
                    $total_desativacoes = $stmt->fetchColumn();
                    
                    // Logs de ativação
                    $stmt = $pdo->query("SELECT COUNT(*) FROM log_atividades WHERE acao = 'usuario_ativado'");
                    $total_ativacoes = $stmt->fetchColumn();
                    
                    // Logs de edição
                    $stmt = $pdo->query("SELECT COUNT(*) FROM log_atividades WHERE acao = 'usuario_editado'");
                    $total_edicoes = $stmt->fetchColumn();
                    
                    echo "<div class='cartao-stat'>";
                    echo "<h4>Total de Logs</h4>";
                    echo "<div class='numero'>" . number_format($total_logs, 0, ',', '.') . "</div>";
                    echo "</div>";
                    
                    echo "<div class='cartao-stat'>";
                    echo "<h4>Desativações</h4>";
                    echo "<div class='numero'>" . number_format($total_desativacoes, 0, ',', '.') . "</div>";
                    echo "</div>";
                    
                    echo "<div class='cartao-stat'>";
                    echo "<h4>Ativações</h4>";
                    echo "<div class='numero'>" . number_format($total_ativacoes, 0, ',', '.') . "</div>";
                    echo "</div>";
                    
                    echo "<div class='cartao-stat'>";
                    echo "<h4>Edições</h4>";
                    echo "<div class='numero'>" . number_format($total_edicoes, 0, ',', '.') . "</div>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<p>Erro ao buscar estatísticas: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                ?>
            </div>

            <!-- Filtros -->
            <div class="filtros-logs">
                <form method="GET" class="form-filtros-logs">
                    <div>
                        <label for="filtro_acao">Tipo de Ação:</label>
                        <select name="filtro_acao" id="filtro_acao" class="campo-select">
                            <option value="todos" <?php echo $filtro_acao === 'todos' ? 'selected' : ''; ?>>Todas as ações</option>
                            <?php foreach ($acoes_disponiveis as $acao): ?>
                                <option value="<?php echo htmlspecialchars($acao); ?>" <?php echo $filtro_acao === $acao ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $acao)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="pesquisa">Pesquisar:</label>
                        <input type="text" name="pesquisa" id="pesquisa" placeholder="Detalhes ou usuário..." 
                               value="<?php echo htmlspecialchars($termo_pesquisa); ?>" class="campo-pesquisa">
                    </div>
                    
                    <div>
                        <label for="data_inicio">Data Início:</label>
                        <input type="date" name="data_inicio" id="data_inicio" 
                               value="<?php echo htmlspecialchars($data_inicio); ?>" class="campo-data">
                    </div>
                    
                    <div>
                        <label for="data_fim">Data Fim:</label>
                        <input type="date" name="data_fim" id="data_fim" 
                               value="<?php echo htmlspecialchars($data_fim); ?>" class="campo-data">
                    </div>
                    
                    <div>
                        <button type="submit" class="botao botao-primario">Filtrar</button>
                        <a href="admin_logs.php" class="botao botao-secundario">Limpar</a>
                    </div>
                </form>
            </div>

            <!-- Tabela de Logs -->
            <div class="tabela-logs">
                <?php if (empty($logs)): ?>
                    <div class="logs-empty">
                        <p>Nenhum log encontrado com os filtros aplicados.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Ação</th>
                                <th>Detalhes</th>
                                <th>IP Address</th>
                    <th>Agente do Usuário (User-Agent)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['data_criacao'])); ?></td>
                                    <td>
                                        <?php
                                        $classe_badge = 'badge-acao';
                                        if ($log['acao'] === 'usuario_desativado') {
                                            $classe_badge .= ' badge-usuario-desativado';
                                        } elseif ($log['acao'] === 'usuario_ativado') {
                                            $classe_badge .= ' badge-usuario-ativado';
                                        } elseif ($log['acao'] === 'usuario_editado') {
                                            $classe_badge .= ' badge-usuario-editado';
                                        }
                                        ?>
                                        <span class="<?php echo $classe_badge; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $log['acao'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['detalhes']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'Indisponível'); ?></td>
                            <td title="<?php echo htmlspecialchars($log['user_agent'] ?? 'Indisponível'); ?>">
                                        <?php 
                        $user_agent = $log['user_agent'] ?? 'Indisponível';
                                        echo strlen($user_agent) > 50 ? htmlspecialchars(substr($user_agent, 0, 50)) . '...' : htmlspecialchars($user_agent);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../scripts/utils.js" defer></script>
    <script src="../scripts/script-admin.js"></script>
    <script>
        // Função para registrar cliques para fins analíticos
        function registrarClique(elemento) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    'event_category': 'Navegação',
                    'event_label': elemento
                });
            }
        }
        
        // Função para acessibilidade - navegação por teclado
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
