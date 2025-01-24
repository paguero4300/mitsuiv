{{-- resources/views/filament/components/vehicle-gallery.blade.php --}}
@php
    $vehicle = \App\Models\Vehicle::with('images')->find($getRecord()->vehicle_id);
    $images = $vehicle?->images ?? collect();
    $uniqueId = uniqid('gallery-');
@endphp

@pushOnce('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lightgallery.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lg-zoom.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lg-thumbnail.min.css">
<style>
    .gallery-container {
        background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
        border-radius: 0.75rem;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1rem;
    }

    .gallery-item {
        position: relative;
        border-radius: 0.5rem;
        overflow: hidden;
        cursor: pointer;
        aspect-ratio: 4/3;
    }

    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .gallery-item:hover img {
        transform: scale(1.05);
    }

    .main-image-label {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background-color: #0ea5e9;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        z-index: 10;
    }

    .zoom-hint {
        position: absolute;
        bottom: 0.5rem;
        left: 50%;
        transform: translateX(-50%);
        background-color: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .gallery-item:hover .zoom-hint {
        opacity: 1;
    }

    /* LightGallery personalización */
    .lg-backdrop {
        background-color: rgba(0, 0, 0, 0.9);
    }

    .lg-toolbar,
    .lg-outer {
        z-index: 99999 !important;
    }

    .lg-counter {
        top: -45px;
    }

    .lg-thumb-outer {
        background-color: rgba(0, 0, 0, 0.75);
    }

    /* Estilo para la imagen principal más grande */
    .gallery-item.main-image {
        grid-column: span 2;
        grid-row: span 2;
    }

    @media (max-width: 640px) {
        .gallery-item.main-image {
            grid-column: span 1;
            grid-row: span 1;
        }
    }

    /* Animaciones suaves */
    .lg-css3.lg-fade .lg-item {
        opacity: 0;
        transition: opacity 0.15s ease-in-out;
    }

    .lg-css3.lg-fade .lg-item.lg-current {
        opacity: 1;
    }
</style>
@endPushOnce

<div class="gallery-container">
    @if($images->count() > 0)
        <div id="{{ $uniqueId }}" class="gallery-grid">
            @foreach($images as $image)
                <div class="gallery-item {{ $image->is_main ? 'main-image' : '' }}" 
                     data-src="{{ Storage::url($image->path) }}"
                     data-sub-html="<h4>Imagen {{ $loop->iteration }}</h4>">
                    <img src="{{ Storage::url($image->path) }}" 
                         alt="Imagen del vehículo"
                         loading="lazy">
                    @if($image->is_main)
                        <div class="main-image-label">Principal</div>
                    @endif
                    <div class="zoom-hint">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                        </svg>
                        Click para ampliar
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex items-center justify-center h-64">
            <div class="text-center text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p>No hay imágenes disponibles</p>
            </div>
        </div>
    @endif
</div>

@pushOnce('scripts')
<script src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/lightgallery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/plugins/zoom/lg-zoom.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/plugins/thumbnail/lg-thumbnail.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    class GalleryController {
        constructor(elementId) {
            this.elementId = elementId;
            this.gallery = null;
            this.isModalOpen = false;
        }

        init() {
            const element = document.getElementById(this.elementId);
            if (!element) return;

            this.gallery = lightGallery(element, {
                speed: 500,
                plugins: [lgZoom, lgThumbnail],
                closeOnTap: true,
                hideScrollbar: true,
                download: false,
                thumbnail: true,
                animateThumb: true,
                zoomFromOrigin: true,
                allowMediaOverlap: false,
                toggleThumb: true,
                thumbWidth: 80,
                thumbHeight: 60,
                loadYouTubeThumbnail: false,
                licenseKey: 'your-license-key',
                mobileSettings: {
                    controls: true,
                    showCloseIcon: true,
                    download: false,
                }
            });

            // Manejar eventos del modal de Filament
            window.addEventListener('modal-open', () => {
                this.isModalOpen = true;
                if (this.gallery) {
                    this.gallery.destroy();
                    this.init();
                }
            });

            window.addEventListener('modal-closed', () => {
                this.isModalOpen = false;
                if (this.gallery) {
                    this.gallery.destroy();
                    this.init();
                }
            });
        }

        destroy() {
            if (this.gallery) {
                this.gallery.destroy();
                this.gallery = null;
            }
        }
    }

    // Inicializar todas las galerías en la página
    const galleries = {};
    document.querySelectorAll('.gallery-grid').forEach(element => {
        const galleryId = element.id;
        if (galleryId) {
            galleries[galleryId] = new GalleryController(galleryId);
            galleries[galleryId].init();
        }
    });

    // Observar cambios en el DOM para reinicializar galerías cuando sea necesario
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && node.classList.contains('gallery-grid')) {
                        const galleryId = node.id;
                        if (galleryId && !galleries[galleryId]) {
                            galleries[galleryId] = new GalleryController(galleryId);
                            galleries[galleryId].init();
                        }
                    }
                });
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
</script>
@endPushOnce