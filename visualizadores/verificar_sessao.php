<?php
/**
 * Arquivo auxiliar para verificar o estado da sessão
 * Usado pela interface de teste de autenticação
 */

session_start();

$response = [
    'usuario_logado' => false,
    'id_usuario' => null,
    'nome_usuario' => null,
    'tipo_usuario' => null,
    'email' => null,
    'sessao_ativa' => false
];

if (session_status() === PHP_SESSION_ACTIVE) {
    $response['sessao_ativa'] = true;
    
    if (isset($_SESSION['id_usuario'])) {
        $response['usuario_logado'] = true;
        $response['id_usuario'] = $_SESSION['id_usuario'];
        $response['nome_usuario'] = $_SESSION['nome_usuario'] ?? null;
        $response['tipo_usuario'] = $_SESSION['tipo_usuario'] ?? null;
        $response['email'] = $_SESSION['email'] ?? null;
    }
}

header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
?>