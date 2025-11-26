<?php
session_start();
$config_file = __DIR__ . '/../configurações/configuraçõesdeconexão.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die('Erro: Arquivo de configuração não encontrado em ' . $config_file);
}

// Objetivo: mostrar detalhes de uma campanha, incluindo itens e fluxo de apoio/pagamento.
// Diagrama mental:
// [Carregar campanha] -> [Calcular progresso] -> [Listar itens] -> [Apoiar via Google Pay]
// Dica: sempre validar IDs e tratar imagens com fallback.

$usuario_esta_logado = isset($_SESSION['id_usuario']);
$nome_usuario_logado = '';

if ($usuario_esta_logado) {
    try {
        $consulta_usuario = $pdo->prepare("SELECT nome_usuario FROM usuarios WHERE id = :id");
        $consulta_usuario->execute([':id' => $_SESSION['id_usuario']]);
        $usuario = $consulta_usuario->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            $nome_usuario_logado = $usuario['nome_usuario'];
        }
    } catch (PDOException $e) {

    }
}


$id_campanha = $_GET['id'] ?? null;
if (!$id_campanha) {
    header('Location: minhas-campanhas.php');
    exit;
}

try {

    $atualizar_visitas = $pdo->prepare("UPDATE campanhas SET visualizacoes = visualizacoes + 1 WHERE id = ?");
    $atualizar_visitas->execute([$id_campanha]);


    $sql = "SELECT c.id, c.titulo, c.descricao, c.meta_arrecadacao, c.valor_arrecadado, c.data_criacao, u.nome_usuario
            FROM campanhas c
            JOIN usuarios u ON c.id_usuario = u.id
            WHERE c.id = ?";
    
    $consulta_campanha = $pdo->prepare($sql);
    $consulta_campanha->execute([$id_campanha]);
    $campanha = $consulta_campanha->fetch(PDO::FETCH_ASSOC);


    if (!$campanha) {
        $_SESSION['erro_campanha'] = "Campanha não encontrada.";
        header('Location: minhas-campanhas.php');
        exit;
    }


    $porcentagem_arrecadada = 0;
    if ($campanha['meta_arrecadacao'] > 0) {
        $porcentagem_arrecadada = ($campanha['valor_arrecadado'] / $campanha['meta_arrecadacao']) * 100;
    }


    $sql_itens = "SELECT id, nome_item, descricao_item, valor_fixo, url_imagem FROM itens_campanha WHERE id_campanha = ? ORDER BY id";
    $consulta_itens = $pdo->prepare($sql_itens);
    $consulta_itens->execute([$id_campanha]);
    $itens_campanha = $consulta_itens->fetchAll(PDO::FETCH_ASSOC);
    

} catch (PDOException $e) {
    $_SESSION['erro_campanha'] = "Erro ao buscar detalhes da campanha: " . $e->getMessage();
    header('Location: minhas-campanhas.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($campanha['titulo']); ?></title>
    <link rel="stylesheet" href="../estilizações/estilos-global.css">
    <link rel="stylesheet" href="../estilizações/estilos-header.css">
    <link rel="stylesheet" href="../estilizações/estilos-campanha-detalhes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://pay.google.com/gp/p/js/pay.js" async></script>
    <script src="../scripts/utils.js" defer></script>
    <script src="../scripts/script-menu.js" defer></script>
    
    
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            const imagens = document.querySelectorAll('.item-imagem img');
            
            imagens.forEach((img, index) => {
                

                img.setAttribute('data-loading', 'true');
                
                img.addEventListener('load', () => {
                    img.classList.add('loaded');
                    img.removeAttribute('data-loading');
                });
                
                img.addEventListener('error', (e) => {
                    img.setAttribute('data-error', 'true');
                    img.removeAttribute('data-loading');
                    

                    criarFallbackVisual(img);
                });
            });
        });
        
        function criarFallbackVisual(imgElement) {
            const container = imgElement.parentElement;
            

            const fallbackExistente = container.querySelector('.imagem-fallback');
            if (fallbackExistente) {
                fallbackExistente.remove();
            }
            

            const fallback = document.createElement('div');
            fallback.className = 'imagem-fallback';
            fallback.innerHTML = `
                <i class="fas fa-image"></i>
                <span>Imagem não disponível</span>
            `;
            

            imgElement.style.display = 'none';
            

            container.appendChild(fallback);
        }
        

        function verificarCaminhoImagem(url) {

            if (url.includes('uploads/itens/')) {
                return url;
            }
            

            if (url.startsWith('itens/')) {
                return '../uploads/' + url;
            }
            
            return url;
        }
        
    </script>
    
    <style>
        
        
        .acoes-campanha {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-campanha {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 16px 0 8px;
        }
        .btn-apoio {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            border-radius: var(--radius-lg);
            padding: 14px 24px;
            font-weight: 700;
            font-size: 1rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
            color: #fff;
            box-shadow: var(--shadow-md);
            min-width: 220px;
        }
        .btn-apoio:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(66,165,245,0.35); }
        .btn-apoio:active { transform: translateY(0); filter: brightness(0.95); }
        .btn-apoio:focus { outline: 3px solid rgba(66,165,245,0.6); outline-offset: 2px; }

        .btn-voltar {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
        }
        
        .btn-voltar {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-voltar:hover {
            background-color: #545b62;
            transform: translateY(-2px);
        }
        
        
        @media (max-width: 768px) {
            .cta-campanha { padding: 0 12px; }
            .btn-apoio { width: 100%; max-width: 340px; }
            .acoes-campanha {
                flex-direction: column;
                align-items: center;
            }
            .btn-voltar {
                width: 100%;
                max-width: 300px;
            }
        }
        .loading-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-spinner { width: 60px; height: 60px; border-radius: 50%; border: 6px solid #fff; border-top-color: #42a5f5; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert-success { background: #123f12; color: #cfe8cf; border: 1px solid #1f7a3f; padding: 10px 14px; border-radius: 8px; margin: 10px 0 0; }
        .contribuicao { display: flex; align-items: center; gap: 12px; margin: 16px 0; flex-wrap: wrap; }
        .contribuicao .valor-input { width: 160px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .contribuicao .sugestoes { display: flex; gap: 8px; }
        .contribuicao .btn-sugestao { padding: 8px 10px; border-radius: 6px; border: 1px solid #ddd; background: #f8f9fa; cursor: pointer; }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <a href="paginainicial.php">origoidea</a>
        </div>
        <div class="container-usuario">
            <?php if ($usuario_esta_logado): ?>
                <span class="nome-usuario"><?php echo htmlspecialchars($nome_usuario_logado); ?></span>
            <?php endif; ?>
            <div class="icone-usuario" id="iconeUsuario">
                <i class="fas fa-user"></i>
            </div>
            <div class="menu-usuario" id="menuUsuario">
                <?php if ($usuario_esta_logado): ?>
                    <a href="meu-perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                    <a href="minhas-campanhas.php"><i class="fas fa-heart"></i> Minhas Campanhas</a>
                    <a href="criar_campanha.php"><i class="fas fa-plus"></i> Criar Campanha</a>
                    <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php else: ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Entrar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Container principal dos detalhes -->
    <div class="container-detalhes">
        <div class="cabecalho-detalhes">
            <h1><?php echo htmlspecialchars($campanha['titulo']); ?></h1>
            <p class="criador">Campanha criada por: <strong><?php echo htmlspecialchars($campanha['nome_usuario']); ?></strong></p>
            <p class="data-criacao">Criada em: <?php echo date('d/m/Y H:i', strtotime($campanha['data_criacao'])); ?></p>
        </div>
        <?php if (isset($_GET['paid']) && $_GET['paid'] === '1'): ?>
        <div class="alert-success" role="status" aria-live="polite">Pagamento confirmado. Obrigado pelo apoio!</div>
        <?php endif; ?>
        <div class="cta-campanha">
            <button type="button" class="btn-apoio" id="btnApoiarCampanha" aria-label="Apoiar esta Campanha">
                <i class="fas fa-hand-holding-heart" aria-hidden="true"></i>
                Apoiar esta Campanha
            </button>
            <span class="sr-only">Botão destacado para iniciar apoio à campanha</span>
        </div>
        <div class="contribuicao" aria-label="Escolha o valor de contribuição">
            <input type="number" id="valorContribuicao" class="valor-input" min="1" step="0.01" placeholder="Valor (R$)">
            <div class="sugestoes">
                <button type="button" class="btn-sugestao" data-valor="10">R$ 10,00</button>
                <button type="button" class="btn-sugestao" data-valor="20">R$ 20,00</button>
                <button type="button" class="btn-sugestao" data-valor="50">R$ 50,00</button>
            </div>
        </div>
        
        <div class="detalhes-campanha">
            <h3>Descrição</h3>
            <p><?php echo nl2br(htmlspecialchars($campanha['descricao'])); ?></p>
        </div>

        <!-- Bloco de progresso da arrecadação -->
        <div class="progresso-campanha">
            <h3>Progresso da Arrecadação</h3>
            <div class="progresso-info">
                <span class="arrecadado">R$ <?php echo number_format($campanha['valor_arrecadado'], 2, ',', '.'); ?></span>
                <span class="meta">Meta: R$ <?php echo number_format($campanha['meta_arrecadacao'], 2, ',', '.'); ?></span>
            </div>
            <div class="barra-progresso">
                <div class="progresso-atual" style="width: <?php echo min(round($porcentagem_arrecadada, 2), 100); ?>%;"></div>
            </div>
            <div class="porcentagem"><?php echo round($porcentagem_arrecadada, 1); ?>%</div>
        </div>

        <?php if (!empty($itens_campanha)): ?>
        <div class="itens-campanha">
            <h3>Itens Oferecidos</h3>
            <p class="subtitulo-itens">Contribua especificamente para obter estes itens</p>
            <div class="grid-itens">
                <?php foreach ($itens_campanha as $item): ?>
                    <div class="item-card">
                        <div class="item-imagem">
                            <?php

                            $imagem_src = '';
                            $imagem_alt = htmlspecialchars($item['nome_item']);
                            $fallback_image = '../uploads/imagem-padrao-item.svg';
                            
                            if (!empty($item['url_imagem'])) {

                                $imagem_path = __DIR__ . '/../' . $item['url_imagem'];
                                $imagem_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagem_path);
                                
                                if (file_exists($imagem_path) && is_file($imagem_path)) {
                                    $imagem_src = '../' . $item['url_imagem'];
                                } else {

                                    $imagem_src = $fallback_image;
                                }
                            } else {

                                $imagem_src = $fallback_image;
                            }
                            ?>
                            
                            <img src="<?php echo $imagem_src; ?>"
                                 alt="<?php echo $imagem_alt; ?>"
                                 onerror="this.src='<?php echo $fallback_image; ?>'; this.alt='Imagem não disponível'"
                                 loading="lazy"
                                 style="width: 100%; height: 200px; object-fit: cover;">
                        </div>
                        <div class="item-conteudo">
                            <h4><?php echo htmlspecialchars($item['nome_item']); ?></h4>
                            <p class="item-descricao"><?php echo htmlspecialchars($item['descricao_item']); ?></p>
                            <div class="item-valor">
                                <span class="valor">R$ <?php echo number_format($item['valor_fixo'], 2, ',', '.'); ?></span>
                                <button class="btn-selecionar-item" data-valor="<?php echo $item['valor_fixo']; ?>" data-item-id="<?php echo isset($item['id']) ? (int)$item['id'] : ''; ?>">
                                    Selecionar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="acoes-campanha">
            <a href="paginainicial.php" class="btn-voltar" aria-label="Retornar à Página Inicial" title="Retornar à Página Inicial">Retornar à Página Inicial</a>
        </div>

        
    </div>
    <div id="loadingPayment" class="loading-overlay">
        <div class="loading-spinner" aria-label="Processando"></div>
    </div>
    <script>
        (function(){
            const campanhaId = <?php echo isset($campanha['id']) ? (int)$campanha['id'] : 0; ?>;
            const usuarioId = <?php echo isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0; ?>;
            const btnApoiar = document.getElementById('btnApoiarCampanha');
            const valorInput = document.getElementById('valorContribuicao');
            const sugestoes = document.querySelectorAll('.btn-sugestao');
            const gridItens = document.querySelector('.grid-itens');
            const primeiroItemBtn = document.querySelector('.btn-selecionar-item');
            const itemButtons = document.querySelectorAll('.btn-selecionar-item');
            let paymentsClient = null;
            const overlay = document.getElementById('loadingPayment');
            function setLoading(on){ try { overlay.style.display = on ? 'flex' : 'none'; } catch(_){} }

            function track(action){
                try {
                    const data = new URLSearchParams();
                    data.set('action', action);
                    data.set('campanha_id', String(campanhaId));
                    data.set('user_id', String(usuarioId));
                    data.set('ts', String(Date.now()));
                    navigator.sendBeacon('../controladores/rastrear_evento.php', data);
                } catch(e) {}
            }
            async function validarPagamento(payload){
                try {
                    const resp = await fetch('../controladores/iniciar_pagamento_googlepay.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(payload) });
                    const json = await resp.json();
                    return { ok: resp.ok && !!json.ok, status: resp.status, data: json };
                } catch (e) {
                    return { ok: false, status: 0, data: { erro: 'network_error' } };
                }
            }
            function ensureClient(){
                if (paymentsClient) return paymentsClient;
                if (window.google && google.payments && google.payments.api){ paymentsClient = new google.payments.api.PaymentsClient({ environment: 'TEST' }); }
                return paymentsClient;
            }
            // Inicia fluxo de pagamento via Google Pay
            async function startGooglePay(valor, moeda, descricao, extra){
                const client = ensureClient();
                if (!client){ track('gp_unavailable'); alert('Google Pay indisponível neste dispositivo.'); return; }
                const allowedPaymentMethods = [{ type: 'CARD', parameters: { allowedAuthMethods: ['PAN_ONLY','CRYPTOGRAM_3DS'], allowedCardNetworks: ['MASTERCARD','VISA'] }, tokenizationSpecification: { type: 'PAYMENT_GATEWAY', parameters: { gateway: 'example', gatewayMerchantId: 'exampleGatewayMerchantId' } } }];
                const transactionInfo = { totalPriceStatus: 'FINAL', totalPrice: String(valor), currencyCode: String(moeda) };
                const merchantInfo = { merchantName: 'origoidea Sandbox' };
                try {
                    track('gp_start');
                    await client.loadPaymentData({ apiVersion: 2, apiVersionMinor: 0, allowedPaymentMethods, transactionInfo, merchantInfo });
                    track('gp_success');
                    await fetch('../controladores/processar_pagamento_googlepay.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ campanha_id: String(campanhaId), item_id: String(extra && extra.itemId ? extra.itemId : ''), valor: String(valor), moeda: String(moeda), descricao: String(descricao||''), token: String(extra && extra.token ? extra.token : ''), final_status: 'success' }) });
                    try {
                        const target = String(extra && extra.return_url ? extra.return_url : window.location.href);
                        const head = await fetch(target, { method: 'HEAD' });
                        if (head && head.ok) { window.location.href = target; }
                        else {
                            const u = new URL(window.location.href);
                            u.searchParams.set('paid','1');
                            window.location.href = u.toString();
                        }
                    } catch (_) {
                        const u = new URL(window.location.href);
                        u.searchParams.set('paid','1');
                        window.location.href = u.toString();
                    }
                } catch (e){
                    track('gp_fail');
                    await fetch('../controladores/processar_pagamento_googlepay.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ campanha_id: String(campanhaId), item_id: String(extra && extra.itemId ? extra.itemId : ''), valor: String(valor), moeda: String(moeda), descricao: String(descricao||''), token: String(extra && extra.token ? extra.token : ''), final_status: 'fail' }) });
                    alert('Não foi possível concluir o pagamento.');
                    setLoading(false);
                }
            }
            async function iniciarApoio(){
                setLoading(true);
                let valorEscolhido = 0;
                try { valorEscolhido = parseFloat((valorInput && valorInput.value) ? valorInput.value.replace(',', '.') : '0'); } catch(_) { valorEscolhido = 0; }
                if (!(valorEscolhido >= 1)) { setLoading(false); alert('Informe um valor mínimo de R$ 1,00.'); return; }
                valorEscolhido = Math.round(valorEscolhido * 100) / 100;
                const v = await validarPagamento({ campanha_id: campanhaId, valor: String(valorEscolhido), usar_googlepay: '1' });
                if (!v.ok){
                    setLoading(false);
                    if (v.status === 401){ window.location.href = 'login.php'; return; }
                    if (v.status === 400 && v.data && v.data.erro === 'método de pagamento ausente'){ alert('Adicione um método de pagamento válido.'); return; }
                    if (v.status === 400 && v.data && v.data.erro === 'valor inválido'){ alert('Valor mínimo de contribuição é R$ 1,00.'); return; }
                    const msg = (v.data && v.data.erro === 'campanha inválida') ? 'Campanha indisponível.' : (v.data && v.data.erro) ? 'Falha: ' + v.data.erro : 'Não foi possível iniciar o pagamento.';
                    alert(msg); return;
                }
                await startGooglePay(v.data.valor, v.data.moeda, v.data.descricao, { token: v.data.token, return_url: v.data.return_url });
                setLoading(false);
            }
            if (btnApoiar){
                btnApoiar.addEventListener('click', iniciarApoio);
                btnApoiar.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); iniciarApoio(); } });
            }
            itemButtons.forEach(btn => {
                btn.addEventListener('click', async () => {
                    setLoading(true);
                    const valor = parseFloat(btn.getAttribute('data-valor') || '0');
                    const itemId = btn.getAttribute('data-item-id') || '';
                    const v = await validarPagamento({ campanha_id: campanhaId, item_id: String(itemId), valor: String(valor), usar_googlepay: '1' });
                    if (!v.ok){
                        setLoading(false);
                        if (v.status === 401){ window.location.href = 'login.php'; return; }
                        if (v.status === 404){ alert('Item indisponível.'); return; }
                        if (v.status === 400 && v.data && v.data.erro === 'método de pagamento ausente'){ alert('Adicione um método de pagamento válido.'); return; }
                        const msg = (v.data && v.data.erro) ? 'Falha: ' + v.data.erro : 'Não foi possível iniciar o pagamento.';
                        alert(msg); return;
                    }
                    await startGooglePay(v.data.valor, v.data.moeda, v.data.descricao, { itemId, token: v.data.token, return_url: v.data.return_url });
                    setLoading(false);
                });
            });
            document.addEventListener('DOMContentLoaded', () => {
                const params = new URLSearchParams(window.location.search);
                if (params.get('apoio') === '1'){
                    if (gridItens && primeiroItemBtn){ gridItens.scrollIntoView({ behavior: 'smooth', block: 'start' }); setTimeout(() => { try { primeiroItemBtn.focus(); } catch(_){} }, 400); }
                    else {  }
                }
                sugestoes.forEach(b => { b.addEventListener('click', () => { try { if (valorInput) { valorInput.value = String(parseFloat(b.getAttribute('data-valor')).toFixed(2)); } } catch(_){} }); });
            });
        })();
    </script>
    </body>
    </html>
