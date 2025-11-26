<?php
/**
 * Tipo de arquivo: Visualizador (frontend PHP)
 * Finalidade: Tela administrativa para listar, filtrar e gerenciar campanhas.
 * Dependências: 'configurações/configuraçõesdeconexão.php' (PDO), estilos e scripts vinculados.
 * Requisitos externos: MySQL via PDO, sessão PHP ativa e usuário com perfil 'admin'.
 * Estrutura geral: Inicialização de sessão e permissão, processamento de ações POST,
 *                  filtros de consulta (GET), consulta e renderização da tabela,
 *                  modais e scripts auxiliares para interação.
 * Marcadores: // ===== <Seção> para delimitar blocos lógicos.
 */
session_start();

// ===== Dependências e configuração =====
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}


// ===== Autorização =====
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: paginainicial.php");
    exit;
}


// ===== Contexto do usuário =====
$id_usuario = $_SESSION['id_usuario'];
$consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = ?");
$consulta_usuario->execute([$id_usuario]);
$administrador = $consulta_usuario->fetch();


// ===== Estado de mensagens =====
$mensagem = '';
$tipo_mensagem = '';

// ===== Processamento de ações (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['acao'])) {
            switch ($_POST['acao']) {
                case 'editar':
                    $id_editar = (int)($_POST['id_campanha'] ?? 0);
                    $titulo = trim($_POST['titulo'] ?? '');
                    $descricao = trim($_POST['descricao'] ?? '');
                    $meta_arrecadacao = (float)($_POST['meta_arrecadacao'] ?? 0);
                    $url_imagem = trim($_POST['url_imagem'] ?? 'https://via.placeholder.com/300');
                    
                    // Validação de obrigatórios e consistência
                    if (empty($titulo) || empty($descricao) || $meta_arrecadacao <= 0 || $id_editar <= 0) {
                        throw new Exception('Dados inválidos para edição');
                    }
                    

                    // Checagem de existência da campanha
                    $stmt = $pdo->prepare("SELECT id FROM campanhas WHERE id = ?");
                    $stmt->execute([$id_editar]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Campanha não encontrada');
                    }
                    

                    // Atualização de atributos
                    $stmt = $pdo->prepare("UPDATE campanhas SET titulo = ?, descricao = ?, url_imagem = ?, meta_arrecadacao = ? WHERE id = ?");
                    $stmt->execute([$titulo, $descricao, $url_imagem, $meta_arrecadacao, $id_editar]);
                    
                    $mensagem = 'Campanha atualizada com sucesso!';
                    $tipo_mensagem = 'sucesso';
                    break;
                    
                case 'toggle_status':
                    $id_campanha_toggle = (int)($_POST['id_campanha'] ?? 0);
                    
                    // Validação de ID
                    if ($id_campanha_toggle <= 0) {
                        throw new Exception('ID da campanha inválido');
                    }
                    

                    // Consulta status atual
                    $stmt = $pdo->prepare("SELECT ativa FROM campanhas WHERE id = ?");
                    $stmt->execute([$id_campanha_toggle]);
                    $campanha = $stmt->fetch();
                    
                    // Garantia de existência
                    if (!$campanha) {
                        throw new Exception('Campanha não encontrada');
                    }
                    
                    // Derivação do novo status
                    $novo_status = $campanha['ativa'] ? 0 : 1;
                    
                    // Persistência do novo status
                    $stmt = $pdo->prepare("UPDATE campanhas SET ativa = ? WHERE id = ?");
                    $stmt->execute([$novo_status, $id_campanha_toggle]);
                    
                    $status_texto = $novo_status ? 'ativada' : 'desativada';
                    $mensagem = "Campanha $status_texto com sucesso!";
                    $tipo_mensagem = 'sucesso';
                    break;
            }
        }
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}


// ===== Filtros (GET) =====
$termo_pesquisa = trim($_GET['pesquisa'] ?? '');
$filtro_status = $_GET['filtro_status'] ?? 'todos';
$filtro_usuario = (int)($_GET['filtro_usuario'] ?? 0);

