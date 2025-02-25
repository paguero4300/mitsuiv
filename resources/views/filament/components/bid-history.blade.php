<div class="max-w-4xl mx-auto bg-white shadow-sm rounded-xl">
    <div class="p-6">
        <div class="space-y-4">
            {{-- Encabezados mejorados --}}
            <div class="flex items-center gap-6 pb-3 border-b border-gray-200">
                <div class="w-1/3 text-sm font-semibold text-gray-600">Usuario</div>
                <div class="w-1/4 text-sm font-semibold text-gray-600">Monto</div>
                <div class="flex-1 text-sm font-semibold text-gray-600">Fecha y Hora</div>
            </div>

            {{-- Lista de ofertas con scroll --}}
            <div class="pr-2 space-y-2 overflow-y-auto max-h-96">
                @forelse($bids as $bid)
                    <div class="flex items-center gap-6 p-4 text-sm transition-colors duration-200 rounded-lg hover:bg-gray-50">
                        {{-- Usuario con avatar --}}
                        <div class="flex items-center w-1/3 gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 rounded-full w-9 h-9 bg-primary-50">
                                <x-heroicon-o-user class="w-5 h-5 text-primary-500" />
                            </div>
                            <span class="font-medium truncate">{{ $bid->reseller->name }}</span>
                        </div>

                        {{-- Monto destacado --}}
                        <div class="w-1/4">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-success-700 bg-success-50">
                                <x-heroicon-o-banknotes class="w-4 h-4" />
                                <span class="font-semibold">US$ {{ number_format($bid->amount, 2) }}</span>
                            </div>
                        </div>

                        {{-- Fecha y hora --}}
                        <div class="flex items-center flex-1 gap-2 text-gray-500">
                            <x-heroicon-o-calendar class="w-4 h-4" />
                            <span>{{ $bid->created_at->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>
                @empty
                    {{-- Estado vacío mejorado --}}
                    <div class="flex flex-col items-center justify-center py-12 text-gray-500">
                        <x-heroicon-o-banknotes class="w-12 h-12 mb-3 text-gray-400" />
                        <p class="text-lg font-medium">No hay ofertas registradas</p>
                        <p class="text-sm">Sé el primero en hacer una oferta</p>
                    </div>
                @endforelse
            </div>

            {{-- Footer mejorado --}}
            @if($bids->count() > 0)
                <div class="pt-4 mt-2 border-t border-gray-200">
                    <p class="text-sm text-right text-gray-500">
                        Total de ofertas: {{ $bids->count() }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
