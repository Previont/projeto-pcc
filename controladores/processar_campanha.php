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
 Objetivo: criar uma campanha com itens e, opcionalmente, imagens enviadas pelo usuário.
 
 Termos:
 - "Upload": envio de arquivo do navegador para o servidor.
 - "MIME type": identificação do tipo real do arquivo (ex.: image/png).
 - "Transação": pacote de operações no banco; ou tudo dá certo, ou nada muda.

 Diagrama mental:
 [Sessão válida?] -> [Coletar dados] -> [Validar] -> [Processar uploads] -> [Salvar campanha] -> [Salvar itens] -> [Confirmar transação]

 Dicas e erros comuns:
 - Tamanho de imagem exagerado: limitamos a 5MB para evitar travamentos.
 - Extensão vs tipo real: checamos ambos para impedir arquivos maliciosos.
 - Falha em um item: usamos transação para evitar dados pela metade.
*/

/**
 * Função para processar upload de imagem com validação robusta
 * @param string $nome_arquivo Nome do arquivo
 * @param string $tmp_name Arquivo temporário
 * @param int $tamanho Tamanho do arquivo
 * @param int $erro Código de erro
 * @return array Resultado do processamento
 */
function processarUploadImagem($nome_arquivo, $tmp_name, $tamanho, $erro) {
    // Log para depuração
    error_log("Processando upload: nome={$nome_arquivo}, tmp={$tmp_name}, size={$tamanho}, erro={$erro}");
    
    // Configurações
    $upload_dir = __DIR__ . '/../uploads/itens/';
    $web_upload_dir = 'uploads/itens/';
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    // Verifica se houve erro no upload
    if ($erro !== UPLOAD_ERR_OK) {
        $erro_msg = upload_error_message($erro);
        error_log("Erro de upload: " . $erro_msg);
        return [
            'sucesso' => false,
            'erro' => $erro_msg
        ];
    }
    
    // Verifica se o arquivo foi enviado
    if (empty($nome_arquivo) || empty($tmp_name) || !is_uploaded_file($tmp_name)) {
        error_log("Arquivo não foi enviado corretamente");
        return [
            'sucesso' => false,
            'erro' => 'Nenhum arquivo foi enviado ou arquivo inválido.'
        ];
    }
    
    // Verifica o tamanho do arquivo
    if ($tamanho <= 0 || $tamanho > $max_size) {
        error_log("Tamanho de arquivo inválido: {$tamanho}");
        return [
            'sucesso' => false,
            'erro' => 'Tamanho de arquivo inválido. Máximo: 5MB.'
        ];
    }
    
    // Verifica o tipo MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        error_log("Erro ao abrir finfo");
        return [
            'sucesso' => false,
            'erro' => 'Erro interno do servidor.'
        ];
    }
    
    $mime_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    if (!$mime_type || !in_array($mime_type, $allowed_types)) {
        error_log("Tipo MIME inválido: {$mime_type}");
        return [
            'sucesso' => false,
            'erro' => 'Tipo de arquivo não permitido. Use JPG, PNG ou GIF.'
        ];
    }
    
    // Verifica extensão do arquivo
    $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extensao, $extensoes_permitidas)) {
        error_log("Extensão inválida: {$extensao}");
        return [
            'sucesso' => false,
            'erro' => 'Extensão de arquivo não permitida.'
        ];
    }
    
    // Cria o diretório se não existir e verifica permissões
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Não foi possível criar diretório: {$upload_dir}");
            return [
                'sucesso' => false,
                'erro' => 'Erro ao criar diretório de upload.'
            ];
        }
    }
    
    if (!is_writable($upload_dir)) {
        error_log("Diretório não tem permissão de escrita: {$upload_dir}");
        return [
            'sucesso' => false,
            'erro' => 'Diretório de upload não tem permissão de escrita.'
        ];
    }
    
    // Gera nome único para o arquivo
    $nome_unico = uniqid('item_', true) . '_' . time() . '.' . $extensao;
    $caminho_completo = $upload_dir . $nome_unico;
    
    error_log("Tentando mover arquivo de {$tmp_name} para {$caminho_completo}");
    
    // Move o arquivo para o destino
    if (move_uploaded_file($tmp_name, $caminho_completo)) {
        // Verifica se o arquivo foi realmente criado
        if (file_exists($caminho_completo) && filesize($caminho_completo) > 0) {
            $caminho_relativo = $web_upload_dir . $nome_unico;
            error_log("Upload bem-sucedido: {$caminho_relativo}");
            return [
                'sucesso' => true,
                'caminho' => $caminho_relativo,
                'arquivo_salvo' => $caminho_completo
            ];
        } else {
            error_log("Arquivo não foi criado corretamente após o move_uploaded_file");
            return [
                'sucesso' => false,
                'erro' => 'Falha ao criar o arquivo no servidor.'
            ];
        }
    } else {
        $error = error_get_last();
        error_log("Falha ao mover arquivo: " . ($error['message'] ?? 'Erro desconhecido'));
        return [
            'sucesso' => false,
            'erro' => 'Falha ao salvar o arquivo. Verifique as permissões do servidor.'
        ];
    }
}

