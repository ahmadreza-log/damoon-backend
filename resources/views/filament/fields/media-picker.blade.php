{{--
    The MediaPicker field (App\Filament\Fields\MediaPicker).

    Shows the chosen pictures as thumbnails, each with a remove button, followed by a
    dashed box that opens the media popup (the "pick" action). A single field with a
    picture shows a "change picture" button instead of the box. In a multiple field the
    thumbnails can be dragged; the new order is sent to the "reorder" action.
    Styles come from media-style.blade.php, injected into every panel page head.

    Extending:
    - Actions are addressed with schemaComponent = the field key, so several pickers on one form stay apart.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $items = $previews();
    $multiple = $isMultiple();
    $disabled = $isDisabled();
    $key = $getKey();
    $open = $getAction('pick')?->getLivewireClickHandler();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div @class(['dm-picker', 'dm-picker-round' => $isRound(), 'dm-picker-single' => ! $multiple])>
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
                        <img src="{{ $item['url'] }}" alt="{{ $item['name'] }}" loading="lazy" />

                        @unless ($disabled)
                            <div class="dm-picker-remove">
                                {{ ($getAction('remove'))(['path' => $path]) }}
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- The open button: an add box while more pictures fit, otherwise a change button. wire:loading blocks double clicks while the popup loads. --}}
        @if (! $disabled && $open)
            @if ($items === [] || $multiple)
                <button
                    type="button"
                    class="dm-picker-box"
                    wire:click="{{ $open }}"
                    wire:loading.attr="disabled"
                    wire:target="mountAction"
                >
                    {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedPhoto) }}
                    <span>{{ $items === [] ? 'برای انتخاب از رسانه‌ها یا بارگذاری تصویر کلیک کنید' : 'افزودن تصویر' }}</span>
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
                    <span>تغییر تصویر</span>
                </button>
            @endif
        @endif
    </div>
</x-dynamic-component>
