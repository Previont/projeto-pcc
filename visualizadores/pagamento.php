<?php
session_start();
$config_file = '../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}
require_once '../configurações/mercadopago_config.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - Mercado Pago</title>
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #009ee3;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background-color: #007eb5;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
    <!-- SDK MercadoPago.js -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body>
    <div class="container">
        <h1>Pagamento</h1>
        
        <div class="form-group">
            <label for="amount">Valor (R$):</label>
            <input type="number" id="amount" min="1" step="0.01" placeholder="Digite o valor" required>
        </div>
        
        <div id="payment-form">
            <div id="cardPaymentBrick_container"></div>
            <button id="pay-button" type="button">Pagar</button>
        </div>
        
        <div id="payment-result" class="result"></div>
    </div>

    <script>
        // Inicializa o SDK do Mercado Pago
        const mp = new MercadoPago('<?php echo MP_PUBLIC_KEY; ?>', {
            locale: 'pt-BR'
        });

        // Função para criar o checkout
        function createCheckout() {
            const amount = document.getElementById('amount').value;
            
            if (!amount || amount <= 0) {
                showError('Por favor, informe um valor válido.');
                return;
            }

            // Cria o brick de pagamento
            const cardPaymentBrickController = mp.bricks().create('cardPayment', 'cardPaymentBrick_container', {
                initialization: {
                    amount: parseFloat(amount)
                },
                customization: {
                    visual: {
                        style: {
                            theme: 'default'
                        }
                    }
                },
                callbacks: {
                    onReady: () => {
                        // Brick pronto para uso
                    },
                    onSubmit: (cardFormData) => {
                        // Callback chamado quando o usuário clica no botão de submissão
                        return new Promise((resolve, reject) => {
                            fetch('../controladores/processar_pagamento.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    token: cardFormData.token,
                                    issuer_id: cardFormData.issuer_id,
                                    payment_method_id: cardFormData.payment_method_id,
                                    transaction_amount: parseFloat(amount),
                                    installments: cardFormData.installments
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'approved') {
                                    showSuccess('Pagamento aprovado! ID: ' + data.id);
                                    resolve();
                                } else {
                                    showError('Pagamento não aprovado: ' + data.status_detail);
                                    reject();
                                }
                            })
                            .catch(error => {
                                showError('Erro ao processar pagamento: ' + error.message);
                                reject();
                            });
                        });
                    },
                    onError: (error) => {
                        showError('Erro: ' + error.message);
                    }
                }
            });

            // Oculta o botão padrão e usa o do Brick
            document.getElementById('pay-button').style.display = 'none';
        }

        // Exibe mensagem de sucesso
        function showSuccess(message) {
            const resultElement = document.getElementById('payment-result');
            resultElement.className = 'result success';
            resultElement.style.display = 'block';
            resultElement.innerHTML = message;
        }

        // Exibe mensagem de erro
        function showError(message) {
            const resultElement = document.getElementById('payment-result');
            resultElement.className = 'result error';
            resultElement.style.display = 'block';
            resultElement.innerHTML = message;
        }

        // Adiciona evento ao botão de pagamento
        document.getElementById('pay-button').addEventListener('click', createCheckout);
    </script>
</body>
</html>