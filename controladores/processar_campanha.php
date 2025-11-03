<?php
session_start();
// Inclui o arquivo de configuração da conexão com o banco de dados.
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// 1. Validação e Segurança
// Apenas usuários logados podem criar uma campanha.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../visualizadores/login.php");
    exit;
}

// Apenas aceita requisições do tipo POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}

// 2. Coleta e Limpeza dos Dados do Formulário
$id_usuario = $_SESSION['id_usuario'];
// Usa filter_input para maior segurança na coleta de dados.
$titulo_campanha = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING));
$descricao_campanha = trim(filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING));
$meta_arrecadacao = filter_input(INPUT_POST, 'meta', FILTER_VALIDATE_FLOAT);

// Coleta dados dos itens (se enviados)
$itens_nome = isset($_POST['itens']['nome']) ? $_POST['itens']['nome'] : [];
$itens_descricao = isset($_POST['itens']['descricao']) ? $_POST['itens']['descricao'] : [];
$itens_valor = isset($_POST['itens']['valor']) ? $_POST['itens']['valor'] : [];
$itens_imagem = isset($_POST['itens']['imagem']) ? $_POST['itens']['imagem'] : [];

// 3. Validação dos Dados Coletados
if (empty($titulo_campanha) || empty($descricao_campanha) || $meta_arrecadacao === false || $meta_arrecadacao <= 0) {
    // Armazena uma mensagem de erro na sessão para ser exibida na página do formulário.
    $_SESSION['erro_campanha'] = "Todos os campos são obrigatórios e a meta deve ser um número positivo.";
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}

// Validação dos itens (se existirem)
$erros_itens = [];
if (!empty($itens_nome)) {
    foreach ($itens_nome as $index => $nome) {
        $nome = trim($nome);
        $descricao = trim($itens_descricao[$index] ?? '');
        $valor = filter_var($itens_valor[$index], FILTER_VALIDATE_FLOAT);
        $imagem = trim($itens_imagem[$index] ?? '');
        
        if (empty($nome)) {
            $erros_itens[] = "Item " . ($index + 1) . ": Nome é obrigatório.";
        }
        
        if (empty($descricao)) {
            $erros_itens[] = "Item " . ($index + 1) . ": Descrição é obrigatória.";
        }
        
        if ($valor === false || $valor <= 0) {
            $erros_itens[] = "Item " . ($index + 1) . ": Valor deve ser um número positivo.";
        }
        
        // Validação de URL da imagem (se fornecida)
        if (!empty($imagem) && !filter_var($imagem, FILTER_VALIDATE_URL)) {
            $erros_itens[] = "Item " . ($index + 1) . ": URL da imagem inválida.";
        }
    }
}

if (!empty($erros_itens)) {
    $_SESSION['erro_campanha'] = "Erros nos itens:<br>" . implode("<br>", $erros_itens);
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}

// 4. Inserção dos Dados no Banco de Dados
try {
    // Inicia transação para garantir integridade dos dados
    $pdo->beginTransaction();
    
    // Insere a campanha
    $sql_campanha = "INSERT INTO campanhas (id_usuario, titulo, descricao, meta_arrecadacao) VALUES (:id_usuario, :titulo, :descricao, :meta)";
    $declaracao_campanha = $pdo->prepare($sql_campanha);
    
    $declaracao_campanha->execute([
        ':id_usuario' => $id_usuario,
        ':titulo' => $titulo_campanha,
        ':descricao' => $descricao_campanha,
        ':meta' => $meta_arrecadacao
    ]);
    
    // Obtém o ID da campanha recém-criada
    $id_campanha = $pdo->lastInsertId();
    
    // Insere os itens da campanha (se existirem)
    if (!empty($itens_nome)) {
        $sql_item = "INSERT INTO itens_campanha (id_campanha, nome_item, descricao_item, valor_fixo, url_imagem)
                     VALUES (:id_campanha, :nome_item, :descricao_item, :valor_fixo, :url_imagem)";
        $declaracao_item = $pdo->prepare($sql_item);
        
        foreach ($itens_nome as $index => $nome) {
            $nome = trim($nome);
            $descricao = trim($itens_descricao[$index] ?? '');
            $valor = filter_var($itens_valor[$index], FILTER_VALIDATE_FLOAT);
            $imagem = trim($itens_imagem[$index] ?? '');
            
            // Apenas insere itens válidos
            if (!empty($nome) && !empty($descricao) && $valor > 0) {
                $declaracao_item->execute([
                    ':id_campanha' => $id_campanha,
                    ':nome_item' => $nome,
                    ':descricao_item' => $descricao,
                    ':valor_fixo' => $valor,
                    ':url_imagem' => !empty($imagem) ? $imagem : null
                ]);
            }
        }
    }
    
    // Confirma a transação
    $pdo->commit();

    // 5. Redirecionamento após o Sucesso
    // Redireciona para a página de "minhas campanhas" com uma mensagem de sucesso.
    $_SESSION['sucesso_campanha'] = "Campanha criada com sucesso!";
    header("Location: ../visualizadores/minhas-campanhas.php");
    exit;

} catch (PDOException $e) {
    // Em um cenário de produção, o erro deveria ser registrado em um log.
    // Para o usuário, uma mensagem genérica é mais apropriada.
    $_SESSION['erro_campanha'] = "Erro ao criar campanha: " . $e->getMessage();
    
    // Log do erro para depuração
    error_log("Erro ao criar campanha: " . $e->getMessage() . " | Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
    
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}
?>