/**
 * Converte código de erro do upload em mensagem legível
 * @param int $error_code
 * @return string
 */
function upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Arquivo muito grande.';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload incompleto.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo foi enviado.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Pasta temporária não encontrada.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Falha ao salvar o arquivo.';
        case UPLOAD_ERR_EXTENSION:
            return 'Extensão do arquivo bloqueou o upload.';
        default:
            return 'Erro desconhecido no upload.';
    }
}

// 1. Validação e Segurança
// Apenas usuários logados podem criar uma campanha (pense como a catraca de entrada)
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../visualizadores/login.php");
    exit;
}

// Apenas aceita requisições do tipo POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}

// Log inicial para depuração
error_log("=== INÍCIO PROCESSAMENTO CAMPANHA ===");
error_log("Dados POST: " . json_encode($_POST));
error_log("Dados FILES: " . json_encode($_FILES));

// 2. Coleta e Limpeza dos Dados do Formulário
$id_usuario = $_SESSION['id_usuario'];
// Usa filter_input para maior segurança na coleta de dados (como peneirar areia)
$titulo_campanha = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING));
$descricao_campanha = trim(filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING));
$meta_arrecadacao = filter_input(INPUT_POST, 'meta', FILTER_VALIDATE_FLOAT);

// Coleta dados dos itens (se enviados)
$itens_nome = isset($_POST['itens']['nome']) ? $_POST['itens']['nome'] : [];
$itens_descricao = isset($_POST['itens']['descricao']) ? $_POST['itens']['descricao'] : [];
$itens_valor = isset($_POST['itens']['valor']) ? $_POST['itens']['valor'] : [];

error_log("Itens coletados: " . count($itens_nome) . " itens");

// 3. Processamento de Upload de Imagens (se existirem)
$imagens_processadas = [];
$erros_upload = [];

$imagem_campanha_path = null;
if (isset($_FILES['campanha_imagem']) && !empty($_FILES['campanha_imagem']['name']) && $_FILES['campanha_imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
    $r = processarUploadImagem(
        $_FILES['campanha_imagem']['name'],
        $_FILES['campanha_imagem']['tmp_name'],
        $_FILES['campanha_imagem']['size'],
        $_FILES['campanha_imagem']['error']
    );
    if ($r['sucesso']) {
        $imagem_campanha_path = $r['caminho'];
    } else {
        $erros_upload[] = $r['erro'];
    }
}

if (!empty($itens_nome)) {
    foreach ($itens_nome as $index => $nome) {
        $imagem_path = null;
        $nome_item = trim($nome) ?: "Item " . ($index + 1);
        
        // Verifica se há arquivo para este item
        if (isset($_FILES['itens']['name']['imagem'][$index]) && 
            !empty($_FILES['itens']['name']['imagem'][$index]) &&
            $_FILES['itens']['error']['imagem'][$index] !== UPLOAD_ERR_NO_FILE) {
            
            error_log("Processando imagem para item '{$nome_item}' (índice: {$index})");
            
            $upload_result = processarUploadImagem(
                $_FILES['itens']['name']['imagem'][$index],
                $_FILES['itens']['tmp_name']['imagem'][$index],
                $_FILES['itens']['size']['imagem'][$index],
                $_FILES['itens']['error']['imagem'][$index]
            );
            
            if ($upload_result['sucesso']) {
                $imagem_path = $upload_result['caminho'];
                error_log("Upload bem-sucedido para '{$nome_item}': {$imagem_path}");
            } else {
                $erro_msg = $upload_result['erro'];
                $erros_upload[] = "Item '{$nome_item}': {$erro_msg}";
                error_log("Erro no upload para '{$nome_item}': {$erro_msg}");
            }
        } else {
            error_log("Nenhuma imagem selecionada para item '{$nome_item}'");
        }
        
        $imagens_processadas[$index] = $imagem_path;
    }
}

// Se houve erros de upload, exibe mensagem e retorna
if (!empty($erros_upload)) {
    $_SESSION['erro_campanha'] = "Erro(s) no upload de imagem:<br>" . implode("<br>", $erros_upload);
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}

// 4. Validação dos Dados Coletados
if (empty($titulo_campanha) || empty($descricao_campanha) || $meta_arrecadacao === false || $meta_arrecadacao <= 0) {
    $_SESSION['erro_campanha'] = "Todos os campos obrigatórios devem ser preenchidos e a meta deve ser um número positivo.";
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
        
        if (empty($nome)) {
            $erros_itens[] = "Item " . ($index + 1) . ": Nome é obrigatório.";
        }
        
        if (empty($descricao)) {
            $erros_itens[] = "Item " . ($index + 1) . ": Descrição é obrigatória.";
        }
        
        if ($valor === false || $valor <= 0) {
            $erros_itens[] = "Item " . ($index + 1) . ": Valor deve ser um número positivo.";
        }
    }
}

