{{-- resources/views/filament/components/document-link.blade.php --}}
@if($url)
    <a 
        href="{{ Storage::url($url) }}" 
        target="_blank" 
        class="inline-flex items-center gap-2 px-3 py-1 text-white transition rounded-full bg-primary-500 hover:bg-primary-600"
    >
        <x-heroicon-o-document-text class="w-4 h-4"/>
        <span>{{ $label }}</span>
    </a>
@else
    <span class="text-gray-500">No disponible</span>
@endif