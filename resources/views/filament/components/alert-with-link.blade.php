<div>
    {{ $message }}
    @if($url)
        <a href="{{ $url }}" class="text-primary-600 hover:underline">
            Haga clic aquí para visualizarlo
        </a>
    @endif
</div>
