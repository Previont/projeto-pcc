// Scripts do painel admin: navegação e UX básicos
// Propósito: alternar visibilidade da barra lateral (sidebar) no painel.
// Relacionados: `visualizadores/admin_dashboard.php`, `visualizadores/admin_gerenciar_*`.
// Exemplo: clique no botão com classe `.menu-toggle` para abrir/fechar o menu.
document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
});
