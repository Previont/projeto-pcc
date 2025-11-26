<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
}
/*
 Propósito: endpoint leve para registrar eventos do cliente (telemetria básica).
 Funcionalidade: recebe POST com `action`, `campanha_id`, `user_id`, `ts` e registra em log.
 Relacionados: `visualizadores/campanha-detalhes.php` (função `track` usa este endpoint).
 Entradas: `action` (string), `campanha_id` (int), `user_id` (int), `ts` (timestamp opcional).
 Saídas: JSON `{ ok: true }` e linha no log via `error_log`.
 Exemplos: `action=gp_start` para início do fluxo Google Pay.
 Boas práticas: manter sem estado, retornar sempre JSON e códigos HTTP adequados.
 Armadilhas: aceitar apenas POST e evitar armazenar informações sensíveis.
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$acao = isset($_POST['action']) ? trim($_POST['action']) : '';
$id_campanha = isset($_POST['campanha_id']) ? (int)$_POST['campanha_id'] : 0;
$id_usuario = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$timestamp = isset($_POST['ts']) ? (int)$_POST['ts'] : time();
if ($acao === '') { $acao = 'desconhecido'; }
// Registro didático: eventos de navegação/interação
error_log('[evento_rastreamento] acao='.$acao.' campanha='.$id_campanha.' usuario='.$id_usuario.' timestamp='.$timestamp);
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
