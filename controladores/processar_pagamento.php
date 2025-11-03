<?php
// Inicia a sessão
session_start();

// Importa as configurações
require_once '../configurações/mercadopago_config.php';
$config_file = '../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Obtém o conteúdo JSON da requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se os dados necessários foram recebidos
if (!isset($data['token']) || !isset($data['transaction_amount']) || 
    !isset($data['payment_method_id']) || !isset($data['installments'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

try {
    // Carrega a biblioteca do Mercado Pago via Composer autoload
    // Se não estiver usando Composer, você pode baixar o SDK e incluir manualmente
    // require_once '../vendor/autoload.php';
    
    // Como alternativa, vamos usar a API REST diretamente
    $url = 'https://api.mercadopago.com/v1/payments';
    
    // Prepara os dados do pagamento
    $payment_data = [
        'transaction_amount' => (float)$data['transaction_amount'],
        'token' => $data['token'],
        'description' => 'Pagamento via site',
        'installments' => (int)$data['installments'],
        'payment_method_id' => $data['payment_method_id'],
        'payer' => [
            'email' => isset($_SESSION['email']) ? $_SESSION['email'] : 'teste@email.com'
        ]
    ];
    
    // Configura a requisição cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MP_ACCESS_TOKEN
    ]);
    
    // Executa a requisição
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Processa a resposta
    if ($status_code >= 200 && $status_code < 300) {
        $payment_result = json_decode($response, true);
        
        // Registra o pagamento no banco de dados (opcional)
        if (isset($payment_result['id']) && isset($_SESSION['id'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO pagamentos (id_usuario, id_pagamento, valor, status, metodo_pagamento, data_criacao) 
                                      VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $_SESSION['id'],
                    $payment_result['id'],
                    $data['transaction_amount'],
                    $payment_result['status'],
                    $data['payment_method_id']
                ]);
            } catch (PDOException $e) {
                // Apenas log do erro, não interrompe o fluxo
                error_log('Erro ao registrar pagamento: ' . $e->getMessage());
            }
        }
        
        // Retorna o resultado do pagamento
        echo $response;
    } else {
        // Erro na API do Mercado Pago
        http_response_code($status_code);
        echo $response;
    }
} catch (Exception $e) {
    // Erro geral
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar pagamento: ' . $e->getMessage()]);
}
?>