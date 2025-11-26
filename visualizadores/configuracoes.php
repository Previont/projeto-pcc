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
/*
 Propósito: página de configurações do usuário (dados pessoais e endereços).
 Funcionalidade: CRUD de endereços com criação automática de tabela se ausente; atualização de dados básicos.
 Relacionados: `scripts/script-configuracoes.js` (UX e CEP), `configurações/utils.php`.
 Entradas: formulários POST para adicionar/editar/excluir endereços; sessão do usuário.
 Saídas: mensagens de sucesso/erro e conteúdo HTML das seções.
 Exemplos: preenchimento automático de endereço via CEP e cache no front.
 Boas práticas: validar entrada, usar prepared statements e feedback visual ao usuário.
 Armadilhas: depender de criação automática de tabela — considerar migrações formais.
*/

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

exigirUsuarioAtivo($pdo);

$id_usuario = $_SESSION['id_usuario'];
$mensagem = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_formulario_endereco'])) {
    try {

        $stmt = $pdo->query("SHOW TABLES LIKE 'enderecos'");
        $tabela_existe = $stmt->rowCount() > 0;
        
        if (!$tabela_existe) {

            try {
                $pdo->exec("
                    CREATE TABLE enderecos (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        id_usuario INT NOT NULL,
                        cep VARCHAR(10) NOT NULL,
                        logradouro VARCHAR(255) NOT NULL,
                        numero VARCHAR(10) NOT NULL,
                        complemento VARCHAR(100) DEFAULT NULL,
                        bairro VARCHAR(100) NOT NULL,
                        cidade VARCHAR(100) NOT NULL,
                        estado VARCHAR(2) NOT NULL,
                        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB
                ");
                error_log("Tabela 'enderecos' criada automaticamente");
                $tabela_existe = true;
            } catch (PDOException $e) {
                $mensagem = "Erro ao criar tabela de endereços: " . $e->getMessage();
                error_log("Erro ao criar tabela enderecos: " . $e->getMessage());
            }
        }
        
        if ($tabela_existe) {
            if ($_POST['acao_formulario_endereco'] === 'adicionar_endereco' || $_POST['acao_formulario_endereco'] === 'editar_endereco') {
                $cep = trim($_POST['cep']);
                $logradouro = trim($_POST['logradouro']);
                $numero = trim($_POST['numero']);
                $bairro = trim($_POST['bairro']);
                $cidade = trim($_POST['cidade']);
                $estado = trim($_POST['estado']);
                $complemento = trim($_POST['complemento']);

                if ($_POST['acao_formulario_endereco'] === 'adicionar_endereco') {
                    $stmt = $pdo->prepare("INSERT INTO enderecos (id_usuario, cep, logradouro, numero, complemento, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_usuario, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado]);
                    $mensagem = "Endereço adicionado com sucesso!";
                } else {
                    $id_endereco = $_POST['id_endereco'];
                    $stmt = $pdo->prepare("UPDATE enderecos SET cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id=? AND id_usuario=?");
                    $stmt->execute([$cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $id_endereco, $id_usuario]);
                    $mensagem = "Endereço atualizado com sucesso!";
                }
            } elseif ($_POST['acao_formulario_endereco'] === 'excluir_endereco') {
                $id_endereco = $_POST['id_endereco'];
                $stmt = $pdo->prepare("DELETE FROM enderecos WHERE id=? AND id_usuario=?");
                $stmt->execute([$id_endereco, $id_usuario]);
                $mensagem = "Endereço removido com sucesso!";
            }
        } else {
            $mensagem = "Não foi possível acessar a tabela de endereços. Verifique a configuração do banco de dados.";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao processar endereço: " . $e->getMessage();
        error_log("Erro no processamento de endereço: " . $e->getMessage());
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_usuario'])) {
    $nome_usuario = trim($_POST['nome_usuario']);
    $email = trim($_POST['email']);

    if (!empty($nome_usuario) && !empty($email)) {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome_usuario = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome_usuario, $email, $id_usuario]);
            $mensagem = "Dados da conta atualizados com sucesso!";
        } catch (PDOException $e) {
            $mensagem = "Erro ao atualizar os dados.";
        }
    }
}


$info_usuario = [];
$enderecos_usuario = [];

try {

    $consulta_usuario = $pdo->prepare("SELECT nome_usuario, email, data_registro FROM usuarios WHERE id = ?");
    $consulta_usuario->execute([$id_usuario]);
    $info_usuario = $consulta_usuario->fetch() ?: [];
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar dados do usuário: " . $e->getMessage();
    error_log("Erro na consulta de usuários: " . $e->getMessage());
}

try {

    $stmt = $pdo->query("SHOW TABLES LIKE 'enderecos'");
    $tabela_enderecos_existe = $stmt->rowCount() > 0;
    
    if (!$tabela_enderecos_existe) {

        try {
            $pdo->exec("
                CREATE TABLE enderecos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario INT NOT NULL,
                    cep VARCHAR(10) NOT NULL,
                    logradouro VARCHAR(255) NOT NULL,
                    numero VARCHAR(10) NOT NULL,
                    complemento VARCHAR(100) DEFAULT NULL,
                    bairro VARCHAR(100) NOT NULL,
                    cidade VARCHAR(100) NOT NULL,
                    estado VARCHAR(2) NOT NULL,
                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
            error_log("Tabela 'enderecos' criada automaticamente na consulta");
            $tabela_enderecos_existe = true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela enderecos na consulta: " . $e->getMessage());
            $tabela_enderecos_existe = false;
        }
    }
    
    if ($tabela_enderecos_existe) {
        $consulta_enderecos = $pdo->prepare("SELECT * FROM enderecos WHERE id_usuario = ?");
        $consulta_enderecos->execute([$id_usuario]);
        $enderecos_usuario = $consulta_enderecos->fetchAll();
    } else {
        $enderecos_usuario = [];
        error_log("Tabela 'enderecos' não existe e não foi possível criá-la");
    }
} catch (PDOException $e) {
    $enderecos_usuario = [];
    $mensagem = "Erro ao carregar endereços: " . $e->getMessage();
    error_log("Erro na consulta de endereços: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações</title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-configuracoes.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="../scripts/utils.js"></script>
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">origoidea</a>
        </div>
        <nav>
            <a href="meu-perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
            <a href="configuracoes.php" class="active"><i class="fas fa-cog"></i> Configurações</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </header>

    <div class="container-configuracoes">
        <aside class="menu-configuracoes">
            <h3>Configurações</h3>
            <ul>
                <li><a href="#" class="active" data-target="conta">Conta</a></li>
                <li><a href="#" data-target="edicao">Edição da conta</a></li>
                
                <li><a href="#" data-target="endereco">Endereço</a></li>
            </ul>
        </aside>

        <main class="conteudo-configuracoes">
            <?php if ($mensagem): ?>
                <div class="mensagem"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php endif; ?>

            
            <section id="conta" class="secao-conteudo active">
                <h2>Visão Geral da Conta</h2>
                <p><strong>Nome de usuário:</strong> <?php echo htmlspecialchars($info_usuario['nome_usuario']); ?></p>
            <p><strong>E-mail:</strong> <?php echo htmlspecialchars($info_usuario['email']); ?></p>
                <p><strong>Membro desde:</strong> <?php echo date("d/m/Y", strtotime($info_usuario['data_registro'])); ?></p>
            </section>

            
            <section id="edicao" class="secao-conteudo">
                <h2>Editar Informações da Conta</h2>
                <form action="configuracoes.php" method="post">
                    <input type="hidden" name="atualizar_usuario" value="1">
                    <div class="form-group">
                        <label for="nome_usuario">Nome de Usuário:</label>
                        <input type="text" id="nome_usuario" name="nome_usuario" value="<?php echo htmlspecialchars($info_usuario['nome_usuario']); ?>">
                    </div>
                    <div class="form-group">
                <label for="email">E-mail:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($info_usuario['email']); ?>">
                    </div>
                    <button type="submit">Salvar Alterações</button>
                </form>
            </section>

            

            
            <section id="endereco" class="secao-conteudo">
                <h2>Seus Endereços</h2>
                <div class="lista-enderecos">
                    <?php foreach ($enderecos_usuario as $endereco): ?>
                        <div class="item-endereco">
                            <p><?php echo htmlspecialchars($endereco['logradouro'] . ', ' . $endereco['numero']); ?></p>
                            <p><?php echo htmlspecialchars($endereco['cidade'] . ' - ' . $endereco['estado']); ?></p>
                            <form action="configuracoes.php" method="post" style="display:inline;">
                                <input type="hidden" name="id_endereco" value="<?php echo $endereco['id']; ?>">
                                <input type="hidden" name="acao_formulario_endereco" value="excluir_endereco">
                                <button type="submit" class="btn-excluir">Excluir</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button id="btn-adicionar-endereco">Adicionar Endereço</button>
                <div id="container-formulario-endereco" style="display:none;">
                    <form action="configuracoes.php" method="post" class="formulario-endereco">
                        <h3 id="titulo-formulario">Adicionar Novo Endereço</h3>
                        <input type="hidden" name="acao_formulario_endereco" id="acao-formulario" value="adicionar_endereco">
                        <input type="hidden" name="id_endereco" id="id-endereco-formulario" value="">
                        <div class="form-group">
                            <label for="cep">CEP:</label>
                            <input type="text" id="cep" name="cep" required>
                        </div>
                        <div class="form-group">
                            <label for="logradouro">Logradouro:</label>
                            <input type="text" id="logradouro" name="logradouro" required>
                        </div>
                        <div class="form-group">
                            <label for="numero">Número:</label>
                            <input type="text" id="numero" name="numero" required>
                        </div>
                        <div class="form-group">
                            <label for="complemento">Complemento:</label>
                            <input type="text" id="complemento" name="complemento">
                        </div>
                        <div class="form-group">
                            <label for="bairro">Bairro:</label>
                            <input type="text" id="bairro" name="bairro" required>
                        </div>
                        <div class="form-group">
                            <label for="cidade">Cidade:</label>
                            <input type="text" id="cidade" name="cidade" required>
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado:</label>
                            <input type="text" id="estado" name="estado" required>
                        </div>
                        <button type="submit">Salvar Endereço</button>
                        <button type="button" id="cancelar-edicao">Cancelar</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script src="../scripts/script-configuracoes.js"></script>
</body>
</html>
