<?php
session_start();
/*
 Propósito: área de testes para administradores verificarem o fluxo de Google Pay.
 Funcionalidade: inicializa cliente Google Pay e permite simular callbacks de sucesso/falha/cancelamento.
 Relacionados: `controladores/googlepay_teste_callback.php` (simulação), `visualizadores/campanha-detalhes.php` (fluxo real).
 Entradas: parâmetros do formulário (valor, moeda, descrição).
 Saídas: logs visuais e chamadas ao endpoint de teste.
 Exemplos: criar botão Google Pay de sandbox e testar respostas.
 Boas práticas: restrição a admin, ambiente de teste e mensagens claras.
 Armadilhas: tokens reais e gateway não configurado — usar sandbox e não dados de produção.
*/
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) { require_once $config_file; } else { die('Erro: Arquivo de configuração não encontrado.'); }
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: paginainicial.php');
    exit;
}
$id_usuario = (int)$_SESSION['id_usuario'];
$stmt = $pdo->prepare('SELECT nome_usuario FROM usuarios WHERE id = ?');
$stmt->execute([$id_usuario]);
$administrador = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel • Teste de Pagamentos</title>
  <link rel="stylesheet" href="../estilizações/estilos-global.css">
  <link rel="stylesheet" href="../estilizações/estilos-admin.css">
  <script src="https://pay.google.com/gp/p/js/pay.js" async></script>
  <style>
    .painel-container { padding: 20px; }
    .status { display:flex; gap:10px; align-items:center; margin-bottom:12px; }
    .badge { display:inline-block; padding:6px 10px; border-radius:999px; border:1px solid var(--color-border,#333); font-size:12px; }
    .ok { background:#123f12; color:#cfe8cf; }
    .warn { background:#3f1b12; color:#f4d0c9; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; }
    .card { background: var(--color-surface,#1E1E1E); border:1px solid var(--color-border,#333); border-radius:12px; padding:16px; }
    .form-row { display:flex; gap:12px; margin-bottom:12px; }
    .form-row label { flex:1; display:flex; flex-direction:column; gap:6px; }
    input[type="number"], input[type="text"], select { padding:10px; border:1px solid var(--color-border,#333); border-radius:8px; background:#111; color:#fff; }
    .acoes { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
    .btn { padding:10px 16px; border:1px solid var(--color-border,#333); background:#222; color:#fff; border-radius:8px; cursor:pointer; }
    .btn:hover { background:#2a2a2a; }
    .btn-danger { background:#7a1f1f; }
    .btn-success { background:#1f7a3f; }
    .btn-warning { background:#7a6a1f; }
    .logs { height:260px; overflow:auto; background:#0d0d0d; border:1px solid var(--color-border,#333); border-radius:8px; padding:10px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size:13px; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <aside class="barra-lateral">
    <h2><a href="paginainicial.php" class="titulo-admin-link">Administração</a></h2>
    <nav>
      <ul>
        <li><a href="admin_dashboard.php">Painel</a></li>
        <li><a href="admin_gerenciar_usuarios.php">Gerenciar Usuários</a></li>
        <li><a href="admin_gerenciar_campanhas.php">Gerenciar Campanhas</a></li>
        <li><a href="admin_teste_pagamentos.php" class="active">Teste de Pagamentos</a></li>
        <li><a href="configuracoes.php">Configurações</a></li>
        <li><a href="logout.php">Sair</a></li>
      </ul>
    </nav>
  </aside>
  <main class="conteudo-principal">
    <header class="cabecalho">
      <div class="alternar-menu">&#9776;</div>
      <div class="info-usuario"><span>Olá, <?php echo htmlspecialchars($administrador['nome_usuario'] ?? 'Administrador'); ?></span><a href="meu-perfil.php">Meu Perfil</a></div>
    </header>
    <div class="painel-container">
      <h1>Teste de Pagamentos (Google Pay • Sandbox)</h1>
      <div class="status">
        <span class="badge" id="sdkBadge">SDK: carregando...</span>
        <span class="badge" id="clientBadge">Cliente: aguardando...</span>
        <span class="badge" id="callbackBadge">Callback: pronto</span>
      </div>
      <div class="grid">
        <div class="card">
          <h3>Simular Transação</h3>
          <div class="form-row">
            <label>Valor<input type="number" id="valor" min="1" step="0.01" value="10.00"></label>
            <label>Moeda<select id="moeda"><option value="BRL">BRL</option><option value="USD">USD</option></select></label>
          </div>
          <div class="form-row"><label>Descrição<input type="text" id="descricao" placeholder="Descrição da transação"></label></div>
          <div class="acoes">
            <div id="gpay-btn"></div>
            <button class="btn btn-success" id="btn-sucesso">Simular Sucesso</button>
            <button class="btn btn-danger" id="btn-falha">Simular Falha</button>
            <button class="btn btn-warning" id="btn-cancelar">Simular Cancelamento</button>
          </div>
        </div>
        <div class="card">
          <h3>Logs</h3>
          <pre class="logs" id="logs"></pre>
        </div>
      </div>
      <div class="card" style="margin-top:16px">
        <h3>Instruções</h3>
        <ul>
          <li>Ambiente de testes: não executa transações reais.</li>
          <li>Defina valor, moeda e descrição para o cenário.</li>
          <li>Use o botão Google Pay (sandbox) ou os botões de simulação.</li>
          <li>Verifique respostas nos logs, incluindo erros e callbacks.</li>
        </ul>
      </div>
    </div>
  </main>

  <script>
    let paymentsClient = null;
    function setBadge(elId, text, cls) { const el = document.getElementById(elId); el.textContent = text; el.className = `badge ${cls||''}`; }
    function log(msg, data) { const el = document.getElementById('logs'); const time = new Date().toISOString(); const line = typeof data !== 'undefined' ? `${time} ${msg} ${JSON.stringify(data)}` : `${time} ${msg}`; el.textContent += line + "\n"; el.scrollTop = el.scrollHeight; }
    function valorValido() { const v = parseFloat(document.getElementById('valor').value); return Number.isFinite(v) && v > 0; }
    async function enviarAcao(acao) {
      if (!valorValido()) { log('Entrada inválida: valor'); return; }
      const payload = new URLSearchParams();
      payload.set('action', acao);
      payload.set('valor', String(document.getElementById('valor').value));
      payload.set('moeda', String(document.getElementById('moeda').value));
      payload.set('descricao', String(document.getElementById('descricao').value || ''));
      try {
        const resp = await fetch('../controladores/googlepay_teste_callback.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload });
        const json = await resp.json();
        log('Callback resposta', json);
      } catch (e) { log('Erro no callback', { erro: String(e) }); }
    }
    function initGPay() {
      if (!window.google || !google.payments || !google.payments.api) { setBadge('sdkBadge','SDK: não carregado','warn'); return; }
      setBadge('sdkBadge','SDK: carregado','ok');
      paymentsClient = new google.payments.api.PaymentsClient({ environment: 'TEST' });
      setBadge('clientBadge','Cliente: pronto','ok');
      const button = paymentsClient.createButton({ onClick: onGooglePayButtonClicked });
      const host = document.getElementById('gpay-btn'); host.innerHTML=''; host.appendChild(button);
      log('Google Pay inicializado');
    }
    async function onGooglePayButtonClicked() {
      if (!valorValido()) { log('Entrada inválida: valor'); return; }
      const allowedPaymentMethods = [{ type:'CARD', parameters:{ allowedAuthMethods:['PAN_ONLY','CRYPTOGRAM_3DS'], allowedCardNetworks:['MASTERCARD','VISA'] }, tokenizationSpecification:{ type:'PAYMENT_GATEWAY', parameters:{ gateway:'example', gatewayMerchantId:'exampleGatewayMerchantId' } } }];
      const transactionInfo = { totalPriceStatus:'FINAL', totalPrice:String(document.getElementById('valor').value), currencyCode:String(document.getElementById('moeda').value) };
      const merchantInfo = { merchantName:'origoidea Sandbox' };
      try { const paymentData = await paymentsClient.loadPaymentData({ apiVersion:2, apiVersionMinor:0, allowedPaymentMethods, transactionInfo, merchantInfo }); log('Google Pay sucesso', paymentData); await enviarAcao('success'); } catch (e) { log('Google Pay falha/cancelamento', { erro:String(e) }); await enviarAcao('fail'); }
    }
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(initGPay, 600); document.getElementById('btn-sucesso').addEventListener('click', function(){ enviarAcao('success'); }); document.getElementById('btn-falha').addEventListener('click', function(){ enviarAcao('fail'); }); document.getElementById('btn-cancelar').addEventListener('click', function(){ enviarAcao('cancel'); }); });
  </script>
</body>
</html>
