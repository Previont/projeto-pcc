<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'erro'=>'config ausente']);
    exit;
}
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'erro'=>'não autorizado']);
    exit;
}
$id_usuario = (int)$_SESSION['id_usuario'];
$stmt = $pdo->prepare('SELECT tipo_usuario FROM usuarios WHERE id = ?');
$stmt->execute([$id_usuario]);
$row = $stmt->fetch();
$is_admin = $row && isset($row['tipo_usuario']) && $row['tipo_usuario'] === 'admin';
if (!$is_admin) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'erro'=>'restrito']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'erro'=>'método inválido']);
    exit;
}
$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
$valor = isset($_POST['valor']) ? (float)$_POST['valor'] : 0;
$moeda = isset($_POST['moeda']) ? strtoupper(trim($_POST['moeda'])) : '';
$descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
$permitidas = ['success','fail','cancel'];
$erros = [];
if (!in_array($action, $permitidas, true)) { $erros[] = 'ação inválida'; }
if (!($valor > 0)) { $erros[] = 'valor inválido'; }
if (!in_array($moeda, ['BRL','USD'], true)) { $erros[] = 'moeda inválida'; }
if (!empty($erros)) {
    error_log('[googlepay_test] usuario='.$id_usuario.' erro='.implode('|',$erros));
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'erros'=>$erros]);
    exit;
}
/*
 Objetivo: endpoint de teste/controlado para simular callbacks do Google Pay.
 Somente administradores podem acionar para verificar integrações.

 Dica: mantenha valores positivos e moedas aceitas para evitar respostas de erro.
*/
error_log('[googlepay_test] usuario='.$id_usuario.' action='.$action.' valor='.$valor.' moeda='.$moeda.' desc='.preg_replace('/\s+/', ' ', $descricao));
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'action'=>$action,'valor'=>$valor,'moeda'=>$moeda,'descricao'=>$descricao,'ts'=>time()]);
