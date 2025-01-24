<style>
    /* Estilos para el footer */
    .footer {
        background-color: #0075BF !important;
        color: #ffffff !important;
        padding: 5px 15px !important;
        z-index: 10 !important;
        position: relative !important;
        width: 100% !important;
    }

    /* Estilos para el sidebar */
    .fi-sidebar {
        z-index: 50 !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        width: 16rem !important;
        background-color: #f8f9fa !important;
    }
</style>

<footer class="footer bottom-0 left-0 z-10 w-full p-4 bg-gray-800 text-white hidden md:flex flex-col">
    <div class="container mx-auto flex flex-col md:flex-row justify-between items-center md:items-start">
        <!-- Logo -->
        <div class="flex flex-col mt-4 items-start mb-4 md:mb-0">
            <img src="{{ asset('images/logo_Mitsui_Blanco.png') }}" alt="logoMitsui" style="width: 12rem; height: auto;">
        </div>

        <!-- Redes Sociales -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
            <!-- Redes Sociales Lima -->
            <div class="flex flex-col items-center md:items-start text-center mb-4 md:mb-0">
                <p class="text-xs">Redes Sociales Lima</p>
                <div class="flex mt-2 items-center space-x-4">
                    <a href="https://www.facebook.com/MitsuiSeminuevos" target="_blank">
                        <img src="{{ asset('images/logofb.png') }}" alt="Facebook" class="w-8 h-8 hover:scale-110 transition-transform duration-200">
                    </a>
                    <a href="https://www.instagram.com/mitsui_seminuevos/" target="_blank">
                        <img src="{{ asset('images/logoInstagram.png') }}" alt="Instagram" class="w-6 h-6 hover:scale-110 transition-transform duration-200">
                    </a>
                </div>
            </div>

            <!-- Redes Sociales Arequipa -->
            <div class="flex flex-col items-center md:items-start text-center">
                <p class="text-xs">Redes Sociales Arequipa</p>
                <div class="flex mt-2 items-center space-x-4">
                    <a href="https://www.facebook.com/mitsuiseminuevosarequipa" target="_blank">
                        <img src="{{ asset('images/logofb.png') }}" alt="Facebook" class="w-8 h-8 hover:scale-110 transition-transform duration-200">
                    </a>
                    <a href="https://www.instagram.com/mitsuiseminuevosarequipa/" target="_blank">
                        <img src="{{ asset('images/logoInstagram.png') }}" alt="Instagram" class="w-6 h-6 hover:scale-110 transition-transform duration-200">
                    </a>
                </div>
            </div>
        </div>

        <!-- Contacto -->
        <div class="flex flex-col items-center md:items-start space-y-2 text-center md:text-left text-xs">
            <div class="flex items-center space-x-2">
                <img src="{{ asset('images/phoneLogo.png') }}" alt="Teléfono" class="w-6 h-6">
                <p>(01) 705 0123</p>
            </div>
            <div class="flex items-center space-x-2">
                <img src="{{ asset('images/wspLogo.png') }}" alt="WhatsApp" class="w-6 h-6">
                <p>+51 1 705 0123</p>
            </div>
            <div class="flex items-center space-x-2">
                <img src="{{ asset('images/correoLogo.png') }}" alt="Correo" class="w-6 h-6">
                <p>mitsuiseminuevos@mitsuiautomotriz.com</p>
            </div>
        </div>
    </div>

    <!--  Derechos Reservados -->
    <div class="border-t border-white mt-1 pt-2 w-full text-center text-xs">
        <p>2024 Mitsui Seminuevos. Todos los Derechos Reservados.</p>
    </div>
</footer>
