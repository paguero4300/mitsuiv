{{-- resources/views/filament/pages/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mitsui</title>

    <!-- Incluir el archivo CSS compilado por Vite -->
    @vite('resources/css/filament/login.css')
</head>
<body>
    <x-filament-panels::page.simple>
        <div class="flex w-full h-full">
            <div class="hidden lg:block w-1/2 h-full">
                <img src="{{ asset('img/imgLogin.jpg') }}"
                     alt="Showroom with cars"
                     class="w-full h-full object-cover" />
            </div>

            <div class="w-full lg:w-1/2 flex items-center justify-center bg-white px-6 lg:px-0">
                <div class="w-full max-w-md">
                    <div class="text-center mb-8">
                        <img src="{{ asset('img/Logo Mitsui.svg') }}"
                             alt="Mitsui Logo"
                             class="mx-auto"
                             width="250"
                             height="70" />
                    </div>

                    <h2 class="text-xl mt-10 text-center font-semibold text-gray-700 mb-10">
                        {{ __('filament-panels::pages/auth/login.title') }}
                    </h2>

                    {{ $this->form }}

                    <x-filament::button
                        type="submit"
                        form="authenticate"
                        class="w-full mt-6 bg-primary-600 hover:bg-primary-500">
                        {{ __('filament-panels::pages/auth/login.buttons.submit.label') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </x-filament-panels::page.simple>
</body>
</html>
