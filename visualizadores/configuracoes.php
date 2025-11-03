<?php
session_start();
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$mensagem = '';

// Ações para Endereços com verificação automática de estrutura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_formulario_endereco'])) {
    try {
        // Verifica se a tabela enderecos existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'enderecos'");
        $tabela_existe = $stmt->rowCount() > 0;
        
        if (!$tabela_existe) {
            // Tenta criar a tabela automaticamente
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

// Ações para Métodos de Pagamento com verificação automática de estrutura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_formulario_pagamento'])) {
    try {
        if ($_POST['acao_formulario_pagamento'] === 'adicionar_pagamento') {
            $nome_titular = trim($_POST['nome_titular']);
            $numero_cartao = preg_replace('/\s+/', '', $_POST['numero_cartao']);
            $data_validade = trim($_POST['data_validade']);
            
            $ultimos_digitos = substr($numero_cartao, -4);
            $cartao_hash = password_hash($numero_cartao, PASSWORD_DEFAULT);

            // Verifica se a coluna cartao_hash existe na tabela
            $stmt = $pdo->query("SHOW COLUMNS FROM metodos_pagamento LIKE 'cartao_hash'");
            $coluna_existe = $stmt->rowCount() > 0;

            if ($coluna_existe) {
                // Se a coluna existe, usa a estrutura completa
                $stmt = $pdo->prepare("INSERT INTO metodos_pagamento (id_usuario, nome_titular, ultimos_digitos, data_validade, cartao_hash) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_usuario, $nome_titular, $ultimos_digitos, $data_validade, $cartao_hash]);
                $mensagem = "Método de pagamento adicionado com sucesso!";
            } else {
                // Se a coluna não existe, tenta criá-la automaticamente
                try {
                    $pdo->exec("ALTER TABLE metodos_pagamento ADD COLUMN cartao_hash VARCHAR(255) NOT NULL AFTER data_validade");
                    error_log("Coluna cartao_hash adicionada automaticamente à tabela metodos_pagamento");
                    
                    // Agora insere com a nova estrutura
                    $stmt = $pdo->prepare("INSERT INTO metodos_pagamento (id_usuario, nome_titular, ultimos_digitos, data_validade, cartao_hash) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$id_usuario, $nome_titular, $ultimos_digitos, $data_validade, $cartao_hash]);
                    $mensagem = "Método de pagamento adicionado com sucesso! (Coluna criada automaticamente)";
                } catch (PDOException $e) {
                    // Fallback: insere sem a coluna cartao_hash
                    $stmt = $pdo->prepare("INSERT INTO metodos_pagamento (id_usuario, nome_titular, ultimos_digitos, data_validade) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id_usuario, $nome_titular, $ultimos_digitos, $data_validade]);
                    $mensagem = "Método de pagamento adicionado com sucesso! (Modo compatível)";
                }
            }

        } elseif ($_POST['acao_formulario_pagamento'] === 'excluir_pagamento') {
            $id_pagamento = $_POST['id_pagamento'];
            $stmt = $pdo->prepare("DELETE FROM metodos_pagamento WHERE id=? AND id_usuario=?");
            $stmt->execute([$id_pagamento, $id_usuario]);
            $mensagem = "Método de pagamento removido com sucesso!";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao processar método de pagamento: " . $e->getMessage();
        error_log("Erro no processamento de pagamento: " . $e->getMessage());
    }
}

// Ação para atualizar dados do usuário
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

// Busca de dados com tratamento de erros
$info_usuario = [];
$enderecos_usuario = [];
$pagamentos_usuario = [];

try {
    // Busca dados do usuário
    $consulta_usuario = $pdo->prepare("SELECT nome_usuario, email, data_registro FROM usuarios WHERE id = ?");
    $consulta_usuario->execute([$id_usuario]);
    $info_usuario = $consulta_usuario->fetch() ?: [];
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar dados do usuário: " . $e->getMessage();
    error_log("Erro na consulta de usuários: " . $e->getMessage());
}

try {
    // Busca endereços do usuário com verificação automática de estrutura
    $stmt = $pdo->query("SHOW TABLES LIKE 'enderecos'");
    $tabela_enderecos_existe = $stmt->rowCount() > 0;
    
    if (!$tabela_enderecos_existe) {
        // Tenta criar a tabela automaticamente
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

try {
    // Busca métodos de pagamento do usuário (verifica estrutura da tabela primeiro)
    $stmt = $pdo->query("SHOW COLUMNS FROM metodos_pagamento LIKE 'cartao_hash'");
    $coluna_hash_existe = $stmt->rowCount() > 0;
    
    if ($coluna_hash_existe) {
        $consulta_pagamentos = $pdo->prepare("SELECT id, nome_titular, ultimos_digitos, data_validade, cartao_hash FROM metodos_pagamento WHERE id_usuario = ?");
    } else {
        $consulta_pagamentos = $pdo->prepare("SELECT id, nome_titular, ultimos_digitos, data_validade FROM metodos_pagamento WHERE id_usuario = ?");
    }
    
    $consulta_pagamentos->execute([$id_usuario]);
    $pagamentos_usuario = $consulta_pagamentos->fetchAll();
} catch (PDOException $e) {
    $pagamentos_usuario = [];
    $mensagem = "Erro ao carregar métodos de pagamento: " . $e->getMessage();
    error_log("Erro na consulta de métodos de pagamento: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações</title>
    <link rel="stylesheet" href="../estilizações/estilos-configuracoes.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">Projeto PCC</a>
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
                <li><a href="#" data-target="pagamento">Métodos de pagamento</a></li>
                <li><a href="#" data-target="endereco">Endereço</a></li>
            </ul>
        </aside>

        <main class="conteudo-configuracoes">
            <?php if ($mensagem): ?>
                <div class="mensagem"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php endif; ?>

            <!-- Seção Conta -->
            <section id="conta" class="secao-conteudo active">
                <h2>Visão Geral da Conta</h2>
                <p><strong>Nome de usuário:</strong> <?php echo htmlspecialchars($info_usuario['nome_usuario']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($info_usuario['email']); ?></p>
                <p><strong>Membro desde:</strong> <?php echo date("d/m/Y", strtotime($info_usuario['data_registro'])); ?></p>
            </section>

            <!-- Seção Edição da Conta -->
            <section id="edicao" class="secao-conteudo">
                <h2>Editar Informações da Conta</h2>
                <form action="configuracoes.php" method="post">
                    <input type="hidden" name="atualizar_usuario" value="1">
                    <div class="form-group">
                        <label for="nome_usuario">Nome de Usuário:</label>
                        <input type="text" id="nome_usuario" name="nome_usuario" value="<?php echo htmlspecialchars($info_usuario['nome_usuario']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($info_usuario['email']); ?>">
                    </div>
                    <button type="submit">Salvar Alterações</button>
                </form>
            </section>

            <!-- Seção Métodos de Pagamento -->
            <section id="pagamento" class="secao-conteudo">
                <h2>Métodos de Pagamento</h2>
                <div class="lista-pagamentos">
                    <?php foreach ($pagamentos_usuario as $pagamento): ?>
                        <div class="item-pagamento">
                            <span>Cartão com final <?php echo htmlspecialchars($pagamento['ultimos_digitos']); ?></span>
                            <form action="configuracoes.php" method="post" style="display:inline;">
                                <input type="hidden" name="id_pagamento" value="<?php echo $pagamento['id']; ?>">
                                <input type="hidden" name="acao_formulario_pagamento" value="excluir_pagamento">
                                <button type="submit" class="btn-excluir">Excluir</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button id="btn-adicionar-pagamento">Adicionar Cartão</button>
                <div id="container-formulario-pagamento" style="display:none;">
                    <form action="configuracoes.php" method="post">
                        <input type="hidden" name="acao_formulario_pagamento" value="adicionar_pagamento">
                        <div class="form-group">
                            <label for="nome_titular">Nome do Titular:</label>
                            <input type="text" id="nome_titular" name="nome_titular" required>
                        </div>
                        <div class="form-group">
                            <label for="numero_cartao">Número do Cartão:</label>
                            <input type="text" id="numero_cartao" name="numero_cartao" required>
                        </div>
                        <div class="form-group">
                            <label for="data_validade">Data de Validade (MM/AA):</label>
                            <input type="text" id="data_validade" name="data_validade" placeholder="MM/AA" required>
                        </div>
                        <button type="submit">Salvar Cartão</button>
                        <button type="button" id="cancelar-edicao-pagamento">Cancelar</button>
                    </form>
                </div>
            </section>

            <!-- Seção Endereço -->
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