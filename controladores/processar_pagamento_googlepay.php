<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (!file_exists($config_file)) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
require_once $config_file;
if (!isset($_SESSION['id_usuario'])) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
$id_usuario = (int)$_SESSION['id_usuario'];
$campanha_id = isset($_POST['campanha_id']) ? (int)$_POST['campanha_id'] : 0;
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$valor = isset($_POST['valor']) ? (float)$_POST['valor'] : 0.0;
$moeda = isset($_POST['moeda']) ? strtoupper(trim($_POST['moeda'])) : 'BRL';
$descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$final_status = isset($_POST['final_status']) ? trim($_POST['final_status']) : 'success';

/*
 Objetivo: registrar o resultado de um pagamento (sucesso, falha, cancelamento) e atualizar valores da campanha.
 
 Termos:
 - "Token": identificador único da tentativa de pagamento (como protocolo).
 - "Auditoria": tabela para guardar o histórico dos pagamentos (registro contábil).

 Erros comuns:
 - Valor não positivo: bloqueamos valores <= 0.
 - Campanha/item inexistente: conferimos antes de registrar.
*/
try {
    $stmtC = $pdo->prepare('SELECT id FROM campanhas WHERE id = ?');
    $stmtC->execute([$campanha_id]);
    if (!$stmtC->fetch()) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
    if ($item_id > 0) {
        $stmtI = $pdo->prepare('SELECT id FROM itens_campanha WHERE id = ? AND id_campanha = ?');
        $stmtI->execute([$item_id, $campanha_id]);
        if (!$stmtI->fetch()) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
    }
    if (!($valor > 0)) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
    $pdo->beginTransaction();
    $exists = $pdo->query("SHOW TABLES LIKE 'auditoria_pagamentos'");
    if ($exists->rowCount() === 0) {
        $pdo->exec("CREATE TABLE auditoria_pagamentos (id INT AUTO_INCREMENT PRIMARY KEY, id_usuario INT NOT NULL, id_campanha INT NOT NULL, id_item INT NULL, valor DECIMAL(10,2) NOT NULL, moeda VARCHAR(8) NOT NULL, descricao VARCHAR(255) NULL, status VARCHAR(32) NOT NULL, token VARCHAR(64) NULL, return_url VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL)");
    }
    if ($final_status === 'success') {
        $upd = $pdo->prepare('UPDATE campanhas SET valor_arrecadado = valor_arrecadado + ? WHERE id = ?');
        $upd->execute([$valor, $campanha_id]);
    }
    if ($token !== '') {
        $up = $pdo->prepare('UPDATE auditoria_pagamentos SET status = ?, updated_at = NOW() WHERE token = ?');
        $up->execute([$final_status, $token]);
    } else {
        $ins = $pdo->prepare('INSERT INTO auditoria_pagamentos (id_usuario, id_campanha, id_item, valor, moeda, descricao, status, user_agent) VALUES (?,?,?,?,?,?,?,?)');
        $ins->execute([$id_usuario, $campanha_id, $item_id > 0 ? $item_id : null, $valor, $moeda, $descricao, $final_status, $_SERVER['HTTP_USER_AGENT'] ?? '']);
    }
    $pdo->commit();
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false]);
}
