<?php
session_start();

// Inclui o arquivo de configuração da conexão com o banco de dados.
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Arquivo de configuração não encontrado']);
    exit;
}

/*
 Objetivo: expor ações administrativas via AJAX para ativar/desativar campanhas e obter estatísticas.
 
 Termos:
 - "AJAX": comunicação assíncrona entre página e servidor sem recarregar a página.
 - "Badge/Botão": elementos visuais atualizados conforme o status.

 Diagrama mental:
 [Requisição AJAX] -> [Verificar admin] -> [Ação: toggle ou stats] -> [Atualizar banco] -> [Responder JSON]
*/

// Verifica se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Requisição inválida']);
    exit;
}

// Verifica se o usuário está logado e é administrador
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'toggle_status') {
        // Toggle do status de campanha (ligar/desligar)
        $id_campanha = filter_input(INPUT_POST, 'id_campanha', FILTER_VALIDATE_INT);
        
        if (!$id_campanha || $id_campanha <= 0) {
            throw new Exception('ID da campanha inválido');
        }
        
        // Busca o status atual da campanha
        $stmt = $pdo->prepare("SELECT ativa, titulo FROM campanhas WHERE id = ?");
        $stmt->execute([$id_campanha]);
        $campanha = $stmt->fetch();
        
        if (!$campanha) {
            throw new Exception('Campanha não encontrada');
        }
        
        // Calcula o novo status (se estava ativa, vira inativa, e vice-versa)
        $novo_status = $campanha['ativa'] ? 0 : 1;
        
        // Atualiza o status da campanha no banco
        $stmt = $pdo->prepare("UPDATE campanhas SET ativa = ? WHERE id = ?");
        $resultado_update = $stmt->execute([$novo_status, $id_campanha]);
        
        if (!$resultado_update || $stmt->rowCount() === 0) {
            throw new Exception('Falha ao atualizar o status da campanha');
        }
        
        // Registra log da atividade (auditoria de quem fez o quê)
        $acao_log = $novo_status ? 'campanha_ativada' : 'campanha_desativada';
        $detalhes_log = "Campanha {$campanha['titulo']} {$acao_log} pelo administrador ID {$id_usuario}";
        
        registrarLog($pdo, $id_usuario, $acao_log, $detalhes_log);
        
        // Retorna sucesso com os novos dados
        echo json_encode([
            'sucesso' => true,
            'acao' => 'toggle_status',
            'id_campanha' => $id_campanha,
            'novo_status' => $novo_status,
            'status_texto' => $novo_status ? 'Ativa' : 'Inativa',
            'botao_texto' => $novo_status ? '🚫 Desativar' : '✅ Ativar',
            'botao_classe' => $novo_status ? 'botao-desativar' : 'botao-ativar',
            'badge_classe' => $novo_status ? 'status-ativo' : 'status-inativo',
            'mensagem' => "Campanha {$campanha['titulo']} {$acao_log} com sucesso!"
        ]);
        exit;
        
    } elseif ($acao === 'get_stats') {
        // Retorna estatísticas das campanhas (quantas ativas/inativas/total)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM campanhas WHERE ativa = 1");
        $ativas = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM campanhas WHERE ativa = 0");
        $inativas = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM campanhas");
        $total = $stmt->fetchColumn();
        
        echo json_encode([
            'sucesso' => true,
            'acao' => 'get_stats',
            'ativas' => $ativas,
            'inativas' => $inativas,
            'total' => $total
        ]);
        exit;
        
    } else {
        throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    // Log do erro para debugging
    error_log("Erro em campanha-status.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'acao' => $acao
    ]);
    exit;
}

// Função para registrar logs (copiada do admin_gerenciar_usuarios.php)
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
        // Não causa falha na operação principal se o log falhar
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}
?>
