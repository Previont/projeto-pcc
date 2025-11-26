<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) { require_once $config_file; } else { die('Erro: Arquivo de configuração não encontrado em ' . $config_file); }
require_once __DIR__ . '/../configurações/utils.php';
/*
 Propósito: painel administrativo para listar, filtrar e gerenciar usuários.
 Funcionalidade: permite ativar/inativar usuários, editar dados e visualizar status.
 Relacionados: `controladores/campanha-status.php` para padrão de resposta AJAX; `scripts/script-admin.js` para interações.
 Entradas: parâmetros de filtro via GET/POST (nome, e-mail, status, tipo).
 Saídas: tabela com usuários e ações, mensagens de feedback.
 Exemplos: filtrar por "ativos" e alterar status com um clique.
 Boas práticas: validar entradas, usar prepared statements e fornecer acessibilidade (atalhos teclado).
 Armadilhas: paginação/filtros grandes — limitar resultados e carregar incrementalmente se necessário.
*/
require_once __DIR__ . '/../configurações/configuraçõesdeconexão.php';

if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: paginainicial.php?erro=acesso_negado");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$stmt = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = ?");
$stmt->execute([$id_usuario]);
$administrador = $stmt->fetch();

function registrarLog($pdo, $id_usuario, $acao, $detalhes) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_atividades (id_usuario, acao, detalhes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_usuario,
            $acao,
            $detalhes,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['acao'])) {
            switch ($_POST['acao']) {
                case 'editar':
                    $id_editar = (int)($_POST['id_usuario'] ?? 0);
                    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $tipo_usuario = $_POST['tipo_usuario'] ?? 'usuario';
                    $senha_nova = $_POST['senha_nova'] ?? '';
                    
                    if (empty($nome_usuario) || empty($email) || $id_editar <= 0) {
                        throw new Exception('Dados inválidos para edição');
                    }
                    
                    // Verifica se o e-mail já existe (excluindo o usuário atual)
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id_editar]);
                    if ($stmt->fetch()) {
                        throw new Exception('E-mail já está em uso por outro usuário');
                    }
                    
                    // Verifica se o nome de usuário já existe (excluindo o usuário atual)
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? AND id != ?");
                    $stmt->execute([$nome_usuario, $id_editar]);
                    if ($stmt->fetch()) {
                        throw new Exception('Nome de usuário já está em uso');
                    }
                    
                    // Busca dados atuais para log
                    $stmt = $pdo->prepare("SELECT nome_usuario, email, tipo_usuario FROM usuarios WHERE id = ?");
                    $stmt->execute([$id_editar]);
                    $usuario_atual = $stmt->fetch();
                    
                    // Atualiza o usuário
                    if (!empty($senha_nova)) {
                        $senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome_usuario = ?, email = ?, tipo_usuario = ?, senha = ? WHERE id = ?");
                        $stmt->execute([$nome_usuario, $email, $tipo_usuario, $senha_hash, $id_editar]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome_usuario = ?, email = ?, tipo_usuario = ? WHERE id = ?");
                        $stmt->execute([$nome_usuario, $email, $tipo_usuario, $id_editar]);
                    }
                    
                    // Registra log da edição
                    $detalhes_log = "Usuário editado: " . ($usuario_atual['nome_usuario'] ?? 'ID ' . $id_editar);
                    if ($usuario_atual) {
                        $detalhes_log .= " - Nome: {$usuario_atual['nome_usuario']} → {$nome_usuario}";
                        $detalhes_log .= ", Email: {$usuario_atual['email']} → {$email}";
                        $detalhes_log .= ", Tipo: {$usuario_atual['tipo_usuario']} → {$tipo_usuario}";
                        if (!empty($senha_nova)) {
                            $detalhes_log .= ", Senha: alterada";
                        }
                    }
                    registrarLog($pdo, $id_editar, 'usuario_editado', $detalhes_log);
                    
                    $mensagem = 'Usuário atualizado com sucesso!';
                    $tipo_mensagem = 'sucesso';
                    break;
                    
                case 'toggle_status':
                    $id_usuario_toggle = (int)($_POST['id_usuario'] ?? 0);
                    
                    if ($id_usuario_toggle <= 0) {
                        throw new Exception('ID do usuário inválido');
                    }
                    
                    // Não permite desativar o próprio usuário admin
                    if ($id_usuario_toggle == $id_usuario) {
                        throw new Exception('Não é possível desativar sua própria conta');
                    }
                    
                    // Busca o status atual e dados do usuário
                    $stmt = $pdo->prepare("SELECT ativo, nome_usuario FROM usuarios WHERE id = ?");
                    $stmt->execute([$id_usuario_toggle]);
                    $usuario = $stmt->fetch();
                    
                    if (!$usuario) {
                        throw new Exception('Usuário não encontrado');
                    }
                    
                    $novo_status = $usuario['ativo'] ? 0 : 1;
                    $nome_usuario_log = $usuario['nome_usuario'] ?? 'ID ' . $id_usuario_toggle;
                    
                    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");
                    $stmt->execute([$novo_status, $id_usuario_toggle]);
                    
                    // Registra log da ativação/desativação
                    $acao_log = $novo_status ? 'usuario_ativado' : 'usuario_desativado';
                    $detalhes_log = "Usuário {$acao_log}: {$nome_usuario_log} pelo administrador ID {$id_usuario}";
                    registrarLog($pdo, $id_usuario_toggle, $acao_log, $detalhes_log);
                    
                    $status_texto = $novo_status ? 'ativado' : 'desativado';
                    $mensagem = "Usuário $status_texto com sucesso!";
                    $tipo_mensagem = 'sucesso';
                    if (!$novo_status) {
                        error_log("Sessões do usuário {$id_usuario_toggle} serão encerradas pelo middleware");
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// Busca usuários com filtros
$termo_pesquisa = trim($_GET['pesquisa'] ?? '');
$filtro_tipo = $_GET['filtro_tipo'] ?? 'todos';
$filtro_status = $_GET['filtro_status'] ?? 'todos';

try {
    $sql = "SELECT id, nome_usuario, email, tipo_usuario, ativo, data_registro
            FROM usuarios
            WHERE 1=1";
    $params = [];
    
    if (!empty($termo_pesquisa)) {
        $sql .= " AND (nome_usuario LIKE ? OR email LIKE ?)";
        $params[] = "%$termo_pesquisa%";
        $params[] = "%$termo_pesquisa%";
    }
    
    if ($filtro_tipo !== 'todos') {
        $sql .= " AND tipo_usuario = ?";
        $params[] = $filtro_tipo;
    }
    
    if ($filtro_status !== 'todos') {
        $sql .= " AND ativo = ?";
        $params[] = ($filtro_status === 'ativo') ? 1 : 0;
    }
    
    $sql .= " ORDER BY data_registro DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $usuarios = [];
    $mensagem_erro = "Erro ao buscar usuários: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Painel Administrativo</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-admin.css">
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
                <li><a href="admin_gerenciar_usuarios.php" class="ativo">Gerenciar Usuários</a></li>
                <li><a href="admin_gerenciar_campanhas.php">Gerenciar Campanhas</a></li>
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
            </div>
        </header>

        <div class="container">
            <h1>Gerenciar Usuários</h1>

            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($mensagem_erro)): ?>
                <div class="erro"><?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <!-- Filtros e Pesquisa -->
            <div class="filtros">
                <form method="GET" class="form-filtros">
                    <input type="text" name="pesquisa" placeholder="Buscar por nome ou e-mail..." 
                           value="<?php echo htmlspecialchars($termo_pesquisa); ?>" class="campo-pesquisa">
                    
                    <select name="filtro_tipo" class="campo-select">
                        <option value="todos" <?php echo $filtro_tipo === 'todos' ? 'selected' : ''; ?>>Todos os tipos</option>
                        <option value="admin" <?php echo $filtro_tipo === 'admin' ? 'selected' : ''; ?>>Administradores</option>
                        <option value="usuario" <?php echo $filtro_tipo === 'usuario' ? 'selected' : ''; ?>>Usuários</option>
                    </select>
                    
                    <select name="filtro_status" class="campo-select">
                        <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos os status</option>
                        <option value="ativo" <?php echo $filtro_status === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                        <option value="inativo" <?php echo $filtro_status === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                    </select>
                    
                    <button type="submit" class="botao botao-primario">Filtrar</button>
                    <a href="admin_gerenciar_usuarios.php" class="botao botao-secundario">Limpar</a>
                </form>
            </div>

            <!-- Lista de Usuários -->
            <div class="tabela-container">
                <?php if (empty($usuarios)): ?>
                    <p class="sem-dados">Nenhum usuário encontrado.</p>
                <?php else: ?>
                    <table class="tabela-usuarios">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data Registro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['nome_usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <span class="badge tipo-<?php echo $usuario['tipo_usuario']; ?>">
                                            <?php echo ucfirst($usuario['tipo_usuario']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge status-<?php echo $usuario['ativo'] ? 'ativo' : 'inativo'; ?>">
                                            <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_registro'])); ?></td>
                                    <td class="acoes">
                                        <button type="button" class="botao-acao botao-editar" 
                                                onclick="editarUsuario(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nome_usuario']); ?>', '<?php echo htmlspecialchars($usuario['email']); ?>', '<?php echo $usuario['tipo_usuario']; ?>')">
                                            ✏️ Editar
                                        </button>
                                        <?php if ($usuario['id'] != $id_usuario): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="acao" value="toggle_status">
                                                <input type="hidden" name="id_usuario" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="botao-acao <?php echo $usuario['ativo'] ? 'botao-desativar' : 'botao-ativar'; ?>" 
                                                        onclick="return confirm('Tem certeza que deseja <?php echo $usuario['ativo'] ? 'desativar' : 'ativar'; ?> este usuário?')">
                                                    <?php echo $usuario['ativo'] ? '🚫 Desativar' : '✅ Ativar'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Editar Usuário -->
    <div id="modalEditar" class="modal">
        <div class="modal-conteudo">
            <div class="modal-header">
                <h2>Editar Usuário</h2>
                <span class="fechar-modal" onclick="fecharModal('modalEditar')">&times;</span>
            </div>
            <form method="POST" class="form-modal">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" id="editar_id_usuario" name="id_usuario">
                
                <div class="grupo-campo">
                    <label for="editar_nome_usuario">Nome de Usuário:</label>
                    <input type="text" id="editar_nome_usuario" name="nome_usuario" required>
                </div>
                
                <div class="grupo-campo">
                    <label for="editar_email">E-mail:</label>
                    <input type="email" id="editar_email" name="email" required>
                </div>
                
                <div class="grupo-campo">
                    <label for="editar_senha_nova">Nova Senha (deixe em branco para manter a atual):</label>
                    <input type="password" id="editar_senha_nova" name="senha_nova">
                </div>
                
                <div class="grupo-campo">
                    <label for="editar_tipo_usuario">Tipo de Usuário:</label>
                    <select id="editar_tipo_usuario" name="tipo_usuario">
                        <option value="usuario">Usuário</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="acoes-modal">
                    <button type="submit" class="botao botao-primario">Salvar Alterações</button>
                    <button type="button" class="botao botao-secundario" onclick="fecharModal('modalEditar')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function registrarClique(elemento) {
        }
        
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editarUsuario(id, nome, email, tipo) {
            document.getElementById('editar_id_usuario').value = id;
            document.getElementById('editar_nome_usuario').value = nome;
            document.getElementById('editar_email').value = email;
            document.getElementById('editar_tipo_usuario').value = tipo;
            abrirModal('modalEditar');
        }

        // Fecha modal ao clicar fora dele
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
    <script src="../scripts/utils.js" defer></script>
</body>
</html>
<?php if (isset($_GET['runAuthTests']) && $_GET['runAuthTests'] === '1'): ?>
    <?php $tests = __runAuthTests($pdo); ?>
    <div class="mensagem" style="margin-top:10px;">
        <h3>Resultados dos Testes de Autenticação</h3>
        <?php foreach ($tests as $t): ?>
            <div><?php echo ($t['ok'] ? '✔' : '✖') . ' ' . htmlspecialchars($t['nome']); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
