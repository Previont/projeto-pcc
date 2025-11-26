/**
 * Script para gerenciar itens da campanha na página de edição
 * Funcionalidades: adicionar, remover, validar itens e upload de imagens
 * Compatível com itens já existentes e novos itens
 */

document.addEventListener('DOMContentLoaded', function() {
    const adicionarItemBtn = document.getElementById('adicionar-item');
    const listaItens = document.getElementById('lista-itens');
    const template = document.getElementById('template-item');
    
    if (!adicionarItemBtn || !listaItens || !template) {
        console.error('Elementos necessários não encontrados');
        return;
    }
    
    

    adicionarItemBtn.addEventListener('click', function(e) {
        e.preventDefault();
        adicionarItem();
    });
    

    listaItens.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remover-item')) {
            e.preventDefault();
            const item = e.target.closest('.item-campanha');
            if (item) {
                confirmarRemoverItem(item);
            }
        }
    });
    

    listaItens.addEventListener('input', function(e) {
        if (e.target.type === 'number' && e.target.name.includes('itens[valor]')) {
            validarValorItem(e.target);
        }
    });
    

    listaItens.addEventListener('change', function(e) {
        if (e.target.type === 'file' && e.target.name.includes('itens[imagem]')) {
            e.preventDefault();
            processarUploadImagem(e.target);
        }
    });
    

    listaItens.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox' && e.target.name.includes('manter_imagem')) {
            const item = e.target.closest('.item-campanha');
            const containerUpload = item.querySelector('.upload-container');
            
            if (e.target.checked) {

                const imagemAtual = item.querySelector('.imagem-atual');
                if (imagemAtual) {
                    imagemAtual.style.display = 'block';
                }
                containerUpload.style.opacity = '0.5';
            } else {

                const imagemAtual = item.querySelector('.imagem-atual');
                if (imagemAtual) {
                    imagemAtual.style.display = 'none';
                }
                containerUpload.style.opacity = '1';
            }
        }
    });
    

    const formulario = document.querySelector('form[action="../controladores/processar_alteracao_campanha.php"]');
    if (formulario) {
        formulario.addEventListener('submit', function(e) {

            const inputsValor = listaItens.querySelectorAll('input[name="itens[valor][]"]');
            let temErros = false;
            let mensagensErro = [];
            
            inputsValor.forEach((input, index) => {
                const valor = parseFloat(input.value);
                if (input.value !== '' && (isNaN(valor) || valor <= 0)) {
                    temErros = true;
                    input.classList.add('invalid');
                    mensagensErro.push(`Item ${index + 1}: valor deve ser um número positivo`);
                }
            });
            
            if (temErros) {
                e.preventDefault();
                alert('Por favor, corrija os seguintes erros:\n\n' + mensagensErro.join('\n'));
                return false;
            }
            

            const btnSubmit = formulario.querySelector('button[type="submit"]');
            if (btnSubmit) {
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                btnSubmit.disabled = true;
            }
        });
    }
    
    /**
     * Adiciona um novo item à lista
     */
    function adicionarItem() {
        
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('.item-campanha');
        
        if (!item) {
            console.error('Template de item não encontrado');
            return;
        }
        

        const numeroItem = listaItens.children.length + 1;
        const titulo = clone.querySelector('h4');
        if (titulo) {
            titulo.textContent = `Item ${numeroItem}`;
        }
        

        const fileInput = clone.querySelector('input[type="file"]');
        const fileLabel = clone.querySelector('label[for]');
        if (fileInput && fileLabel) {
            const novoId = `file-upload-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            fileInput.id = novoId;
            fileLabel.setAttribute('for', novoId);
        }
        

        listaItens.appendChild(clone);
        

        const primeiroInput = item.querySelector('input[type="text"]');
        if (primeiroInput) {
            setTimeout(() => primeiroInput.focus(), 100);
        }
        

        item.style.opacity = '0';
        item.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 50);
        

        setTimeout(() => {
            item.style.transition = '';
        }, 350);
        
    }
    
    /**
     * Confirma e remove um item da lista
     * @param {Element} item - Elemento do item a ser removido
     */
    function confirmarRemoverItem(item) {
        const itemId = item.getAttribute('data-item-id');
        const titulo = item.querySelector('h4')?.textContent || 'este item';
        
        let mensagem = 'Tem certeza que deseja remover ' + titulo + '?';
        if (itemId) {
            mensagem += '\n\nEsta ação não pode ser desfeita.';
        }
        
        const confirmar = confirm(mensagem);
        if (!confirmar) return;
        
        if (itemId) {

            marcarItemParaRemocao(item);
        } else {

            removerItem(item);
        }
    }
    
    /**
     * Marca um item existente para remoção
     * @param {Element} item - Elemento do item
     */
    function marcarItemParaRemocao(item) {

        const inputHidden = document.createElement('input');
        inputHidden.type = 'hidden';
        inputHidden.name = 'itens[remover][]';
        inputHidden.value = item.getAttribute('data-item-id');
        

        const formulario = item.closest('form');
        if (formulario) {
            formulario.appendChild(inputHidden);
        }
        

        item.classList.add('item-removido');
        item.style.opacity = '0.5';
        item.style.backgroundColor = 'rgba(231, 76, 60, 0.1)';
        

        const inputs = item.querySelectorAll('input, textarea, button');
        inputs.forEach(input => {
            if (input.type !== 'hidden') {
                input.disabled = true;
            }
        });
        

        const btnRemover = item.querySelector('.btn-remover-item');
        if (btnRemover) {
            btnRemover.innerHTML = '<i class="fas fa-undo"></i>';
            btnRemover.title = 'Desfazer remoção';
        }
    }
    
    /**
     * Remove um item da lista (apenas para novos itens)
     * @param {Element} item - Elemento do item a ser removido
     */
    function removerItem(item) {
        if (!item) return;
        

        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {

            if (item.parentNode) {
                item.parentNode.removeChild(item);
            }
            

            renumerarItens();
        }, 300);
    }
    
    /**
     * Renumera os títulos dos itens após remoção
     */
    function renumerarItens() {
        if (window.ItensCampanhaCore) {
            window.ItensCampanhaCore.renumerarItens(listaItens, { skipRemoved: true });
            return;
        }
        const itens = listaItens.querySelectorAll('.item-campanha:not(.item-removido)');
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
        if (window.ItensCampanhaCore) { window.ItensCampanhaCore.validarValorItem(input); return; }
        const valor = parseFloat(input.value);
        const item = input.closest('.item-campanha');
        input.classList.remove('invalid', 'valid');
        if (input.value === '') { return; }
        if (isNaN(valor) || valor <= 0) {
            input.classList.add('invalid');
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
            input.classList.add('valid');
            const erroMsg = item.querySelector('.erro-valor');
            if (erroMsg) { erroMsg.remove(); }
        }
    }
    
    /**
     * Processa upload de imagem
     * @param {HTMLInputElement} inputFile - Campo de input do arquivo
     */
    function processarUploadImagem(inputFile) {
        if (window.ItensCampanhaCore) { window.ItensCampanhaCore.processarUploadImagem(inputFile); return; }
        const file = inputFile.files[0];
        const container = inputFile.closest('.upload-container') || inputFile.closest('.form-group');
        if (!file) return;
        limparEstados(container);
        mostrarCarregamento(container);
        const validacao = validarArquivo(file);
        if (!validacao.valido) {
            esconderCarregamento(container);
            exibirErro(container, validacao.erro);
            inputFile.value = '';
            return;
        }
        setTimeout(() => {
            esconderCarregamento(container);
            criarPreview(container, file);
            exibirInfoArquivo(container, file);
            atualizarBotaoUpload(container, file);
            container.classList.add('has-success');
        }, 300);
    }
    
    /**
     * Valida arquivo de imagem
     * @param {File} file - Arquivo a ser validado
     */
    function validarArquivo(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!file) {
            return { valido: false, erro: 'Nenhum arquivo selecionado' };
        }
        
        if (!allowedTypes.includes(file.type)) {
            return {
                valido: false,
                erro: 'Tipo de arquivo não permitido. Use JPG, PNG ou GIF.'
            };
        }
        
        if (file.size > maxSize) {
            return {
                valido: false,
                erro: 'Arquivo muito grande. Tamanho máximo: 5MB.'
            };
        }
        
        return { valido: true };
    }
    
    /**
     * Cria preview da imagem
     * @param {Element} container - Container do upload
     * @param {File} file - Arquivo da imagem
     */
    function criarPreview(container, file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            let preview = container.querySelector('.preview-imagem');
            
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'preview-imagem';
                preview.style.display = 'block';
                preview.style.marginTop = '8px';
                preview.style.textAlign = 'center';
                
                const img = document.createElement('img');
                img.className = 'preview-img';
                img.style.maxWidth = '200px';
                img.style.maxHeight = '150px';
                img.style.border = '1px solid #555';
                img.style.borderRadius = '4px';
                img.style.padding = '5px';
                
                const overlay = document.createElement('div');
                overlay.className = 'preview-overlay';
                overlay.style.position = 'absolute';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.right = '0';
                overlay.style.bottom = '0';
                overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.opacity = '0';
                overlay.style.transition = 'opacity 0.3s ease';
                
                const btnTrocar = document.createElement('button');
                btnTrocar.type = 'button';
                btnTrocar.className = 'btn-trocar-imagem';
                btnTrocar.title = 'Trocar imagem';
                btnTrocar.innerHTML = '<i class="fas fa-camera"></i>';
                btnTrocar.style.backgroundColor = '#42a5f5';
                btnTrocar.style.color = 'white';
                btnTrocar.style.border = 'none';
                btnTrocar.style.borderRadius = '50%';
                btnTrocar.style.width = '40px';
                btnTrocar.style.height = '40px';
                btnTrocar.style.cursor = 'pointer';
                
                btnTrocar.onclick = function(e) {
                    e.stopPropagation();
                    const fileInput = container.querySelector('input[type="file"]');
                    if (fileInput) {
                        fileInput.click();
                    }
                };
                
                preview.style.position = 'relative';
                overlay.appendChild(btnTrocar);
                preview.appendChild(img);
                preview.appendChild(overlay);
                

                preview.onmouseenter = function() {
                    overlay.style.opacity = '1';
                };
                preview.onmouseleave = function() {
                    overlay.style.opacity = '0';
                };
            }
            
            const img = preview.querySelector('.preview-img');
            img.src = e.target.result;
            

            if (!preview.parentNode) {
                container.appendChild(preview);
            }
        };
        
        reader.readAsDataURL(file);
    }
    
    /**
     * Exibe informações do arquivo
     * @param {Element} container - Container do upload
     * @param {File} file - Arquivo selecionado
     */
    function exibirInfoArquivo(container, file) {
        if (window.ItensCampanhaCore) { window.ItensCampanhaCore.exibirInfoArquivo(container, file); return; }
        let info = container.querySelector('.file-info');
        
        if (!info) {
            info = document.createElement('div');
            info.className = 'file-info';
            info.style.display = 'flex';
            info.style.alignItems = 'center';
            info.style.justifyContent = 'space-between';
            info.style.padding = '8px 12px';
            info.style.backgroundColor = '#2C2C2C';
            info.style.border = '1px solid #555';
            info.style.borderRadius = '4px';
            info.style.marginTop = '5px';
            
            const nome = document.createElement('span');
            nome.className = 'file-name';
            nome.style.color = '#E0E0E0';
            nome.style.fontSize = '12px';
            nome.style.maxWidth = '80%';
            nome.style.overflow = 'hidden';
            nome.style.textOverflow = 'ellipsis';
            nome.style.whiteSpace = 'nowrap';
            
            const btnRemover = document.createElement('button');
            btnRemover.type = 'button';
            btnRemover.className = 'btn-remover-imagem';
            btnRemover.title = 'Remover imagem';
            btnRemover.innerHTML = '<i class="fas fa-times"></i>';
            btnRemover.style.backgroundColor = '#e74c3c';
            btnRemover.style.color = 'white';
            btnRemover.style.border = 'none';
            btnRemover.style.borderRadius = '50%';
            btnRemover.style.width = '20px';
            btnRemover.style.height = '20px';
            btnRemover.style.cursor = 'pointer';
            btnRemover.style.fontSize = '10px';
            btnRemover.style.marginLeft = '8px';
            
            btnRemover.onclick = function(e) {
                e.stopPropagation();
                limparPreview(container);
            };
            
            info.appendChild(nome);
            info.appendChild(btnRemover);
            container.appendChild(info);
        }
        
        const nome = info.querySelector('.file-name');
        if (nome) {
            nome.textContent = truncarNomeArquivo(file.name, 25);
        }
    }
    
    /**
     * Atualiza o botão de upload com o nome do arquivo
     * @param {Element} container - Container do upload
     * @param {File} file - Arquivo selecionado
     */
    function atualizarBotaoUpload(container, file) {
        const label = container.querySelector('.file-label');
        if (label) {
            const nomeTruncado = truncarNomeArquivo(file.name, 30);
            label.innerHTML = `<i class="fas fa-image"></i><span>${nomeTruncado}</span>`;
            label.title = file.name; // Tooltip com nome completo
            

            label.classList.add('arquivo-selecionado');
        }
    }
    
    /**
     * Trunca nome do arquivo
     * @param {string} nome - Nome do arquivo
     * @param {number} maxLength - Tamanho máximo
     */
    function truncarNomeArquivo(nome, maxLength) {
        if (nome.length <= maxLength) return nome;
        
        const extensao = nome.split('.').pop();
        const nomeSemExtensao = nome.slice(0, -(extensao.length + 1));
        
        return nomeSemExtensao.slice(0, maxLength - extensao.length - 4) + '...' + extensao;
    }
    
    /**
     * Mostra indicador de carregamento
     * @param {Element} container - Container do upload
     */
    function mostrarCarregamento(container) {
        const label = container.querySelector('.file-label');
        if (label) {
            label.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Carregando...</span>';
            label.style.pointerEvents = 'none';
        }
    }
    
    /**
     * Esconde indicador de carregamento
     * @param {Element} container - Container do upload
     */
    function esconderCarregamento(container) {
        const label = container.querySelector('.file-label');
        if (label) {
            label.innerHTML = '<i class="fas fa-cloud-upload-alt"></i><span>Escolher imagem</span>';
            label.style.pointerEvents = 'auto';
        }
    }
    
    /**
     * Exibe mensagem de erro
     * @param {Element} container - Container do upload
     * @param {string} mensagem - Mensagem de erro
     */
    function exibirErro(container, mensagem) {
        if (window.ItensCampanhaCore) { window.ItensCampanhaCore.exibirErro(container, mensagem); return; }
        let erroDiv = container.querySelector('.erro-imagem');
        
        if (!erroDiv) {
            erroDiv = document.createElement('div');
            erroDiv.className = 'erro-imagem';
            erroDiv.style.color = '#e74c3c';
            erroDiv.style.fontSize = '12px';
            erroDiv.style.marginTop = '5px';
            erroDiv.style.padding = '5px 8px';
            erroDiv.style.backgroundColor = 'rgba(231, 76, 60, 0.1)';
            erroDiv.style.border = '1px solid rgba(231, 76, 60, 0.3)';
            erroDiv.style.borderRadius = '4px';
            container.appendChild(erroDiv);
        }
        
        erroDiv.textContent = mensagem;
        erroDiv.style.display = 'block';
        container.classList.add('has-error');
        

        setTimeout(() => {
            if (erroDiv.parentNode) {
                erroDiv.remove();
            }
        }, 5000);
    }
    
    /**
     * Limpa preview da imagem
     * @param {Element} container - Container do upload
     */
    function limparPreview(container) {
        if (window.ItensCampanhaCore) { window.ItensCampanhaCore.limparPreview(container); return; }
        const preview = container.querySelector('.preview-imagem');
        const info = container.querySelector('.file-info');
        const input = container.querySelector('input[type="file"]');
        
        if (preview) preview.remove();
        if (info) info.remove();
        if (input) input.value = '';
        

        const label = container.querySelector('.file-label');
        if (label) {
            label.innerHTML = '<i class="fas fa-cloud-upload-alt"></i><span>Escolher imagem</span>';
            label.title = '';
            label.classList.remove('arquivo-selecionado');
        }
        
        container.classList.remove('has-success', 'has-error');
        limparEstados(container);
    }
    
    /**
     * Limpa estados de erro
     * @param {Element} container - Container do upload
     */
    function limparEstados(container) {
        if (window.ItensCampanhaCore) { window.ItensCampanhaCore.limparEstados(container); return; }
        const erroDiv = container.querySelector('.erro-imagem');
        if (erroDiv) {
            erroDiv.style.display = 'none';
        }
        container.classList.remove('has-error');
    }
    

    adicionarEstilosDinamicos();

    const containers = listaItens.querySelectorAll('.upload-container');
    containers.forEach(function(container){
        const input = container.querySelector('input[type="file"]');
        const label = container.querySelector('.file-label');
        if (input && label) {
            if (!input.id) {
                input.id = 'file-upload-' + Date.now() + '-' + Math.random().toString(36).substr(2,9);
            }
            label.setAttribute('for', input.id);
            label.addEventListener('click', function(e){
                e.preventDefault();
                input.click();
            });
        }
    });

    window.runUploadButtonTests = function(){
        var resultados = [];
        function assert(nome, cond){ resultados.push({nome:nome, ok:!!cond}); }
        var testContainer1 = document.createElement('div');
        testContainer1.className = 'upload-container';
        var input1 = document.createElement('input');
        input1.type = 'file';
        var label1 = document.createElement('label');
        label1.className = 'file-label';
        testContainer1.appendChild(input1);
        testContainer1.appendChild(label1);
        document.body.appendChild(testContainer1);
        var testContainer2 = document.createElement('div');
        testContainer2.className = 'upload-container';
        var input2 = document.createElement('input');
        input2.type = 'file';
        var label2 = document.createElement('label');
        label2.className = 'file-label';
        testContainer2.appendChild(input2);
        testContainer2.appendChild(label2);
        document.body.appendChild(testContainer2);
        function wire(container){
            var inp = container.querySelector('input[type="file"]');
            var lab = container.querySelector('.file-label');
            if (!inp.id) { inp.id = 'file-upload-' + Date.now() + '-' + Math.random().toString(36).substr(2,9); }
            lab.setAttribute('for', inp.id);
            lab.addEventListener('click', function(e){ e.preventDefault(); inp.click(); });
        }
        wire(testContainer1);
        wire(testContainer2);
        var clicks1 = 0, clicks2 = 0;
        var origClick = HTMLInputElement.prototype.click;
        HTMLInputElement.prototype.click = function(){ if (this===input1) clicks1++; if (this===input2) clicks2++; origClick.call(this); };
        label1.click();
        label2.click();
        assert('label dispara input.click (1)', clicks1===1);
        assert('label dispara input.click (2)', clicks2===1);
        HTMLInputElement.prototype.click = origClick;
        var fakeFile = new File([new Blob(['x'])],'teste.png',{type:'image/png'});
        var fakeInput1 = { files:[fakeFile], closest:function(sel){ return testContainer1; }, value:'', type:'file', name:'itens[imagem][]' };
        var fakeInput2 = { files:[fakeFile], closest:function(sel){ return testContainer2; }, value:'', type:'file', name:'itens[imagem][]' };
        if (window.ItensCampanhaCore && typeof window.ItensCampanhaCore.processarUploadImagem==='function') {
            window.ItensCampanhaCore.processarUploadImagem(fakeInput1);
            window.ItensCampanhaCore.processarUploadImagem(fakeInput2);
            var l1 = testContainer1.querySelector('.file-label');
            var l2 = testContainer2.querySelector('.file-label');
            assert('ambos setam arquivo-selecionado', l1.classList.contains('arquivo-selecionado') && l2.classList.contains('arquivo-selecionado'));
            var p1 = testContainer1.querySelector('.preview-imagem');
            var p2 = testContainer2.querySelector('.preview-imagem');
            assert('ambos criam preview', !!p1 && !!p2);
            assert('estado has-success idêntico', testContainer1.classList.contains('has-success') && testContainer2.classList.contains('has-success'));
        } else {
            assert('core disponível', false);
        }
        var out = document.createElement('div');
        out.id = 'upload-tests-results';
        out.style.position = 'fixed';
        out.style.bottom = '10px';
        out.style.right = '10px';
        out.style.background = '#1f1f1f';
        out.style.color = '#fff';
        out.style.padding = '10px';
        out.style.border = '1px solid #555';
        out.style.borderRadius = '6px';
        resultados.forEach(function(r){ var li = document.createElement('div'); li.textContent = (r.ok?'✔ ':'✖ ') + r.nome; li.style.marginBottom = '4px'; out.appendChild(li); });
        document.body.appendChild(out);
        return resultados;
    };

    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('runUploadTests') === '1') { window.runUploadButtonTests(); }
    } catch (e) {}
});

/**
 * Adiciona estilos dinâmicos ao documento
 */
function adicionarEstilosDinamicos() {
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
        
        .item-campanha.item-removido {
            border: 2px dashed #e74c3c;
            background-color: rgba(231, 76, 60, 0.1);
        }
        
        .upload-container.has-error .file-label {
            border-color: #e74c3c;
            background-color: rgba(231, 76, 60, 0.1);
        }
        
        .upload-container.has-success .file-label {
            border-color: #27ae60;
        }
        
        .imagem-atual {
            margin-bottom: 10px;
        }
        
        .info-imagem {
            color: #42a5f5;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .opcoes-imagem {
            margin-top: 5px;
        }
        
        .checkbox-manter {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #E0E0E0;
        }
        
        .checkbox-manter input[type="checkbox"] {
            margin-right: 5px;
        }
    `;
    document.head.appendChild(style);
}