// ===== Consulta principal =====
try {
    $sql = "SELECT c.id, c.titulo, c.descricao, c.meta_arrecadacao, c.valor_arrecadado, c.url_imagem, c.ativa, c.data_criacao, 
                   u.nome_usuario, u.id as usuario_id
            FROM campanhas c
            INNER JOIN usuarios u ON c.id_usuario = u.id
            WHERE 1=1";
    $params = [];
    
    if (!empty($termo_pesquisa)) {
        $sql .= " AND (c.titulo LIKE ? OR c.descricao LIKE ? OR u.nome_usuario LIKE ?)";
        $params[] = "%$termo_pesquisa%";
        $params[] = "%$termo_pesquisa%";
        $params[] = "%$termo_pesquisa%";
    }
    
    if ($filtro_status === 'ativa') {
        $sql .= " AND c.ativa = 1";
    } elseif ($filtro_status === 'inativa') {
        $sql .= " AND c.ativa = 0";
    }
    
    if ($filtro_usuario > 0) {
        $sql .= " AND c.id_usuario = ?";
        $params[] = $filtro_usuario;
    }
    
    $sql .= " ORDER BY c.data_criacao DESC";
    
    // Execução da consulta montada
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campanhas = $stmt->fetchAll();
    

    // Usuários ativos para filtros
    $stmt = $pdo->query("SELECT id, nome_usuario FROM usuarios WHERE ativo = 1 ORDER BY nome_usuario");
    $usuarios_ativos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $campanhas = [];
    $usuarios_ativos = [];
    $mensagem_erro = "Erro ao buscar campanhas: " . $e->getMessage();
}


