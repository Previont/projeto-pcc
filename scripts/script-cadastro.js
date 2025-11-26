document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const usuarioInput = document.getElementById('usuario');
    const emailInput = document.getElementById('email');
    const senhaInput = document.getElementById('senha');
    const confirmaSenhaInput = document.getElementById('confirma_senha');

    // Funções de validação
    function validateUsuario(value) {
        return value.trim().length >= 3;
    }

    function validateEmail(value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(value.trim());
    }

    function validateSenha(value) {
        return value.length >= 6;
    }

    function validateConfirmaSenha(value, senhaValue) {
        return value === senhaValue;
    }

    // Função para mostrar/ocultar erro
    function setError(input, messageElement, isValid, message) {
        if (isValid) {
            input.classList.remove('error');
            if (messageElement) messageElement.style.display = 'none';
        } else {
            input.classList.add('error');
            if (messageElement) {
                messageElement.textContent = message;
                messageElement.style.display = 'block';
            }
        }
    }

    // Validação em tempo real para usuário
    usuarioInput.addEventListener('input', function() {
        const isValid = validateUsuario(this.value);
        setError(this, document.getElementById('error-usuario'), isValid, 'Usuário deve ter pelo menos 3 caracteres.');
    });

    // Validação em tempo real para email
    emailInput.addEventListener('input', function() {
        const isValid = validateEmail(this.value);
    setError(this, document.getElementById('error-email'), isValid, 'E-mail inválido.');
    });

    // Validação em tempo real para senha
    senhaInput.addEventListener('input', function() {
        const isValid = validateSenha(this.value);
        setError(this, document.getElementById('error-senha'), isValid, 'Senha deve ter pelo menos 6 caracteres.');
        
        // Revalida confirmação se senha mudou
        const confirmaValue = confirmaSenhaInput.value;
        if (confirmaValue) {
            const confirmaValid = validateConfirmaSenha(confirmaValue, this.value);
            setError(confirmaSenhaInput, document.getElementById('error-confirma-senha'), confirmaValid, 'Senhas não coincidem.');
        }
    });

    // Validação em tempo real para confirmação de senha
    confirmaSenhaInput.addEventListener('input', function() {
        const senhaValue = senhaInput.value;
        const isValid = validateConfirmaSenha(this.value, senhaValue);
        setError(this, document.getElementById('error-confirma-senha'), isValid, 'Senhas não coincidem.');
    });

    // Validação no submit
    form.addEventListener('submit', function(e) {
        let isFormValid = true;

        // Valida usuário
        if (!validateUsuario(usuarioInput.value)) {
            setError(usuarioInput, document.getElementById('error-usuario'), false, 'Usuário deve ter pelo menos 3 caracteres.');
            isFormValid = false;
        }

        // Valida email
  if (!validateEmail(emailInput.value)) {
    setError(emailInput, document.getElementById('error-email'), false, 'E-mail inválido.');
            isFormValid = false;
        }

        // Valida senha
        if (!validateSenha(senhaInput.value)) {
            setError(senhaInput, document.getElementById('error-senha'), false, 'Senha deve ter pelo menos 6 caracteres.');
            isFormValid = false;
        }

        // Valida confirmação
        if (!validateConfirmaSenha(confirmaSenhaInput.value, senhaInput.value)) {
            setError(confirmaSenhaInput, document.getElementById('error-confirma-senha'), false, 'Senhas não coincidem.');
            isFormValid = false;
        }

        if (!isFormValid) {
            e.preventDefault();
            alert('Por favor, corrija os erros no formulário.');
        }
    });
});
