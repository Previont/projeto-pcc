// Utilidades globais para scripts do projeto
// Objetivo: fornecer funções simples e reutilizáveis para feedback visual e reset de formulários.
// Termo: "dataset" — espaço onde guardamos atributos personalizados do elemento (como flags de loading).
// Exemplo: mostrarLoading(botao) troca o texto do botão para "Carregando..." e o desabilita temporariamente.
(function () {
  'use strict';

  const Utils = {
    // Reinicia um formulário com segurança (útil após enviar ou cancelar)
    safeReset(form) {
      if (form && typeof form.reset === 'function') form.reset();
    },

    // Mostra estado de carregamento em um botão.
    // Dica: chame botao.finishLoading() para voltar ao estado normal.
    mostrarLoading(botao, texto = 'Carregando...') {
      if (!botao) return;
      // Evita múltiplos estados de loading
      if (botao.dataset.loading === 'true') return;
      botao.dataset.loading = 'true';
      botao.disabled = true;

      const originalText = botao.textContent;
      botao.dataset.originalText = originalText;
      botao.textContent = texto;

      // Estilo simples para indicar loading
      botao.classList.add('is-loading');

      // Expor uma função para encerrar loading (como desligar uma luz)
      botao.finishLoading = function () {
        botao.disabled = false;
        botao.dataset.loading = 'false';
        botao.textContent = botao.dataset.originalText || originalText;
        botao.classList.remove('is-loading');
      };
    }
  };

  window.Utils = Utils;
})();
