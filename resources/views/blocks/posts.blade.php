{{-- Latest articles block: the newest published articles, optionally from one category. Used by the panel preview and the site. --}}
@php
    $data = $block['data'] ?? [];
    $articles = \App\Filament\Builder\Posts::articles($data);
@endphp
<section class="damoon-block damoon-posts" style="padding:1.5rem 0">
    @if (filled($data['heading'] ?? null))
        <h2 style="margin:0 0 1rem;font-size:1.5rem;font-weight:700">{{ $data['heading'] }}</h2>
    @endif
    @if ($articles->isEmpty())
        <p style="opacity:.75">هنوز نوشته‌ای منتشر نشده است.</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem">
            @foreach ($articles as $article)
                <article style="border-radius:.75rem;overflow:hidden;border:1px solid rgba(127,127,127,.25)">
                    @if ($cover = \App\Filament\Builder\Posts::picture($article->cover))
                        <img src="{{ $cover }}" alt="{{ $article->title }}" style="width:100%;aspect-ratio:16/9;object-fit:cover">
                    @endif
                    <h3 style="margin:0;padding:.9rem 1rem;font-size:1rem;font-weight:700">{{ $article->title }}</h3>
                </article>
            @endforeach
        </div>
    @endif
</section>