// ===== Migração defensiva da coluna 'ativa' =====
try {
    $stmt = $pdo->query("SELECT ativa FROM campanhas LIMIT 1");
} catch (PDOException $e) {

    // Cria coluna com valor padrão caso ausente
    $pdo->exec("ALTER TABLE campanhas ADD COLUMN ativa BOOLEAN DEFAULT TRUE AFTER data_criacao");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Campanhas - Painel Administrativo</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-admin.css">
    <style>
        
        .mensagem-ajax {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-weight: bold;
            animation: slideIn 0.3s ease-out;
        }
        
        .mensagem-ajax.sucesso {
            background-color: var(--cor-sucesso);
            color: white;
            border: 1px solid #2e7d32;
        }
        
        .mensagem-ajax.erro {
            background-color: var(--cor-erro);
            color: white;
            border: 1px solid #c62828;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        
        .botao-acao:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <!-- ===== Barra lateral de navegação ===== -->
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
                <li><a href="admin_gerenciar_campanhas.php" class="ativo">Gerenciar Campanhas</a></li>
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

        <!-- ===== Conteúdo principal ===== -->
        <div class="container">
            <h1>Gerenciar Campanhas</h1>

            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($mensagem_erro)): ?>
                <div class="erro"><?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            
            <!-- ===== Filtros e busca ===== -->
            <div class="filtros">
                <form method="GET" class="form-filtros">
                    <input type="text" name="pesquisa" placeholder="Buscar por título, descrição ou usuário..." 
                           value="<?php echo htmlspecialchars($termo_pesquisa); ?>" class="campo-pesquisa">
                    
                    <select name="filtro_status" class="campo-select">
                        <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos os status</option>
                        <option value="ativa" <?php echo $filtro_status === 'ativa' ? 'selected' : ''; ?>>Ativas</option>
                        <option value="inativa" <?php echo $filtro_status === 'inativa' ? 'selected' : ''; ?>>Inativas</option>
                    </select>
                    
                    <select name="filtro_usuario" class="campo-select">
                        <option value="0" <?php echo $filtro_usuario === 0 ? 'selected' : ''; ?>>Todos os usuários</option>
                        <?php foreach ($usuarios_ativos as $usuario): ?>
                            <option value="<?php echo $usuario['id']; ?>" <?php echo $filtro_usuario === $usuario['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nome_usuario']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="botao botao-primario">Filtrar</button>
                    <a href="admin_gerenciar_campanhas.php" class="botao botao-secundario">Limpar</a>
                </form>
            </div>

            
            <!-- ===== Tabela de campanhas ===== -->
            <div class="tabela-container">
                <?php if (empty($campanhas)): ?>
                    <p class="sem-dados">Nenhuma campanha encontrada.</p>
                <?php else: ?>
                    <table class="tabela-campanhas">
                        <thead>
                            <tr>
                                <th>Imagem</th>
                                <th>Título</th>
                                <th>Usuário</th>
                                <th>Meta</th>
                                <th>Arrecadado</th>
                                <th>Progresso</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campanhas as $campanha): ?>
                                <?php 
                                $progresso = $campanha['meta_arrecadacao'] > 0 ? 
                                    ($campanha['valor_arrecadado'] / $campanha['meta_arrecadacao']) * 100 : 0;
                                $progresso = min(100, max(0, $progresso));
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($campanha['url_imagem']); ?>" 
                                             alt="Imagem da campanha" class="imagem-miniatura">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($campanha['titulo']); ?></strong>
                                        <br><small><?php echo htmlspecialchars(substr($campanha['descricao'], 0, 100)); ?>...</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($campanha['nome_usuario']); ?></td>
                                    <td>R$ <?php echo number_format($campanha['meta_arrecadacao'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($campanha['valor_arrecadado'], 2, ',', '.'); ?></td>
                                    <td>
                                        <div class="progresso-container">
                                            <div class="progresso-barra" style="width: <?php echo $progresso; ?>%"></div>
                                            <span class="progresso-texto"><?php echo number_format($progresso, 1, ',', '.'); ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge status-<?php echo $campanha['ativa'] ? 'ativo' : 'inativo'; ?>">
                                            <?php echo $campanha['ativa'] ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($campanha['data_criacao'])); ?></td>
                                    <td class="acoes">
                                        <button type="button" class="botao-acao botao-editar"
                                                onclick="editarCampanha(<?php echo $campanha['id']; ?>, '<?php echo htmlspecialchars($campanha['titulo'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($campanha['descricao'], ENT_QUOTES); ?>', <?php echo $campanha['meta_arrecadacao']; ?>, '<?php echo htmlspecialchars($campanha['url_imagem']); ?>')">
                                            ✏️ Editar
                                        </button>
                                        <button type="button" class="botao-acao <?php echo $campanha['ativa'] ? 'botao-desativar' : 'botao-ativar'; ?>"
                                                onclick="toggleStatusCampanha(<?php echo $campanha['id']; ?>, '<?php echo htmlspecialchars($campanha['titulo'], ENT_QUOTES); ?>', <?php echo $campanha['ativa'] ? 'true' : 'false'; ?>)"
                                                id="btn-status-<?php echo $campanha['id']; ?>">
                                            <?php echo $campanha['ativa'] ? '🚫 Desativar' : '✅ Ativar'; ?>
                                        </button>
                                        <a href="campanha-detalhes.php?id=<?php echo $campanha['id']; ?>" class="botao-acao" target="_blank">
                                            👁️ Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    
    <div id="modalEditar" class="modal">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2>Editar Campanha</h2>
                <span class="fechar-modal" onclick="fecharModal('modalEditar')">&times;</span>
            </div>
            <form method="POST" class="form-modal">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" id="editar_id_campanha" name="id_campanha">
                
                <div class="grupo-campo">
                    <label for="editar_titulo">Título:</label>
                    <input type="text" id="editar_titulo" name="titulo" required>
                </div>
                
                <div class="grupo-campo">
                    <label for="editar_descricao">Descrição:</label>
                    <textarea id="editar_descricao" name="descricao" rows="4" required></textarea>
                </div>
                
                <div class="grupo-campo">
                    <label for="editar_meta_arrecadacao">Meta de Arrecadação (R$):</label>
                    <input type="number" id="editar_meta_arrecadacao" name="meta_arrecadacao" step="0.01" min="0.01" required>
                </div>
                
                <div class="grupo-campo">
                    <label for="editar_url_imagem">URL da Imagem:</label>
                    <input type="url" id="editar_url_imagem" name="url_imagem">
                </div>
                
                <div class="acoes-modal">
                    <button type="submit" class="botao botao-primario">Salvar Alterações</button>
                    <button type="button" class="botao botao-secundario" onclick="fecharModal('modalEditar')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../scripts/utils.js" defer></script>
    <script src="../scripts/script-admin.js"></script>
    <script>

        /**
         * Função registrarClique
         * Parâmetros: elemento (string)
         * Saída: sem retorno; envia evento analítico se disponível
         */
        function registrarClique(elemento) {

            

            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    'event_category': 'Navegação',
                    'event_label': elemento
                });
            }
            

        }
        

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
        
        /**
         * Função abrirModal
         * Parâmetros: modalId (string)
         * Saída: sem retorno; exibe modal
         */
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        /**
         * Função fecharModal
         * Parâmetros: modalId (string)
         * Saída: sem retorno; oculta modal
         */
        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        /**
         * Função editarCampanha
         * Parâmetros: id (number), titulo (string), descricao (string), meta (number), urlImagem (string)
         * Saída: sem retorno; popula formulário e abre modal
         */
        function editarCampanha(id, titulo, descricao, meta, urlImagem) {
            document.getElementById('editar_id_campanha').value = id;
            document.getElementById('editar_titulo').value = titulo;
            document.getElementById('editar_descricao').value = descricao;
            document.getElementById('editar_meta_arrecadacao').value = meta;
            document.getElementById('editar_url_imagem').value = urlImagem;
            abrirModal('modalEditar');
        }


        /**
         * Função toggleStatusCampanha
         * Parâmetros: id (number), titulo (string), ativoAtual (boolean)
         * Saída: sem retorno; atualiza UI conforme resposta do backend
         */
        async function toggleStatusCampanha(id, titulo, ativoAtual) {
            const acao = ativoAtual ? 'desativar' : 'ativar';
            
            if (!confirm(`Tem certeza que deseja ${acao} a campanha "${titulo}"?`)) {
                return;
            }

            const btn = document.getElementById(`btn-status-${id}`);
            const spinner = btn.innerHTML;
            btn.innerHTML = '⏳ Aguarde...';
            btn.disabled = true;

            try {
                const response = await fetch('../controladores/campanha-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `acao=toggle_status&id_campanha=${id}`
                });

                const data = await response.json();

                if (data.sucesso) {

                    btn.innerHTML = data.botao_texto;
                    btn.className = `botao-acao ${data.botao_classe}`;
                    

                    const linha = btn.closest('tr');
                    const badge = linha.querySelector('.badge');
                    badge.innerHTML = data.status_texto;
                    badge.className = `badge status-${data.novo_status ? 'ativo' : 'inativo'}`;
                    

                    mostrarMensagem(data.mensagem, 'sucesso');
                    

                    atualizarFiltrosAposToggle();
                    try {
                        const payload = { id: id, novo_status: !!data.novo_status, ts: Date.now() };
                        localStorage.setItem('campanha_toggle', JSON.stringify(payload));
                    } catch (e) {
                        // silencioso
                    }
                } else {
                    throw new Error(data.erro || 'Erro desconhecido');
                }
            } catch (error) {
                console.error('Erro:', error);
                btn.innerHTML = spinner;
                btn.disabled = false;
                mostrarMensagem('Erro ao alterar status: ' + error.message, 'erro');
            }
        }


        /**
         * Função atualizarFiltrosAposToggle
         * Parâmetros: nenhum
         * Saída: sem retorno; obtém estatísticas atualizadas
         */
        async function atualizarFiltrosAposToggle() {
            try {
                const response = await fetch('../controladores/campanha-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'acao=get_stats'
                });

                const data = await response.json();
                if (data.sucesso) {

                }
            } catch (error) {
                console.error('Erro ao atualizar estatísticas:', error);
            }
        }


        /**
         * Função mostrarMensagem
         * Parâmetros: texto (string), tipo ('sucesso' | 'erro')
         * Saída: sem retorno; exibe alerta temporário abaixo do título
         */
        function mostrarMensagem(texto, tipo) {

            const msgAnterior = document.querySelector('.mensagem-ajax');
            if (msgAnterior) {
                msgAnterior.remove();
            }


            const div = document.createElement('div');
            div.className = `mensagem-ajax ${tipo}`;
            div.innerHTML = texto;


            const container = document.querySelector('.container');
            const titulo = container.querySelector('h1');
            titulo.parentNode.insertBefore(div, titulo.nextSibling);


            setTimeout(() => {
                div.remove();
            }, 5000);
        }


        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
