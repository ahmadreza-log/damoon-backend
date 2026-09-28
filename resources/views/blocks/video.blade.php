{{-- Video block: an Aparat or YouTube player under an optional heading. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $player = \App\Filament\Builder\Video::embed($data['url'] ?? null);
@endphp
<section class="damoon-block damoon-video" style="padding:1.5rem 0">
    @if (filled($data['heading'] ?? null))
        <h2 style="margin:0 0 1rem;font-size:1.5rem;font-weight:700">{{ $data['heading'] }}</h2>
    @endif
    @if ($player)
        <div style="position:relative;padding-top:56.25%;border-radius:.75rem;overflow:hidden">
            <iframe src="{{ $player }}" title="{{ $data['heading'] ?? 'ویدیو' }}" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
        </div>
    @elseif (filled($data['url'] ?? null))
        <p style="opacity:.75">این آدرس از آپارات یا یوتیوب نیست.</p>
    @endif
</section>
