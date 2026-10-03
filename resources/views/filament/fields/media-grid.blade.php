{{--
    The MediaGrid field (App\Filament\Fields\MediaGrid), the library tab inside the media popup.

    Every library file of the picker's kind is a tile. Clicking a tile toggles it in the field's
    state, a list of paths. Alpine keeps that list entangled with Livewire, so choosing
    happens in the browser without a server round trip, and the search box filters
    tiles on each tile's pre-lowercased name, title, and place.

    Extending:
    - The tile data (path, url, name, title, place, search) comes from MediaGrid::tiles().
    - Picture tiles draw an img, video tiles a muted video frame, and audio tiles the kind's icon.
--}}
@php
    use App\Models\Kind;
    use Filament\Support\Icons\Heroicon;

    $tiles = $tiles();
    $kind = $getKind();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="dm-grid"
        {{-- toggle() adds or removes a path; without multiple, a new choice replaces the old one. --}}
        x-data="{
            state: $wire.$entangle(@js($statePath)),
            search: '',
            multiple: @js($isMultiple()),
            list() {
                return Array.isArray(this.state) ? this.state : []
            },
            has(path) {
                return this.list().includes(path)
            },
            toggle(path) {
                if (this.has(path)) {
                    this.state = this.list().filter((item) => item !== path)

                    return
                }

                this.state = this.multiple ? [...this.list(), path] : [path]
            },
        }"
    >
        @if ($tiles === [])
            <div class="dm-grid-empty">
                در رسانه‌ها {{ $kind->label() }} پیدا نشد. از زبانهٔ «بارگذاری فایل» {{ $kind->label() }} اضافه کنید.
            </div>
        @else
            <div class="dm-grid-bar">
                <input
                    type="search"
                    class="dm-grid-search"
                    placeholder="جستجو در رسانه‌ها"
                    x-model.debounce.200ms="search"
                />
                <span class="dm-grid-count" x-text="list().length ? `${list().length} {{ $kind->label() }} انتخاب شده` : ''"></span>
            </div>

            <div class="dm-grid-tiles">
                @foreach ($tiles as $tile)
                    <button
                        type="button"
                        class="dm-grid-tile dm-grid-tile-{{ $kind->value }}"
                        wire:key="{{ $statePath }}.{{ $tile['path'] }}"
                        x-show="! search || @js($tile['search']).includes(search.toLowerCase())"
                        x-on:click="toggle(@js($tile['path']))"
                        x-bind:class="{ 'dm-grid-tile-on': has(@js($tile['path'])) }"
                        title="{{ $tile['title'] !== '' ? $tile['title'] : $tile['name'] }} — {{ $tile['place'] }}"
                    >
                        @if ($kind === Kind::Image)
                            <img src="{{ $tile['url'] }}" alt="{{ $tile['name'] }}" loading="lazy" />
                        @elseif ($kind === Kind::Video)
                            <video src="{{ $tile['url'] }}#t=0.5" preload="metadata" muted playsinline></video>
                            <span class="dm-grid-kind">{{ \Filament\Support\generate_icon_html(Heroicon::Play) }}</span>
                        @else
                            <span class="dm-grid-icon">{{ \Filament\Support\generate_icon_html($kind->icon()) }}</span>
                        @endif
                        <span class="dm-grid-check">
                            {{ \Filament\Support\generate_icon_html(Heroicon::Check) }}
                        </span>
                        <span class="dm-grid-name" dir="auto">{{ $tile['title'] !== '' ? $tile['title'] : $tile['name'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</x-dynamic-component>
