<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) { require_once $config_file; } else { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'erro'=>'config ausente']); exit; }
if (!isset($_SESSION['id_usuario'])) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'erro'=>'não autorizado']); exit; }
$id_usuario = (int)$_SESSION['id_usuario'];
$campanha_id = isset($_POST['campanha_id']) ? (int)$_POST['campanha_id'] : 0;
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$valor = isset($_POST['valor']) ? (float)$_POST['valor'] : 0.0;
$moeda = 'BRL';
$descricao = '';

/*
 Objetivo: iniciar um fluxo de pagamento, gerando um token e uma URL de retorno.
 
 Termos:
 - "Token": código único para rastrear a tentativa de pagamento.
 - "URL de retorno": para onde voltar após o pagamento (ex.: detalhes da campanha).
*/
try {
    header('Content-Type: application/json');
    $usar_googlepay = isset($_POST['usar_googlepay']) ? ($_POST['usar_googlepay'] === '1') : true;

    // Consulta métodos de pagamento somente quando NÃO usamos Google Pay
    if (!$usar_googlepay) {
        $tem_metodo = false;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM metodos_pagamento WHERE id_usuario = ?');
            $stmt->execute([$id_usuario]);
            $tem_metodo = (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            // Se a tabela não existir ou houver erro, trata como ausência de método
            $tem_metodo = false;
        }
        if (!$tem_metodo) { http_response_code(400); echo json_encode(['ok'=>false,'erro'=>'método de pagamento ausente']); exit; }
    }
    $stmtC = $pdo->prepare('SELECT id, titulo FROM campanhas WHERE id = ?');
    $stmtC->execute([$campanha_id]);
    $c = $stmtC->fetch();
    if (!$c) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'erro'=>'campanha inválida']); exit; }
    if ($item_id > 0) {
        $stmtI = $pdo->prepare('SELECT id, nome_item, valor_fixo FROM itens_campanha WHERE id = ? AND id_campanha = ?');
        $stmtI->execute([$item_id, $campanha_id]);
        $i = $stmtI->fetch();
        if (!$i) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'erro'=>'item inválido']); exit; }
        if (!($valor > 0)) { $valor = (float)$i['valor_fixo']; }
        $descricao = (string)$i['nome_item'];
    } else {
        if (!($valor > 0)) { $valor = 0.00; }
        $descricao = (string)$c['titulo'];
    }
    $min_valor = 1.00;
    if ($item_id === 0 && $valor < $min_valor) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'erro'=>'valor inválido']); exit; }
    $exists = $pdo->query("SHOW TABLES LIKE 'auditoria_pagamentos'");
    if ($exists->rowCount() === 0) {
        $pdo->exec("CREATE TABLE auditoria_pagamentos (id INT AUTO_INCREMENT PRIMARY KEY, id_usuario INT NOT NULL, id_campanha INT NOT NULL, id_item INT NULL, valor DECIMAL(10,2) NOT NULL, moeda VARCHAR(8) NOT NULL, descricao VARCHAR(255) NULL, status VARCHAR(32) NOT NULL, token VARCHAR(64) NULL, return_url VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    }
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($script), '/\\');
    $root = rtrim(dirname($dir), '/\\');
    if ($root === '' || $root === '/' || $root === '\\') { $root = $dir; }
    $base = $root ? $root : '';
    $return_url = $proto . '://' . $host . $base . '/visualizadores/campanha-detalhes.php?id=' . $campanha_id . '&paid=1&source=gpay&cid=' . $campanha_id . '&uid=' . $id_usuario;
    $token = bin2hex(random_bytes(16));
    $ins = $pdo->prepare('INSERT INTO auditoria_pagamentos (id_usuario, id_campanha, id_item, valor, moeda, descricao, status, token, return_url, user_agent) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([$id_usuario, $campanha_id, $item_id > 0 ? $item_id : null, $valor, $moeda, $descricao, 'attempt', $token, $return_url, $_SERVER['HTTP_USER_AGENT'] ?? '']);
    echo json_encode(['ok'=>true,'valor'=>$valor,'moeda'=>$moeda,'descricao'=>$descricao,'token'=>$token,'return_url'=>$return_url,'is_https'=>($proto==='https')]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'erro'=>'falha interna']);
}
