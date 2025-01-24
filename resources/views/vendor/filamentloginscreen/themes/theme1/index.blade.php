@props([
    'heading' => null,
    'subheading' => null,
])

<div>
    <div class="flex w-full min-h-screen">
        <!-- Contenedor izquierdo (imagen) -->
        <div class="hidden h-screen lg:block lg:w-1/2">
            <img
                src="{{ asset('images/imgLogin.jpg') }}"
                alt="imgLogin"
                class="object-cover w-full h-full"
            >
        </div>

        <!-- Contenedor derecho (formulario) -->
        <div class="flex items-center justify-center w-full min-h-screen bg-gray-100 lg:w-1/2">
            <div class="w-full max-w-md p-6">
                <div class="flex items-center justify-center mb-8">
                    <img src="{{ asset('images/logoMitsui.svg') }}" alt="logoMitsui"
                        style="margin-bottom:20px; width: 15rem; height: auto;">
                </div>

                <section class="grid gap-y-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold tracking-tight text-gray-950">
                            {{ $heading ??= $this->getHeading() }}
                        </h2>
                        @if ($subheading)
                            <p class="mt-2 text-gray-500">
                                {{ $subheading }}
                            </p>
                        @endif
                    </div>

                    @if (filament()->hasRegistration())
                        <x-slot name="subheading">
                            {{ __('filament-panels::pages/auth/login.actions.register.before') }}
                            {{ $this->registerAction }}
                        </x-slot>
                    @endif

                    <x-filament-panels::form wire:submit="authenticate">
                        {{ $this->form }}

                        <x-filament-panels::form.actions
                            :actions="$this->getCachedFormActions()"
                            :full-width="$this->hasFullWidthFormActions()"
                        />
                    </x-filament-panels::form>
                </section>
            </div>
        </div>
    </div>

    <style>
        /* Para los labels de los campos (email y password) */
        .fi-fo-field-wrp span {
            @apply text-gray-950 !important;
        }
        /* Para los asteriscos de campos requeridos */
        .fi-fo-field-wrp sup {
            @apply text-red-600 !important;
        }
        /* Para el texto de Recordarme */
        .fi-fo-checkbox-label {
            @apply text-gray-950 !important;
        }
        /* Para asegurar que todos los labels del formulario estén en negro */
        .fi-form-component-label {
            @apply text-gray-950 !important;
        }
        /* Para todos los textos en el formulario */
        .fi-form-component-label span {
            @apply text-gray-950 !important;
        }
        .dark .fi-form-component-label span {
            @apply text-gray-950 !important;
        }
        /* Forzar texto negro en modo oscuro */
        .dark .text-sm.font-medium.leading-6.text-gray-950.dark\:text-white {
            @apply !text-gray-950;
        }
        
        /* Para el asterisco */
        .dark .text-danger-600.dark\:text-danger-400 {
            @apply !text-red-600;
        }

        /* Solución agresiva para forzar el texto negro */
        [class*="dark:text-white"] {
            @apply !text-gray-950;
        }
        
        /* Para los asteriscos */
        [class*="dark:text-danger-400"] {
            @apply !text-red-600;
        }

        /* Asegurarnos que cualquier texto en modo oscuro sea negro */
        .dark * {
            color: rgb(17 24 39) !important;
        }
        
        /* Excepciones para elementos que no queremos en negro */
        .dark button[type="submit"] *,
        .dark a[href] * {
            color: inherit !important;
        }
    </style>

</div>
