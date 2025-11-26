<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

/*
 Objetivo: editar ou excluir uma campanha existente e gerenciar seus itens (incluindo imagens).
 
 Diagrama mental:
 [Sessão válida?] -> [Escolher ação (editar/excluir)] -> [Validar] -> [Processar itens/imagens] -> [Atualizar/Remover] -> [Feedback]
 
 Dicas:
 - Manter imagem atual: útil quando o item já tem imagem e você não quer trocar.
 - Remoção segura: usamos transação para que tudo seja consistente.
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

/**
 * Processa os itens da campanha (adicionar, editar, remover)
 * @param PDO $pdo Conexão com o banco
 * @param int $id_campanha ID da campanha
 * @param array $post Dados do POST
 * @param array $files Dados dos arquivos
 * @return array Resultado do processamento
 */
function processarItensCampanha($pdo, $id_campanha, $post, $files) {
    try {
        // Inicia transação para garantir integridade dos dados
        $pdo->beginTransaction();
        error_log("Iniciando transação para processar itens da campanha {$id_campanha}");
        
        // Coleta dados dos itens
        $itens_nome = isset($post['itens']['nome']) ? $post['itens']['nome'] : [];
        $itens_descricao = isset($post['itens']['descricao']) ? $post['itens']['descricao'] : [];
        $itens_valor = isset($post['itens']['valor']) ? $post['itens']['valor'] : [];
        $itens_id = isset($post['itens']['id_item']) ? $post['itens']['id_item'] : [];
        $itens_imagem_atual = isset($post['itens']['imagem_atual']) ? $post['itens']['imagem_atual'] : [];
        $itens_manter_imagem = isset($post['itens']['manter_imagem']) ? $post['itens']['manter_imagem'] : [];
        $itens_remover = isset($post['itens']['remover']) ? $post['itens']['remover'] : [];
        
        error_log("Dados dos itens: " . count($itens_nome) . " itens recebidos");
        error_log("Itens para remover: " . implode(',', $itens_remover));
        
        // 1. Remove itens marcados para exclusão (como retirar produtos da prateleira)
        if (!empty($itens_remover)) {
            $placeholders = str_repeat('?,', count($itens_remover) - 1) . '?';
            $sql_remover = "DELETE FROM itens_campanha WHERE id IN ($placeholders) AND id_campanha = ?";
            $stmt_remover = $pdo->prepare($sql_remover);
            
            $params_remover = array_merge($itens_remover, [$id_campanha]);
            $stmt_remover->execute($params_remover);
            
            $itens_removidos = $stmt_remover->rowCount();
            error_log("{$itens_removidos} itens removidos da campanha {$id_campanha}");
        }
        
        // 2. Processa upload de imagens (novas ou mantém as existentes)
        $imagens_processadas = [];
        $erros_upload = [];
        
        if (!empty($itens_nome)) {
            foreach ($itens_nome as $index => $nome) {
                $nome_item = trim($nome) ?: "Item " . ($index + 1);
                $imagem_path = null;
                
                // Se é um item existente e deve manter a imagem atual
                $item_id = $itens_id[$index] ?? null;
                if ($item_id && in_array($item_id, $itens_manter_imagem)) {
                    $imagem_path = $itens_imagem_atual[$index] ?? null;
                    error_log("Mantendo imagem atual para item '{$nome_item}': {$imagem_path}");
                } else {
                    // Processa nova imagem se foi enviada
                    if (isset($files['itens']['name']['imagem'][$index]) &&
                        !empty($files['itens']['name']['imagem'][$index]) &&
                        $files['itens']['error']['imagem'][$index] !== UPLOAD_ERR_NO_FILE) {
                        
                        error_log("Processando nova imagem para item '{$nome_item}' (índice: {$index})");
                        
                        $upload_result = processarUploadImagem(
                            $files['itens']['name']['imagem'][$index],
                            $files['itens']['tmp_name']['imagem'][$index],
                            $files['itens']['size']['imagem'][$index],
                            $files['itens']['error']['imagem'][$index]
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
                        // Nenhuma imagem selecionada para este item
                        error_log("Nenhuma imagem selecionada para item '{$nome_item}'");
                    }
                }
                
                $imagens_processadas[$index] = $imagem_path;
            }
        }
        
        // Se houve erros de upload, retorna erro
        if (!empty($erros_upload)) {
            $pdo->rollBack();
            return [
                'sucesso' => false,
                'erro' => "Erro(s) no upload de imagem:<br>" . implode("<br>", $erros_upload)
            ];
        }
        
        // 3. Valida os dados dos itens (nome, descrição e valor precisam fazer sentido)
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
            $pdo->rollBack();
            return [
                'sucesso' => false,
                'erro' => "Erros nos itens:<br>" . implode("<br>", $erros_itens)
            ];
        }
        
        // 4. Atualiza ou insere os itens (decide entre atualizar o existente ou criar um novo)
        $itens_processados = 0;
        if (!empty($itens_nome)) {
            foreach ($itens_nome as $index => $nome) {
                $nome = trim($nome);
                $descricao = trim($itens_descricao[$index] ?? '');
                $valor = filter_var($itens_valor[$index], FILTER_VALIDATE_FLOAT);
                $item_id = $itens_id[$index] ?? null;
                $imagem = $imagens_processadas[$index] ?? null;
                
                // Apenas processa itens válidos
                if (!empty($nome) && !empty($descricao) && $valor > 0) {
                    if ($item_id) {
                        // Atualiza item existente
                        $sql_atualizar = "UPDATE itens_campanha
                                        SET nome_item = :nome_item,
                                            descricao_item = :descricao_item,
                                            valor_fixo = :valor_fixo,
                                            url_imagem = :url_imagem
                                        WHERE id = :id AND id_campanha = :id_campanha";
                        $stmt_atualizar = $pdo->prepare($sql_atualizar);
                        
                        $resultado = $stmt_atualizar->execute([
                            ':nome_item' => $nome,
                            ':descricao_item' => $descricao,
                            ':valor_fixo' => $valor,
                            ':url_imagem' => $imagem,
                            ':id' => $item_id,
                            ':id_campanha' => $id_campanha
                        ]);
                        
                        if ($resultado) {
                            $itens_processados++;
                            error_log("Item '{$nome}' atualizado (ID: {$item_id})");
                        } else {
                            error_log("Falha ao atualizar item '{$nome}' (ID: {$item_id})");
                        }
                    } else {
                        // Insere novo item
                        $sql_inserir = "INSERT INTO itens_campanha (id_campanha, nome_item, descricao_item, valor_fixo, url_imagem)
                                      VALUES (:id_campanha, :nome_item, :descricao_item, :valor_fixo, :url_imagem)";
                        $stmt_inserir = $pdo->prepare($sql_inserir);
                        
                        $resultado = $stmt_inserir->execute([
                            ':id_campanha' => $id_campanha,
                            ':nome_item' => $nome,
                            ':descricao_item' => $descricao,
                            ':valor_fixo' => $valor,
                            ':url_imagem' => $imagem
                        ]);
                        
                        if ($resultado) {
                            $itens_processados++;
                            error_log("Novo item '{$nome}' inserido");
                        } else {
                            error_log("Falha ao inserir novo item '{$nome}'");
                        }
                    }
                }
            }
        }
        
        error_log("{$itens_processados} itens processados na campanha {$id_campanha}");
        
        // Confirma a transação (tudo certo? então grava!)
        $pdo->commit();
        error_log("Transação de itens confirmada com sucesso");
        
        return [
            'sucesso' => true,
            'itens_processados' => $itens_processados
        ];
        
    } catch (Exception $e) {
        // Rollback em caso de erro (volta ao estado anterior se algo falhar)
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transação revertida devido a erro: " . $e->getMessage());
        }
        
        error_log("Erro ao processar itens: " . $e->getMessage());
        return [
            'sucesso' => false,
            'erro' => "Erro ao processar itens da campanha: " . $e->getMessage()
        ];
    }
}