if (!empty($erros_itens)) {
    $_SESSION['erro_campanha'] = "Erros nos itens:<br>" . implode("<br>", $erros_itens);
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}

// 5. Inserção dos Dados no Banco de Dados
try {
    // Inicia transação para garantir integridade dos dados
    $pdo->beginTransaction();
    error_log("Iniciando transação no banco de dados");
    
    // Insere a campanha (cabeçalho da campanha)
    $sql_campanha = "INSERT INTO campanhas (id_usuario, titulo, descricao, meta_arrecadacao, url_imagem) VALUES (:id_usuario, :titulo, :descricao, :meta, :url_imagem)";
    $declaracao_campanha = $pdo->prepare($sql_campanha);
    
    $declaracao_campanha->execute([
        ':id_usuario' => $id_usuario,
        ':titulo' => $titulo_campanha,
        ':descricao' => $descricao_campanha,
        ':meta' => $meta_arrecadacao,
        ':url_imagem' => $imagem_campanha_path ?: 'https://via.placeholder.com/300'
    ]);
    
    // Obtém o ID da campanha recém-criada
    $id_campanha = $pdo->lastInsertId();
    error_log("Campanha criada com ID: {$id_campanha}");
    
    // Insere os itens da campanha (se existirem) — cada item é um produto com nome, descrição e valor
    $itens_inseridos = 0;
    if (!empty($itens_nome)) {
        $sql_item = "INSERT INTO itens_campanha (id_campanha, nome_item, descricao_item, valor_fixo, url_imagem)
                     VALUES (:id_campanha, :nome_item, :descricao_item, :valor_fixo, :url_imagem)";
        $declaracao_item = $pdo->prepare($sql_item);
        
        foreach ($itens_nome as $index => $nome) {
            $nome = trim($nome);
            $descricao = trim($itens_descricao[$index] ?? '');
            $valor = filter_var($itens_valor[$index], FILTER_VALIDATE_FLOAT);
            $imagem = $imagens_processadas[$index] ?? null;
            
            // Apenas insere itens válidos
            if (!empty($nome) && !empty($descricao) && $valor > 0) {
                $resultado = $declaracao_item->execute([
                    ':id_campanha' => $id_campanha,
                    ':nome_item' => $nome,
                    ':descricao_item' => $descricao,
                    ':valor_fixo' => $valor,
                    ':url_imagem' => $imagem
                ]);
                
                if ($resultado) {
                    $itens_inseridos++;
                    error_log("Item '{$nome}' inserido com imagem: " . ($imagem ?: "sem imagem"));
                } else {
                    error_log("Falha ao inserir item '{$nome}'");
                }
            }
        }
    }
    
    error_log("{$itens_inseridos} itens inseridos na campanha {$id_campanha}");
    
    // Confirma a transação (como dizer: pode registrar tudo!)
    $pdo->commit();
    error_log("Transação confirmada com sucesso");

    // Verificação final - busca os dados salvos para confirmar
    $consulta_verificacao = $pdo->prepare("SELECT ic.* FROM itens_campanha ic WHERE ic.id_campanha = :id_campanha");
    $consulta_verificacao->execute([':id_campanha' => $id_campanha]);
    $itens_salvos = $consulta_verificacao->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Verificação final - Itens salvos: " . count($itens_salvos));
    foreach ($itens_salvos as $item) {
        error_log("Item salvo: {$item['nome_item']} - Imagem: " . ($item['url_imagem'] ?: "sem imagem"));
    }

    // 6. Redirecionamento após o Sucesso
    $_SESSION['sucesso_campanha'] = "Campanha criada com sucesso!";
    header("Location: ../visualizadores/minhas-campanhas.php");
    exit;

} catch (PDOException $e) {
    // Rollback em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transação revertida devido a erro");
    }
    
    // Em um cenário de produção, o erro deveria ser registrado em um log.
    $_SESSION['erro_campanha'] = "Erro ao criar campanha: " . $e->getMessage();
    
    // Log do erro para depuração
    error_log("Erro ao criar campanha: " . $e->getMessage() . " | Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
    
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
} catch (Exception $e) {
    // Rollback em caso de erro geral
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transação revertida devido a erro geral");
    }
    
    $_SESSION['erro_campanha'] = "Erro interno do servidor: " . $e->getMessage();
    error_log("Erro geral: " . $e->getMessage() . " | Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
    
    header("Location: ../visualizadores/criar_campanha.php");
    exit;
}
?>
