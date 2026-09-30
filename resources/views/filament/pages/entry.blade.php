{{--
    One inbox message, laid out like a mail client. ViewEntry renders it and supplies
    $entry, $sender, $rows, $answered, $details, $history, and $transcript.

    The panel has no custom Filament theme, so the layout classes are defined here with an
    em- prefix, using Filament's CSS variables (--gray-*, --primary-*) so they follow the
    panel colours and dark mode. The side column sits beside the answers only when the content
    area (not the window) is wide enough, so an open sidebar does not squeeze it.
    Buttons, badges, sections, and icons are Filament components.
    Copy buttons use Filament's $tooltip Alpine magic for the «کپی شد» hint.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    <style>
        .em-wrap { display: grid; gap: 1.5rem; container-type: inline-size; }
        .em-card { display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem; padding: 1.5rem; border-radius: .75rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: 1px solid rgb(3 7 18 / .05); }
        .em-avatar { flex: none; display: grid; place-items: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background: var(--primary-100); color: var(--primary-700); font-size: 1.375rem; font-weight: 700; }
        .em-who { flex: 1 1 18rem; min-width: 0; display: grid; gap: .5rem; }
        .em-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .em-name { font-size: 1.25rem; font-weight: 700; color: var(--gray-950); }
        .em-chip { display: inline-flex; align-items: center; gap: .375rem; padding: .25rem .375rem .25rem .625rem; border-radius: .5rem; background: var(--gray-50); color: var(--gray-700); font-size: .875rem; direction: ltr; }
        .em-chip a:hover { color: var(--primary-600); text-decoration: underline; }
        .em-muted-text { display: inline-flex; align-items: center; gap: .375rem; color: var(--gray-500); font-size: .875rem; }
        .em-cta { display: flex; flex-wrap: wrap; gap: .5rem; }

        .em-grid { display: grid; gap: 1.5rem; align-items: start; }
        @container (min-width: 52rem) {
            .em-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
            .em-side { position: sticky; top: 5rem; }
        }
        .em-side { display: grid; gap: 1.5rem; }

        .em-answers { display: grid; }
        .em-answer { position: relative; display: grid; gap: .375rem; padding: 1rem .25rem; border-top: 1px solid var(--gray-100); }
        .em-answer:first-child { border-top: 0; padding-top: 0; }
        .em-label { display: flex; align-items: center; gap: .5rem; font-size: .8125rem; font-weight: 500; color: var(--gray-500); }
        .em-type { display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: .375rem; background: var(--gray-100); color: var(--gray-500); }
        .em-kind { font-size: .6875rem; font-weight: 400; color: var(--gray-400); }
        .em-value { padding-inline-start: 2rem; color: var(--gray-950); font-size: .9375rem; line-height: 1.75; overflow-wrap: anywhere; }
        .em-value-long { white-space: pre-line; padding: .75rem 1rem; margin-inline-start: 2rem; border-radius: .5rem; background: var(--gray-50); }
        .em-link { color: var(--primary-600); }
        .em-link:hover { text-decoration: underline; }
        .em-extra { margin-inline-start: .5rem; font-size: .8125rem; color: var(--gray-500); }
        .em-value.em-empty, .em-empty { color: var(--gray-400); font-size: .875rem; }
        .em-pills { display: flex; flex-wrap: wrap; gap: .375rem; }
        .em-copy { position: absolute; top: .75rem; inset-inline-end: 0; opacity: 0; transition: opacity .15s; }
        .em-answer:first-child .em-copy { top: -.25rem; }
        .em-answer:hover .em-copy, .em-copy:focus-within { opacity: 1; }
        @media (hover: none) { .em-copy { opacity: 1; } }

        .em-file { display: flex; align-items: center; gap: .75rem; max-width: 26rem; padding: .625rem .75rem; border: 1px solid var(--gray-200); border-radius: .625rem; }
        .em-ext { flex: none; display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: .5rem; background: var(--primary-50); color: var(--primary-700); font-size: .6875rem; font-weight: 700; }
        .em-file-info { flex: 1; min-width: 0; display: grid; }
        .em-file-name { font-size: .875rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; direction: ltr; text-align: end; }
        .em-file-size { font-size: .75rem; color: var(--gray-500); }
        .em-file-gone { font-size: .75rem; color: var(--danger-600); }

        .em-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .875rem 1.5rem; }
        .em-fact { display: grid; gap: .25rem; }
        .em-fact dt { font-size: .75rem; color: var(--gray-500); }
        .em-fact dd { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; font-size: .875rem; color: var(--gray-950); overflow-wrap: anywhere; }
        .em-fact .em-ltr { direction: ltr; }

        .em-history { display: grid; margin: -.5rem; }
        .em-history a { display: grid; gap: .25rem; padding: .625rem .5rem; border-radius: .5rem; }
        .em-history a:hover { background: var(--gray-50); }
        .em-history-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; font-size: .75rem; color: var(--gray-500); }
        .em-history-text { font-size: .875rem; color: var(--gray-800); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .dark .em-card { background: var(--gray-900); outline-color: rgb(255 255 255 / .1); }
        .dark .em-avatar { background: color-mix(in oklab, var(--primary-500) 20%, transparent); color: var(--primary-300); }
        .dark .em-name, .dark .em-value, .dark .em-fact dd { color: #fff; }
        .dark .em-value.em-empty, .dark .em-empty { color: var(--gray-500); }
        .dark .em-chip, .dark .em-value-long { background: rgb(255 255 255 / .05); color: var(--gray-200); }
        .dark .em-answer { border-color: rgb(255 255 255 / .06); }
        .dark .em-type { background: rgb(255 255 255 / .06); color: var(--gray-400); }
        .dark .em-link { color: var(--primary-400); }
        .dark .em-file { border-color: rgb(255 255 255 / .1); }
        .dark .em-ext { background: color-mix(in oklab, var(--primary-500) 15%, transparent); color: var(--primary-300); }
        .dark .em-history a:hover { background: rgb(255 255 255 / .05); }
        .dark .em-history-text { color: var(--gray-200); }
    </style>

    <div class="em-wrap">
        <section class="em-card">
            <div class="em-avatar" aria-hidden="true">{{ $sender['initial'] }}</div>

            <div class="em-who">
                <div class="em-row">
                    <h2 class="em-name">{{ $sender['name'] }}</h2>
                    <x-filament::badge :color="$details['colour']">{{ $details['status'] }}</x-filament::badge>
                    <x-filament::badge color="gray" :icon="Heroicon::OutlinedClipboardDocumentList">{{ $details['form'] }}</x-filament::badge>
                </div>

                @if ($sender['email'] || $sender['phone'])
                    <div class="em-row">
                        @foreach (array_filter(['email' => $sender['email'], 'phone' => $sender['phone']]) as $kind => $contact)
                            <span class="em-chip">
                                <x-filament::icon :icon="$kind === 'email' ? Heroicon::OutlinedAtSymbol : Heroicon::OutlinedPhone" style="width: 1rem; height: 1rem; color: var(--gray-400)" />
                                <a href="{{ ($kind === 'email' ? 'mailto:' : 'tel:').$contact }}">{{ $contact }}</a>
                                <x-filament::icon-button
                                    :icon="Heroicon::OutlinedSquare2Stack"
                                    color="gray"
                                    size="sm"
                                    :label="'کپی '.($kind === 'email' ? 'ایمیل' : 'شماره')"
                                    x-on:click="window.navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($contact) }}); $tooltip('کپی شد', { theme: $store.theme, timeout: 1500 })"
                                />
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="em-muted-text">
                    <x-filament::icon :icon="Heroicon::OutlinedClock" style="width: 1rem; height: 1rem" />
                    <span>{{ $details['date'] }}</span>
                    @if ($details['ago'])
                        <span>·</span>
                        <span>{{ $details['ago'] }}</span>
                    @endif
                </div>
            </div>

            <div class="em-cta">
                @if ($sender['reply'])
                    <x-filament::button tag="a" :href="$sender['reply']" :icon="Heroicon::OutlinedArrowUturnLeft">
                        پاسخ با ایمیل
                    </x-filament::button>
                @endif
                @if ($sender['phone'])
                    <x-filament::button tag="a" :href="'tel:'.$sender['phone']" color="gray" :icon="Heroicon::OutlinedPhone">
                        تماس
                    </x-filament::button>
                @endif
            </div>
        </section>

        <div class="em-grid">
            <x-filament::section
                :icon="Heroicon::OutlinedChatBubbleLeftRight"
                heading="پاسخ‌ها"
                :description="count($rows) ? $answered.' از '.count($rows).' پرسش پاسخ داده شده' : null"
            >
                <x-slot name="afterHeader">
                    @if (count($rows))
                        <x-filament::button
                            color="gray"
                            size="sm"
                            :icon="Heroicon::OutlinedSquare2Stack"
                            x-on:click="window.navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($transcript) }}); $tooltip('همهٔ پاسخ‌ها کپی شد', { theme: $store.theme, timeout: 1500 })"
                        >
                            کپی همه
                        </x-filament::button>
                    @endif
                </x-slot>

                @if (count($rows))
                    <dl class="em-answers">
                        @foreach ($rows as $row)
                            <div class="em-answer">
                                <dt class="em-label">
                                    <span class="em-type"><x-filament::icon :icon="$row['icon']" style="width: .875rem; height: .875rem" /></span>
                                    <span>{{ $row['label'] }}</span>
                                    @if ($row['kind'] !== $row['label'])
                                        <span class="em-kind">{{ $row['kind'] }}</span>
                                    @endif
                                </dt>

                                @if ($row['file'])
                                    <dd class="em-value">
                                        <div class="em-file">
                                            <span class="em-ext">{{ \Illuminate\Support\Str::limit($row['file']['extension'], 4, '') }}</span>
                                            <span class="em-file-info">
                                                <span class="em-file-name" title="{{ $row['file']['name'] }}">{{ $row['file']['name'] }}</span>
                                                @if ($row['file']['present'])
                                                    <span class="em-file-size">{{ $row['file']['size'] ?? 'فایل پیوست' }}</span>
                                                @else
                                                    <span class="em-file-gone">فایل روی سرور پیدا نشد.</span>
                                                @endif
                                            </span>
                                            @if ($row['file']['present'])
                                                {{ ($this->downloadAction)(['index' => $row['index']]) }}
                                            @endif
                                        </div>
                                    </dd>
                                @elseif ($row['empty'])
                                    <dd class="em-value em-empty">بدون پاسخ</dd>
                                @elseif ($row['tick'] !== null)
                                    <dd class="em-value">
                                        <x-filament::badge
                                            :color="$row['tick'] ? 'success' : 'gray'"
                                            :icon="$row['tick'] ? Heroicon::OutlinedCheck : Heroicon::OutlinedXMark"
                                        >
                                            {{ $row['tick'] ? 'تأیید کرد' : 'تأیید نکرد' }}
                                        </x-filament::badge>
                                    </dd>
                                @elseif (count($row['items']))
                                    <dd class="em-value em-pills">
                                        @foreach ($row['items'] as $item)
                                            <x-filament::badge color="primary">{{ $item }}</x-filament::badge>
                                        @endforeach
                                    </dd>
                                @elseif ($row['multiline'])
                                    <dd class="em-value-long">{{ $row['text'] }}</dd>
                                @else
                                    <dd class="em-value">
                                        @if ($row['link'])
                                            <a
                                                class="em-link"
                                                href="{{ $row['link'] }}"
                                                @if ($row['external']) target="_blank" rel="noopener noreferrer nofollow" @endif
                                                dir="auto"
                                            >{{ $row['text'] }}</a>
                                        @elseif ($row['extra'])
                                            <span>{{ $row['extra'] }}</span>
                                            <span class="em-extra">({{ $row['text'] }})</span>
                                        @else
                                            <span dir="auto">{{ $row['text'] }}</span>
                                        @endif
                                    </dd>
                                @endif

                                @if (! $row['empty'] && ! $row['file'])
                                    <span class="em-copy">
                                        <x-filament::icon-button
                                            :icon="Heroicon::OutlinedSquare2Stack"
                                            color="gray"
                                            size="sm"
                                            :label="'کپی «'.$row['label'].'»'"
                                            x-on:click="window.navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($row['text']) }}); $tooltip('کپی شد', { theme: $store.theme, timeout: 1500 })"
                                        />
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="em-empty">این پیام پاسخی ندارد.</p>
                @endif
            </x-filament::section>

            <aside class="em-side">
                <x-filament::section :icon="Heroicon::OutlinedInformationCircle" heading="جزئیات ارسال">
                    <dl class="em-facts">
                        <div class="em-fact">
                            <dt>فرم</dt>
                            <dd>
                                @if ($details['formurl'])
                                    <x-filament::link :href="$details['formurl']">{{ $details['form'] }}</x-filament::link>
                                @else
                                    {{ $details['form'] }}
                                @endif
                                @if ($details['inbox'])
                                    <x-filament::link :href="$details['inbox']" color="gray" size="sm">(همهٔ پیام‌های این فرم)</x-filament::link>
                                @endif
                            </dd>
                        </div>

                        <div class="em-fact">
                            <dt>زمان ارسال</dt>
                            <dd>{{ $details['date'] }}</dd>
                        </div>

                        <div class="em-fact">
                            <dt>فرستنده</dt>
                            <dd>
                                @if ($entry->customer)
                                    @if ($sender['customer'])
                                        <x-filament::link :href="$sender['customer']" :icon="Heroicon::OutlinedUserCircle">{{ $entry->customer->username }}</x-filament::link>
                                    @else
                                        {{ $entry->customer->username }}
                                    @endif
                                    <x-filament::badge color="info" size="sm">مشتری</x-filament::badge>
                                @else
                                    <span>مهمان</span>
                                    <span class="em-muted-text">(وارد حساب نشده بود)</span>
                                @endif
                            </dd>
                        </div>

                        <div class="em-fact">
                            <dt>صفحهٔ ارسال</dt>
                            <dd>
                                @if ($details['pageurl'])
                                    <x-filament::link :href="$details['pageurl']" target="_blank" rel="noopener noreferrer nofollow" :icon="Heroicon::OutlinedArrowTopRightOnSquare" class="em-ltr">{{ $details['page'] }}</x-filament::link>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <div class="em-fact">
                            <dt>دستگاه</dt>
                            <dd title="{{ $details['agent'] }}">
                                <x-filament::icon :icon="$details['mobile'] ? Heroicon::OutlinedDevicePhoneMobile : Heroicon::OutlinedComputerDesktop" style="width: 1rem; height: 1rem; color: var(--gray-400)" />
                                {{ $details['browser'] ?? 'نامشخص' }}
                            </dd>
                        </div>

                        <div class="em-fact">
                            <dt>IP</dt>
                            <dd class="em-ltr" style="justify-content: flex-end">{{ $details['ip'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </x-filament::section>

                @if (count($history))
                    <x-filament::section :icon="Heroicon::OutlinedInboxStack" heading="پیام‌های دیگر این فرستنده">
                        <nav class="em-history">
                            @foreach ($history as $other)
                                <a href="{{ $other['url'] }}" wire:navigate>
                                    <span class="em-history-top">
                                        <span>{{ $other['form'] }} · {{ $other['ago'] }}</span>
                                        <x-filament::badge :color="$other['colour']" size="sm">{{ $other['status'] }}</x-filament::badge>
                                    </span>
                                    <span class="em-history-text">{{ $other['summary'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </x-filament::section>
                @endif
            </aside>
        </div>
    </div>
</x-filament-panels::page>
