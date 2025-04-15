@props(['message', 'duration'])

<div class="flex items-center gap-2">
    <x-heroicon-o-clock class="w-5 h-5 text-primary-500" />
    <div class="flex flex-col">
        <span class="text-sm text-gray-600">{{ $message }}</span>
        @if($duration)
            <span class="font-medium text-primary-600">{{ $duration }}</span>
        @endif
    </div>
</div> 