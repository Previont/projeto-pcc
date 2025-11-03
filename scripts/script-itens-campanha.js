/**
 * Script para gerenciar itens da campanha na página de criação
 * Funcionalidades: adicionar, remover e validar itens
 */

document.addEventListener('DOMContentLoaded', function() {
    const adicionarItemBtn = document.getElementById('adicionar-item');
    const listaItens = document.getElementById('lista-itens');
    const template = document.getElementById('template-item');
    
    // Adiciona o primeiro item automaticamente
    adicionarItem();
    
    // Event listener para adicionar novo item
    adicionarItemBtn.addEventListener('click', adicionarItem);
    
    // Event listener para remover itens (usando event delegation)
    listaItens.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remover-item')) {
            removerItem(e.target.closest('.item-campanha'));
        }
    });
    
    // Event listeners para validação em tempo real
    listaItens.addEventListener('input', function(e) {
        if (e.target.type === 'number' && e.target.name.includes('itens[valor]')) {
            validarValorItem(e.target);
        }
    });
    
    /**
     * Adiciona um novo item à lista
     */
    function adicionarItem() {
        if (!template || !listaItens) return;
        
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('.item-campanha');
        const botaoRemover = clone.querySelector('.btn-remover-item');
        
        // Personaliza o título do item com número sequencial
        const numeroItem = listaItens.children.length + 1;
        const titulo = clone.querySelector('h4');
        titulo.textContent = `Item ${numeroItem}`;
        
        // Adiciona o item à lista
        listaItens.appendChild(clone);
        
        // Foca no primeiro campo do novo item
        const primeiroInput = item.querySelector('input[type="text"]');
        if (primeiroInput) {
            setTimeout(() => primeiroInput.focus(), 100);
        }
        
        // Animação de entrada
        item.style.opacity = '0';
        item.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 50);
        
        // Remove a transição após a animação
        setTimeout(() => {
            item.style.transition = '';
        }, 350);
    }
    
    /**
     * Remove um item da lista
     * @param {Element} item - Elemento do item a ser removido
     */
    function removerItem(item) {
        if (!item) return;
        
        // Confirmação antes de remover (apenas se não for o último item)
        if (listaItens.children.length > 1) {
            const confirmar = confirm('Tem certeza que deseja remover este item?');
            if (!confirmar) return;
        }
        
        // Animação de saída
        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            // Remove o elemento após a animação
            if (item.parentNode) {
                item.parentNode.removeChild(item);
            }
            
            // Renumera os itens restantes
            renumerarItens();
        }, 300);
    }
    
    /**
     * Renumera os títulos dos itens após remoção
     */
    function renumerarItens() {
        const itens = listaItens.querySelectorAll('.item-campanha');
        itens.forEach((item, index) => {
            const titulo = item.querySelector('h4');
            if (titulo) {
                titulo.textContent = `Item ${index + 1}`;
            }
        });
    }
    
    /**
     * Valida o valor do item (deve ser positivo)
     * @param {Element} input - Campo de input do valor
     */
    function validarValorItem(input) {
        const valor = parseFloat(input.value);
        const item = input.closest('.item-campanha');
        
        // Remove classes de validação anteriores
        input.classList.remove('invalid', 'valid');
        
        if (input.value === '') {
            // Campo vazio - remove estilização
            return;
        }
        
        if (isNaN(valor) || valor <= 0) {
            // Valor inválido
            input.classList.add('invalid');
            
            // Mostra mensagem de erro se não existir
            if (!item.querySelector('.erro-valor')) {
                const erroMsg = document.createElement('span');
                erroMsg.className = 'erro-valor';
                erroMsg.textContent = 'O valor deve ser um número positivo';
                erroMsg.style.color = '#e74c3c';
                erroMsg.style.fontSize = '12px';
                erroMsg.style.marginTop = '5px';
                erroMsg.style.display = 'block';
                input.parentNode.appendChild(erroMsg);
            }
        } else {
            // Valor válido
            input.classList.add('valid');
            
            // Remove mensagem de erro se existir
            const erroMsg = item.querySelector('.erro-valor');
            if (erroMsg) {
                erroMsg.remove();
            }
        }
    }
    
    // Validação do formulário antes do envio
    const formulario = document.querySelector('form[action="../controladores/processar_campanha.php"]');
    if (formulario) {
        formulario.addEventListener('submit', function(e) {
            // Valida todos os campos de valor dos itens
            const inputsValor = listaItens.querySelectorAll('input[name="itens[valor][]"]');
            let temErros = false;
            
            inputsValor.forEach(input => {
                const valor = parseFloat(input.value);
                if (input.value !== '' && (isNaN(valor) || valor <= 0)) {
                    temErros = true;
                    input.classList.add('invalid');
                }
            });
            
            if (temErros) {
                e.preventDefault();
                alert('Por favor, corrija os valores inválidos nos itens antes de enviar o formulário.');
                return false;
            }
        });
    }
    
    // Estilos adicionais para validação (serão adicionados dinamicamente)
    const style = document.createElement('style');
    style.textContent = `
        .item-campanha input.invalid {
            border-color: #e74c3c !important;
            box-shadow: 0 0 5px rgba(231, 76, 60, 0.3) !important;
        }
        
        .item-campanha input.valid {
            border-color: #27ae60 !important;
            box-shadow: 0 0 5px rgba(39, 174, 96, 0.3) !important;
        }
        
        .item-campanha {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
    `;
    document.head.appendChild(style);
});

/**
 * Função auxiliar para obter dados dos itens (pode ser usada por outros scripts)
 */
window.obterDadosItens = function() {
    const listaItens = document.getElementById('lista-itens');
    const itens = [];
    
    if (!listaItens) return itens;
    
    const itensElements = listaItens.querySelectorAll('.item-campanha');
    
    itensElements.forEach((item, index) => {
        const nome = item.querySelector('input[name="itens[nome][]"]').value;
        const descricao = item.querySelector('textarea[name="itens[descricao][]"]').value;
        const valor = item.querySelector('input[name="itens[valor][]"]').value;
        const imagem = item.querySelector('input[name="itens[imagem][]"]').value;
        
        if (nome && descricao && valor) {
            itens.push({
                indice: index,
                nome: nome.trim(),
                descricao: descricao.trim(),
                valor: parseFloat(valor),
                imagem: imagem.trim()
            });
        }
    });
    
    return itens;
};