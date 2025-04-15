@props(['icon', 'color', 'name'])

<div class="flex items-center gap-2">
    <x-dynamic-component 
        :component="$icon" 
        class="w-5 h-5 text-{{ $color }}-500" 
    />
    <span>{{ $name }}</span>
</div> 