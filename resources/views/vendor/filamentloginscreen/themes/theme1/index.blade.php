@props([
    'heading' => null,
    'subheading' => null,
])

<div class="flex min-h-screen w-full">

    <!-- Contenedor izquierdo (imagen) -->
    <div class="hidden lg:block lg:w-1/2 h-screen">
        <img
            src="{{ asset('images/imgLogin.jpg') }}"
            alt="imgLogin"
            class="w-full h-full object-cover"
        >
    </div>

    <!-- Contenedor derecho (formulario) -->
    <div class="w-full lg:w-1/2 min-h-screen bg-gray-100 flex items-center justify-center">

        <div class="max-w-md w-full p-6">

            <div class="flex items-center justify-center mb-8">
                <img src="{{ asset('images/logoMitsui.svg') }}" alt="logoMitsui"
                    style="margin-bottom:20px; width: 15rem; height: auto;">
            </div>

            <section class="grid gap-y-6">
                <x-filament-panels::header.simple
                    :heading="$heading ??= $this->getHeading()"
                    :logo="!request()->routeIs('filament.admin.auth.login') && $this->hasLogo()"
                    :subheading="$subheading ??= $this->getSubHeading()"
                />

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

<script src="https://cdn.tailwindcss.com"></script>
