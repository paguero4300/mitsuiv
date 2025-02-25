<div>
    <div wire:poll.1s class="p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            @if($startCountdown)
                <div class="p-4 transition-shadow bg-white shadow-sm rounded-xl hover:shadow-md">
                    <span class="block mb-2 text-sm font-medium text-gray-600">Inicio</span>
                    <x-filament::badge size="lg" color="warning">
                        {{ $startCountdown }}
                    </x-filament::badge>
                </div>
            @endif

            @if($remainingTime)
                <div class="p-4 transition-shadow bg-white shadow-sm rounded-xl hover:shadow-md">
                    <span class="block mb-2 text-sm font-medium text-gray-600">Tiempo Restante</span>
                    <x-filament::badge size="lg" :color="$remainingTime === 'Subasta finalizada' ? 'gray' : 'success'">
                        {{ $remainingTime }}
                    </x-filament::badge>
                </div>
            @endif

            <div class="p-4 transition-shadow bg-white shadow-sm rounded-xl hover:shadow-md">
                <span class="block mb-2 text-sm font-medium text-gray-600">Estado</span>
                <x-filament::badge size="lg" :color="$statusColor">
                    {{ $status }}
                </x-filament::badge>
            </div>

            <div class="p-4 transition-shadow bg-white shadow-sm rounded-xl hover:shadow-md">
                <span class="block mb-2 text-sm font-medium text-gray-600">Precio Base</span>
                <x-filament::badge size="lg" color="info">
                    US$ {{ number_format($basePrice, 0, '', ',') }}
                </x-filament::badge>
            </div>

            <div class="p-4 transition-shadow bg-white shadow-sm rounded-xl hover:shadow-md">
                <span class="block mb-2 text-sm font-medium text-gray-600">Precio Actual</span>
                <x-filament::badge size="lg" color="success">
                    US$ {{ number_format($currentPrice, 0, '', ',') }}
                </x-filament::badge>
            </div>
        </div>

        <div class="flex justify-center gap-2 mt-4 lg:justify-end">
            <x-filament::button
                :color="$record->end_date->isPast() ? 'success' : 'gray'"
                icon="heroicon-m-trophy"
                size="lg"
                tag="a"
                href="#"
                x-on:click="$dispatch('open-modal', { id: 'winner-{{ $record->id }}' })"
                :disabled="!$record->end_date->isPast()"
            >
                Ver Ganador
            </x-filament::button>

            <x-filament::button
                color="primary"
                icon="heroicon-m-clock"
                size="lg"
                tag="a"
                href="#"
                x-on:click="$dispatch('open-modal', { id: 'bid-history-{{ $record->id }}' })"
            >
                Ver Historial
            </x-filament::button>
        </div>
    </div>

    <x-filament::modal
        id="winner-{{ $record->id }}"
        :heading="__('Ganador de la Subasta')"
        width="md"
    >
        @php
            $winningBid = $record->bids()->orderByDesc('amount')->first();
        @endphp

        @if (!$winningBid)
            <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                <x-heroicon-o-x-circle class="w-12 h-12 mb-3 text-danger-500" />
                <p class="text-lg font-medium">Subasta sin ganador</p>
                <p class="text-sm text-gray-400">No se registraron ofertas en esta subasta</p>
            </div>
        @else
            <div class="p-4 space-y-6">
                <div class="flex items-center justify-center">
                    <div class="p-2 rounded-full bg-success-50">
                        <x-heroicon-o-trophy class="w-8 h-8 text-success-500" />
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="p-4 bg-white border border-gray-200 rounded-lg">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-50">
                                <x-heroicon-o-user class="w-6 h-6 text-primary-500" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $winningBid->reseller->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $winningBid->reseller->email }}</p>
                                <p class="text-sm text-gray-500">
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-phone class="w-4 h-4" />
                                        {{ $winningBid->reseller->custom_fields['phone'] ?? 'No registrado' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-lg bg-gray-50">
                            <p class="text-sm font-medium text-gray-500">Monto Ganador</p>
                            <p class="text-lg font-bold text-success-600">US$ {{ number_format($winningBid->amount, 0, '', ',') }}</p>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50">
                            <p class="text-sm font-medium text-gray-500">Fecha de Adjudicación</p>
                            <p class="text-lg font-medium text-gray-700">{{ $record->end_date->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    @if($canAdjudicate)
                        <div class="flex flex-col gap-4 pt-4 mt-4 border-t">
                            <div class="flex flex-col">
                                <label class="mb-2 text-sm font-medium text-gray-600">
                                    Notas de Adjudicación
                                </label>
                                <textarea
                                    wire:model="adjudicationNotes"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                                    rows="3"
                                    placeholder="Ingrese notas o comentarios sobre la adjudicación (opcional)"
                                ></textarea>
                            </div>

                            <div class="flex justify-end gap-3">
                                <x-filament::button
                                    wire:click="rejectAdjudication"
                                    color="danger"
                                    icon="heroicon-m-x-mark"
                                    wire:loading.attr="disabled"
                                >
                                    No Adjudicar
                                </x-filament::button>

                                <x-filament::button
                                    wire:click="acceptAdjudication"
                                    color="success"
                                    icon="heroicon-m-check"
                                    wire:loading.attr="disabled"
                                >
                                    Adjudicar Subasta
                                </x-filament::button>
                            </div>
                        </div>
                    @elseif($record->status->slug === 'adjudicada')
                        <div class="p-4 mt-4 rounded-lg bg-success-50">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-5 h-5 text-success-500" />
                                <p class="text-success-700">Subasta Adjudicada</p>
                            </div>
                        </div>
                    @elseif($record->status->slug === 'fallida')
                        <div class="p-4 mt-4 rounded-lg bg-danger-50">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-x-circle class="w-5 h-5 text-danger-500" />
                                <p class="text-danger-700">Subasta No Adjudicada</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </x-filament::modal>

    <x-filament::modal
        id="bid-history-{{ $record->id }}"
        :heading="__('Historial de Ofertas')"
        :description="__('Historial de ofertas realizadas')"
        width="xl"
    >
        <div class="space-y-4">
            @forelse($record->bids()->with('reseller')->latest()->get() as $bid)
                <div class="flex flex-col items-center justify-between p-5 space-y-3 bg-white border border-gray-200 rounded-xl sm:flex-row sm:space-y-0">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-50">
                                <x-heroicon-o-user class="w-7 h-7 text-primary-500" />
                            </div>
                        </div>
                        <div class="text-left">
                            <p class="text-base font-medium text-gray-900">{{ $bid->reseller->name }}</p>
                            <p class="text-sm text-gray-500">{{ $bid->reseller->email }}</p>
                            <p class="text-sm text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <x-heroicon-o-phone class="w-4 h-4" />
                                    {{ $bid->reseller->custom_fields['phone'] ?? 'No registrado' }}
                                </span>
                            </p>
                            <p class="text-sm text-gray-500">{{ $bid->created_at->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                    <div class="mt-2 sm:mt-0">
                        <x-filament::badge size="lg" color="success">
                            US$ {{ number_format($bid->amount, 0, '', ',') }}
                        </x-filament::badge>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-gray-500">
                    <x-heroicon-o-information-circle class="w-10 h-10 mx-auto mb-3" />
                    <p class="text-lg">No hay ofertas registradas</p>
                </div>
            @endforelse
        </div>
    </x-filament::modal>
</div>
