{{--
    The MediaPicker field (App\Filament\Fields\MediaPicker).

    Shows the chosen files as thumbnails, each with a remove button, followed by a
    dashed box that opens the media popup (the "pick" action). A single field with a
    file shows a "change" button instead of the box. In a multiple field the
    thumbnails can be dragged; the new order is sent to the "reorder" action.
    Pictures draw an img, videos a playable video, and audio files a card with a player.
    Styles come from media-style.blade.php, injected into every panel page head.

    Extending:
    - Actions are addressed with schemaComponent = the field key, so several pickers on one form stay apart.
--}}
@php
    use App\Models\Kind;
    use Filament\Support\Icons\Heroicon;

    $items = $previews();
    $multiple = $isMultiple();
    $disabled = $isDisabled();
    $kind = $getKind();
    $noun = $kind->label();
    $key = $getKey();
    $open = $getAction('pick')?->getLivewireClickHandler();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div @class(['dm-picker', 'dm-picker-'.$kind->value, 'dm-picker-round' => $isRound(), 'dm-picker-single' => ! $multiple])>
        @if ($items !== [])
            {{-- x-sortable is Filament's drag-and-drop helper; each item's x-sortable-item value is its path. --}}
            <ul
                class="dm-picker-list"
                @if ($multiple && ! $disabled)
                    x-sortable
                    data-sortable-animation-duration="200"
                    x-on:end.stop="$wire.mountAction('reorder', { items: $event.target.sortable.toArray() }, { schemaComponent: @js($key) })"
                @endif
            >
                @foreach ($items as $path => $item)
                    <li
                        wire:key="{{ $key }}.{{ $path }}"
                        x-sortable-item="{{ $path }}"
                        @if ($multiple && ! $disabled) x-sortable-handle @endif
                        class="dm-picker-item"
                        title="{{ $item['name'] }}"
                    >
                        @if ($kind === Kind::Image)
                            <img src="{{ $item['url'] }}" alt="{{ $item['name'] }}" loading="lazy" />
                        @elseif ($kind === Kind::Video)
                            <video src="{{ $item['url'] }}" preload="metadata" controls playsinline></video>
                        @else
                            <div class="dm-picker-track">
                                {{ \Filament\Support\generate_icon_html($kind->icon()) }}
                                <bdi class="dm-picker-name" dir="ltr">{{ $item['name'] }}</bdi>
                                <audio src="{{ $item['url'] }}" preload="none" controls></audio>
                            </div>
                        @endif

                        @unless ($disabled)
                            <div class="dm-picker-remove">
                                {{ ($getAction('remove'))(['path' => $path]) }}
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- The open button: an add box while more files fit, otherwise a change button. wire:loading blocks double clicks while the popup loads. --}}
        @if (! $disabled && $open)
            @if ($items === [] || $multiple)
                <button
                    type="button"
                    class="dm-picker-box"
                    wire:click="{{ $open }}"
                    wire:loading.attr="disabled"
                    wire:target="mountAction"
                >
                    {{ \Filament\Support\generate_icon_html($kind->icon()) }}
                    <span>{{ $items === [] ? 'برای انتخاب از رسانه‌ها یا بارگذاری '.$noun.' کلیک کنید' : 'افزودن '.$noun }}</span>
                </button>
            @else
                <button
                    type="button"
                    class="dm-picker-change"
                    wire:click="{{ $open }}"
                    wire:loading.attr="disabled"
                    wire:target="mountAction"
                >
                    {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedArrowPath) }}
                    <span>تغییر {{ $noun }}</span>
                </button>
            @endif
        @endif
    </div>
</x-dynamic-component>
