// Menu do usuário (mostrar/esconder ao passar o mouse)
// Dica: usamos pequeno atraso para evitar "piscar" ao mover o cursor.
document.addEventListener('DOMContentLoaded', function() {
    const iconeUsuario = document.getElementById('iconeUsuario');
    const menuUsuario = document.getElementById('menuUsuario');
    let menuTimeout;

    function mostrarMenu() {
        // Se houver um timer para esconder o menu, cancele-o.
        clearTimeout(menuTimeout);
        // Mostra o menu.
        if (menuUsuario) {
            menuUsuario.classList.add('active');
        }
    }

    function esconderMenu() {
        // Define um timer para esconder o menu após um pequeno atraso.
        menuTimeout = setTimeout(() => {
            if (menuUsuario) {
                menuUsuario.classList.remove('active');
            }
        }, 300); // 300ms de atraso
    }

    // Adiciona eventos apenas se os elementos existirem.
    if (iconeUsuario && menuUsuario) {
        // Eventos para o ícone do usuário.
        iconeUsuario.addEventListener('mouseenter', mostrarMenu);
        iconeUsuario.addEventListener('mouseleave', esconderMenu);

        // Eventos para o próprio menu para mantê-lo aberto.
        menuUsuario.addEventListener('mouseenter', mostrarMenu);
        menuUsuario.addEventListener('mouseleave', esconderMenu);
    }
});