// Validação de segurança
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../visualizadores/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../visualizadores/minhas-campanhas.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'editar') {
        // Coleta e validação dos dados
        $id_campanha = filter_input(INPUT_POST, 'id_campanha', FILTER_VALIDATE_INT);
        $titulo = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING));
        $descricao = trim(filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING));
        $meta = filter_input(INPUT_POST, 'meta', FILTER_VALIDATE_FLOAT);

        if (!$id_campanha || empty($titulo) || empty($descricao) || !$meta || $meta <= 0) {
            $_SESSION['erro_campanha'] = "Todos os campos são obrigatórios e a meta deve ser um número positivo.";
            header("Location: ../visualizadores/editar-campanha.php?id=" . $id_campanha);
            exit;
        }

        // Verifica se a campanha pertence ao usuário (evita editar o que não é seu)
        $verifica = $pdo->prepare("SELECT id FROM campanhas WHERE id = ? AND id_usuario = ?");
        $verifica->execute([$id_campanha, $id_usuario]);
        
        if (!$verifica->fetch()) {
            $_SESSION['erro_campanha'] = "Campanha não encontrada ou você não tem permissão para editá-la.";
            header("Location: ../visualizadores/minhas-campanhas.php");
            exit;
        }

        // Processa os itens da campanha (inclui uploads e validações)
        $resultado_itens = processarItensCampanha($pdo, $id_campanha, $_POST, $_FILES);
        
        if (!$resultado_itens['sucesso']) {
            $_SESSION['erro_campanha'] = $resultado_itens['erro'];
            header("Location: ../visualizadores/editar-campanha.php?id=" . $id_campanha);
            exit;
        }

        // Atualiza a campanha (título, descrição e meta)
        $sql = "UPDATE campanhas SET titulo = :titulo, descricao = :descricao, meta_arrecadacao = :meta WHERE id = :id AND id_usuario = :id_usuario";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':titulo' => $titulo,
            ':descricao' => $descricao,
            ':meta' => $meta,
            ':id' => $id_campanha,
            ':id_usuario' => $id_usuario
        ]);

        $_SESSION['sucesso_campanha'] = "Campanha e itens atualizados com sucesso!";
        header("Location: ../visualizadores/minhas-campanhas.php");
        exit;

    } elseif ($acao === 'excluir') {
        // Exclusão de campanha (remove a campanha inteira)
        $id_campanha = filter_input(INPUT_POST, 'id_campanha', FILTER_VALIDATE_INT);
        
        if (!$id_campanha) {
            $_SESSION['erro_campanha'] = "ID da campanha inválido.";
            header("Location: ../visualizadores/minhas-campanhas.php");
            exit;
        }

        // Verifica se a campanha pertence ao usuário
        $verifica = $pdo->prepare("SELECT id FROM campanhas WHERE id = ? AND id_usuario = ?");
        $verifica->execute([$id_campanha, $id_usuario]);
        
        if (!$verifica->fetch()) {
            $_SESSION['erro_campanha'] = "Campanha não encontrada ou você não tem permissão para excluí-la.";
            header("Location: ../visualizadores/minhas-campanhas.php");
            exit;
        }

        // Exclui a campanha
        $sql = "DELETE FROM campanhas WHERE id = :id AND id_usuario = :id_usuario";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':id' => $id_campanha,
            ':id_usuario' => $id_usuario
        ]);

        $_SESSION['sucesso_campanha'] = "Campanha excluída com sucesso!";
        header("Location: ../visualizadores/minhas-campanhas.php");
        exit;

    } else {
        header("Location: ../visualizadores/minhas-campanhas.php");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['erro_campanha'] = "Erro ao processar solicitação. Tente novamente.";
    header("Location: ../visualizadores/minhas-campanhas.php");
    exit;
}
?>
