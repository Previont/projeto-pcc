// Sistema de configurações otimizado
// Objetivo: gerenciar navegação de seções, formulários de usuário/endereço e busca de CEP.
// Termos:
// - "Debounce": técnica para atrasar uma ação até que o usuário termine de digitar/clicar.
// - "Cache": guardar dados já obtidos para reutilizar sem nova consulta.
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Elementos principais
    const linksMenu = document.querySelectorAll('.menu-configuracoes a');
    const secoesConteudo = document.querySelectorAll('.secao-conteudo');
    const containerFormularioEndereco = document.getElementById('container-formulario-endereco');
    
    // Inicialização otimizada
    initMenuNavegacao();
    initFormularios();
    initCepService();
    initPerformanceMonitoring();

    // Utilitário de reset com suporte a Utils global (fallback local)
    function safeReset(form) {
        if (window.Utils && typeof window.Utils.safeReset === 'function') {
            return window.Utils.safeReset(form);
        }
        if (form && typeof form.reset === 'function') form.reset();
    }
    
    /** Inicializa o sistema de navegação do menu */
    function initMenuNavegacao() {
        if (!linksMenu.length) return;
        
        // Event listener otimizado com delegação
        document.querySelector('.menu-configuracoes').addEventListener('click', function(e) {
            const link = e.target.closest('a[data-target]');
            if (!link) return;
            
            e.preventDefault();
            
            // Feedback visual imediato
            link.style.transform = 'scale(0.98)';
            setTimeout(() => {
                link.style.transform = '';
            }, 150);
            
            // Mudança de seção com animação
            alternarSecao(link);
        });
        
        // Ativa seção baseada no hash da URL
        const hash = window.location.hash.substring(1);
        if (hash) {
            const linkAtivo = document.querySelector(`.menu-configuracoes a[data-target="${hash}"]`);
            if (linkAtivo) {
                setTimeout(() => alternarSecao(linkAtivo), 100);
            }
        }
    }
    
    /** Alterna entre seções do menu com animação */
    function alternarSecao(linkAtivo) {
        const idAlvo = linkAtivo.getAttribute('data-target');
        
        // Remove active de todos os elementos
        linksMenu.forEach(l => l.classList.remove('active'));
        secoesConteudo.forEach(s => {
            s.classList.remove('active');
            s.style.opacity = '0';
        });
        
        // Adiciona active ao elemento selecionado
        linkAtivo.classList.add('active');
        
        // Anima a nova seção
        const secaoAlvo = document.getElementById(idAlvo);
        if (secaoAlvo) {
            secaoAlvo.classList.add('active');
            
            // Trigger reflow para garantir a animação
            secaoAlvo.offsetHeight;
            secaoAlvo.style.opacity = '1';
            
            // Atualiza URL sem recarregar página
            if (history.pushState) {
                history.pushState(null, null, `#${idAlvo}`);
            }
        }
    }
    
    /** Inicializa todos os formulários */
    function initFormularios() {
        initFormularioEndereco();
        initFormularioUsuario();
    }
    
    /** Gerencia formulário de endereço */
    function initFormularioEndereco() {
        const btnAdicionarEndereco = document.getElementById('btn-adicionar-endereco');
        const btnCancelarEdicao = document.getElementById('cancelar-edicao');
        const formularioEndereco = document.querySelector('.formulario-endereco');
        
        if (!btnAdicionarEndereco) return;
        
        // Mostrar formulário
        btnAdicionarEndereco.addEventListener('click', function() {
            mostrarFormulario(containerFormularioEndereco, btnAdicionarEndereco, 'Adicionar Novo Endereço');
        });
        
        // Cancelar edição
        if (btnCancelarEdicao) {
            btnCancelarEdicao.addEventListener('click', function() {
                ocultarFormulario(containerFormularioEndereco, btnAdicionarEndereco);
                safeReset(formularioEndereco);
            });
        }
    }
    
    
    
    /** Gerencia formulário de dados do usuário */
    function initFormularioUsuario() {
        const formUsuario = document.querySelector('form[action="configuracoes.php"]');
        if (!formUsuario) return;
        
        formUsuario.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                mostrarLoading(submitBtn, 'Salvando...');
            }
        });
    }
    
    /** Mostra formulário com animação */
    function mostrarFormulario(container, btnOrigem, titulo = '') {
        if (titulo) {
            const tituloElement = document.getElementById('titulo-formulario');
            if (tituloElement) tituloElement.textContent = titulo;
        }
        
        container.style.display = 'block';
        container.classList.add('formulario-dinamico');
        
        // Animação de entrada
        container.style.opacity = '0';
        container.style.transform = 'translateY(-20px)';
        
        requestAnimationFrame(() => {
            container.style.transition = 'all 0.3s ease';
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        });
        
        btnOrigem.style.display = 'none';
        
        // Focus no primeiro input
        const primeiroInput = container.querySelector('input:not([type="hidden"])');
        if (primeiroInput) {
            setTimeout(() => primeiroInput.focus(), 400);
        }
    }
    
    /** Oculta formulário com animação */
    function ocultarFormulario(container, btnOrigem) {
        container.style.transition = 'all 0.3s ease';
        container.style.opacity = '0';
        container.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            container.style.display = 'none';
            container.classList.remove('formulario-dinamico');
            btnOrigem.style.display = 'inline-block';
        }, 300);
    }
    
    /** Inicializa serviço de CEP com cache */
    function initCepService() {
        const campoCep = document.getElementById('cep');
        if (!campoCep) return;
        
        let cepCache = new Map();
        let timeoutId;
        
        campoCep.addEventListener('blur', function() {
            clearTimeout(timeoutId);
            
            const cep = this.value.replace(/\D/g, '');
            if (cep.length !== 8) return;
            
            // Verifica cache primeiro
            if (cepCache.has(cep)) {
                preencherEndereco(cepCache.get(cep));
                return;
            }
            
            // Debounce para evitar muitas requisições
            timeoutId = setTimeout(() => {
                buscarCep(cep, cepCache);
            }, 500);
        });
    }
    
    /** Busca CEP com feedback visual */
    function buscarCep(cep, cache) {
        const campoCep = document.getElementById('cep');
        const originalBg = campoCep.style.backgroundColor;
        
        // Feedback visual
        campoCep.style.backgroundColor = '#fff3cd';
        campoCep.placeholder = 'Buscando...';
        
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            campoCep.style.backgroundColor = originalBg;
            campoCep.placeholder = 'CEP';
            
            if (!data.erro) {
                cache.set(cep, data);
                preencherEndereco(data);
                mostrarMensagem('Endereço carregado automaticamente', 'sucesso');
            } else {
                mostrarMensagem('CEP não encontrado', 'erro');
            }
        })
        .catch(error => {
            campoCep.style.backgroundColor = originalBg;
            campoCep.placeholder = 'CEP';
            console.error('Erro ao buscar CEP:', error);
            mostrarMensagem('Erro ao buscar CEP. Tente novamente.', 'erro');
        });
    }
    
    /** Preenche campos do endereço */
    function preencherEndereco(data) {
        const campos = {
            'logradouro': data.logradouro,
            'bairro': data.bairro,
            'cidade': data.localidade,
            'estado': data.uf
        };
        
        Object.entries(campos).forEach(([id, valor]) => {
            const campo = document.getElementById(id);
            if (campo && valor) {
                campo.value = valor;
                campo.style.borderColor = '#28a745';
                setTimeout(() => {
                    campo.style.borderColor = '';
                }, 2000);
            }
        });
    }
    
    /** Sistema de mensagens otimizado */
    function mostrarMensagem(texto, tipo = 'info') {
        // Remove mensagens anteriores
        const mensagensAnteriores = document.querySelectorAll('.mensagem');
        mensagensAnteriores.forEach(msg => msg.remove());
        
        const mensagem = document.createElement('div');
        mensagem.className = `mensagem ${tipo}`;
        mensagem.innerHTML = `
            <i class="fas fa-${tipo === 'sucesso' ? 'check-circle' : tipo === 'erro' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${texto}
        `;
        
        const container = document.querySelector('.conteudo-configuracoes');
        container.insertBefore(mensagem, container.firstChild);
        
        // Auto-remove após 5 segundos
        setTimeout(() => {
            if (mensagem.parentNode) {
                mensagem.style.opacity = '0';
                setTimeout(() => mensagem.remove(), 300);
            }
        }, 5000);
    }
    
    /** Sistema de loading para botões */
    function mostrarLoading(botao, texto = 'Carregando...') {
        // Usa utilitário global, se disponível, mantendo o fallback local
        if (window.Utils && typeof window.Utils.mostrarLoading === 'function') {
            return window.Utils.mostrarLoading(botao, texto);
        }
        const textoOriginal = botao.textContent;
        botao.disabled = true;
        botao.innerHTML = `
            <i class="fas fa-spinner fa-spin"></i>
            ${texto}
        `;
        
        return function() {
            botao.disabled = false;
            botao.textContent = textoOriginal;
        };
    }
    
    /** Monitoramento de performance */
    function initPerformanceMonitoring() {
        // Tempo de carregamento da página
        window.addEventListener('load', function() {
            const loadTime = performance.now();
        });
        
        // Monitora tempo de resposta das interações
        document.addEventListener('click', function(e) {
            const start = performance.now();
            
            // Medir resposta de cliques nos menus
            if (e.target.closest('.menu-configuracoes a')) {
                setTimeout(() => {
                    const responseTime = performance.now() - start;
                    if (responseTime > 100) {
                    }
                }, 0);
            }
        });
    }
    
    // Manipulador de erros global
    window.addEventListener('error', function(e) {
        console.error('Erro JavaScript:', e.error);
        mostrarMensagem('Erro interno. Recarregue a página.', 'erro');
    });
    
    // Otimização de performance
    requestIdleCallback(function() {
        // Pre-carregamento de recursos não críticos
        const fontes = [
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
        ];
        
        fontes.forEach(src => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'style';
            link.href = src;
            document.head.appendChild(link);
        });
    });
});

// Função global para compatibilidade
window.Configuracoes = {
    mostrarMensagem: function(texto, tipo) {
        // Função wrapper para acesso externo
        const evento = new CustomEvent('configuracoes:mensagem', {
            detail: { texto, tipo }
        });
        document.dispatchEvent(evento);
    }
};
