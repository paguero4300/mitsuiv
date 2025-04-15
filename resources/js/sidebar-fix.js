// Script para manejar el comportamiento del sidebar en Filament

document.addEventListener('DOMContentLoaded', function() {
    // Función para ajustar el margen del contenido principal
    function adjustMainContentMargin() {
        const sidebar = document.querySelector('.fi-sidebar');
        const mainContent = document.querySelector('.fi-main-ctn');
        
        if (!sidebar || !mainContent) return;
        
        // Verificar si el sidebar está colapsado
        const isCollapsed = sidebar.classList.contains('fi-sidebar-collapsed');
        
        // Ajustar el margen del contenido principal
        if (isCollapsed) {
            mainContent.style.marginLeft = '4.5rem';
        } else {
            mainContent.style.marginLeft = '16rem';
        }
    }
    
    // Observar cambios en las clases del sidebar
    const sidebar = document.querySelector('.fi-sidebar');
    if (sidebar) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    adjustMainContentMargin();
                }
            });
        });
        
        observer.observe(sidebar, { attributes: true });
        
        // Ajustar inicialmente
        adjustMainContentMargin();
    }
    
    // Manejar el botón de colapsar/expandir
    const toggleButton = document.querySelector('.fi-sidebar-collapse-button');
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            // El ajuste se hará automáticamente por el observer
        });
    }
});
