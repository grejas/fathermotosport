{{--
    Ventana "Ver" de una reseña en el panel. Los botones llaman a métodos de ListReviews.
    Estilos en línea: el CSS del panel solo trae las clases que usa Filament.
--}}
@php
    $fotos = $review->images;
    $pendientes = $fotos->where('status', \App\Models\ReviewImage::PENDIENTE)->count();
@endphp

<div style="display: grid; gap: 1rem;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem;">
        <span style="color: #f59e0b; font-size: 1.1rem; letter-spacing: .1em;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
        <strong>{{ $review->user?->full_name ?? 'Cliente' }}</strong>
        <span style="opacity: .7;">{{ $review->created_at?->format('d/m/Y H:i') }}</span>
        @if ($review->is_approved)
            <x-filament::badge color="success">Aprobada (se ve en la web)</x-filament::badge>
        @else
            <x-filament::badge color="warning">Sin aprobar (no se ve en la web)</x-filament::badge>
        @endif
    </div>

    @if ($review->product)
        <div style="opacity: .8;">Producto: {{ $review->product->name }}</div>
    @endif

    @if ($review->title)
        <div style="font-weight: 600;">{{ $review->title }}</div>
    @endif
    <div style="white-space: pre-line;">{{ $review->comment ?: 'Sin comentario.' }}</div>

    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;">
        {{-- La reseña y sus fotos pendientes de una vez; los botones de al lado siguen para ir por partes. --}}
        @if (! $review->is_approved || $pendientes > 0)
            <x-filament::button color="success" icon="heroicon-o-check-badge"
                wire:click="aprobarTodo('{{ $review->id }}')">
                Aprobar todo
            </x-filament::button>
        @endif
        @if ($review->is_approved)
            <x-filament::button color="danger" size="sm" icon="heroicon-o-x-mark"
                wire:click="cambiarAprobacion('{{ $review->id }}', false)">
                Retirar reseña
            </x-filament::button>
        @else
            <x-filament::button color="success" size="sm" icon="heroicon-o-check"
                wire:click="cambiarAprobacion('{{ $review->id }}', true)">
                Aprobar reseña
            </x-filament::button>
        @endif
        @if ($pendientes > 0)
            <x-filament::button color="success" size="sm" outlined icon="heroicon-o-photo"
                wire:click="aprobarTodasLasFotos('{{ $review->id }}')">
                Aprobar las {{ $pendientes }} fotos pendientes
            </x-filament::button>
        @endif
    </div>

    @if ($fotos->isEmpty())
        <div style="opacity: .7;">Esta reseña no tiene fotos.</div>
    @else
        <div style="font-size: .85rem; opacity: .75;">
            Una foto se publica solo si está aprobada y la reseña también. Las pendientes no tienen URL pública.
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
            @foreach ($fotos as $foto)
                <div wire:key="foto-{{ $foto->id }}" style="border: 1px solid rgba(128,128,128,.3); border-radius: .75rem; overflow: hidden;">
                    <a href="{{ route('panel.resenas.foto', [$foto, 'grande']) }}" target="_blank" rel="noopener">
                        <img src="{{ route('panel.resenas.foto', [$foto, 'miniatura']) }}" alt="Foto {{ $loop->iteration }}"
                            style="display: block; width: 100%; aspect-ratio: 1; object-fit: cover; background: #111;">
                    </a>
                    <div style="display: grid; gap: .5rem; padding: .6rem;">
                        <div style="display: flex; align-items: center; gap: .5rem;">
                            <strong style="font-size: .85rem;">Foto {{ $loop->iteration }}</strong>
                            @if ($foto->estaAprobada())
                                <x-filament::badge color="success">Aprobada</x-filament::badge>
                            @else
                                <x-filament::badge color="warning">Pendiente</x-filament::badge>
                            @endif
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: .4rem;">
                            @unless ($foto->estaAprobada())
                                <x-filament::button color="success" size="xs" icon="heroicon-o-check"
                                    wire:click="aprobarFoto('{{ $foto->id }}')">
                                    Aprobar foto
                                </x-filament::button>
                            @endunless
                            <x-filament::button color="danger" size="xs" outlined icon="heroicon-o-trash"
                                wire:click="eliminarFoto('{{ $foto->id }}')"
                                wire:confirm="¿Eliminar esta foto? Se borra del servidor y no se puede deshacer.">
                                Eliminar foto
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
