<style>
    /* Estilos generales para el header */
    header.fi-sidebar-header {
        background-color: #0075BF !important;
        color: #ffffff !important;
        height: 80px !important;
        /* Aumentamos la altura para un aspecto más moderno */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        /* Sombra más pronunciada */
        padding: 0 24px !important;
        /* Más espacio interno */
        z-index: 30 !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Estilos para el logo */
    header.fi-sidebar-header img {
        filter: brightness(0) invert(1) !important;
        height: 48px !important;
        /* Logo más grande */
        width: auto !important;
        transition: transform 0.3s ease;
        /* Efecto de transición suave */
    }

    header.fi-sidebar-header img:hover {
        transform: scale(1.1);
        /* Efecto de escala al pasar el cursor */
    }

    /* Estilos para la barra de navegación */
    nav.flex.h-16.items-center {
        background-color: #0075BF !important;
        color: #ffffff !important;
        height: 80px !important;
        /* Aumentamos la altura */
        padding: 0 24px !important;
        /* Más espacio interno */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        border-bottom: 2px solid #005bb5 !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Estilos para los enlaces de navegación */
    nav.flex.h-16.items-center a {
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 16px !important;
        /* Tamaño de fuente más grande */
        font-weight: 500 !important;
        /* Fuente más gruesa */
        margin: 0 16px !important;
        /* Espaciado entre enlaces */
        transition: color 0.3s ease;
        /* Transición suave para el color */
    }

    nav.flex.h-16.items-center a:hover {
        color: rgba(255, 255, 255, 0.8) !important;
        /* Color más claro al pasar el cursor */
        text-decoration: underline !important;
    }

    /* Estilos para los botones */
    nav.flex.h-16.items-center button {
        background-color: transparent !important;
        color: #ffffff !important;
        border: none !important;
        height: 40px !important;
        width: 40px !important;
        border-radius: 8px !important;
        /* Bordes más redondeados */
        transition: background-color 0.3s ease, transform 0.3s ease;
        /* Transiciones suaves */
    }

    nav.flex.h-16.items-center button:hover {
        transform: scale(1.1);
        /* Efecto de escala al pasar el cursor */
    }

    /* Estilos para el dropdown del usuario */
    .fi-dropdown-list {
        background-color: white !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 12px !important;
        /* Bordes más redondeados */
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
        /* Sombra más pronunciada */
    }

    /* Texto dentro del dropdown */
    .fi-dropdown-list-item-label {
        color: #000000 !important;
        font-weight: 500 !important;
        overflow: visible;
    }

    /* Íconos dentro del dropdown */
    .fi-dropdown-list-item-icon {
        color: #000000 !important;
        overflow: visible;
    }

    /* Efecto hover en los elementos del dropdown */
    .fi-dropdown-list-item:hover {
        background-color: rgba(0, 0, 0, 0.05) !important;
    }

    /* Botón de salir (logout) */
    .fi-dropdown-list-item button {
        color: #000000 !important;
        font-weight: 500 !important;
    }

    .fi-dropdown-header-label {
        color: #000000 !important;
        font-weight: 500 !important;
    }

    /* Búsqueda en Topbar */
    .fi-topbar .fi-input-wrp {
        background-color: rgba(255, 255, 255, 0.1) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        border-radius: 8px !important;
        /* Bordes redondeados */
    }

    .fi-topbar .fi-input-wrp input {
        color: white !important;
    }

    .fi-topbar .fi-input-wrp input::placeholder {
        color: rgba(255, 255, 255, 0.7) !important;
    }

    /* Iconos en Topbar */
    .fi-topbar .fi-input-wrp-icon {
        color: rgba(255, 255, 255, 0.7) !important;
    }

    /* Estilos para el botón de salir (logout) */
    .fi-dropdown-list-item button.fi-dropdown-list-item-button {
        color: #000000 !important;
        font-weight: 500 !important;
        background-color: transparent !important;
        border: none !important;
        padding: 8px 16px !important;
        width: 100% !important;
        text-align: left !important;
    }

    /* Efecto hover en el botón de salir */
    .fi-dropdown-list-item button.fi-dropdown-list-item-button:hover {
        background-color: rgba(0, 0, 0, 0.05) !important;
        color: #000000 !important;
    }

    .dark .fi-dropdown-list-item .fi-dropdown-list-item-label {
        color: #000000 !important;
        /* Fuerza el color negro incluso en dark mode */
    }

    .fi-icon-btn {
        margin-left: 20px;
    }

    /* Efecto hover para el botón */
    .fi-icon-btn:hover {
        background-color: rgba(255, 255, 255, 0.2) !important;
        /* Fondo más claro al pasar el cursor */
        transform: scale(1.1) !important;
        /* Efecto de escala al pasar el cursor */
    }

    /* Estilos para el ícono dentro del botón */
    .fi-icon-btn-icon {
        width: 36px !important;
        /* Tamaño del ícono */
        height: 36px !important;
        /* Tamaño del ícono */
        color: #ffffff !important;
        /* Color del ícono */
        transition: transform 0.3s ease !important;
        /* Transición suave para el ícono */
    }
</style>
