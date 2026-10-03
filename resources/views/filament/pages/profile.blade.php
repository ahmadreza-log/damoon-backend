@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-panels::page>
    <div class="dp-profile">
        <section class="dp-profile-hero">
            <div class="dp-profile-cover" aria-hidden="true"></div>

            <div class="dp-profile-head">
                <img src="{{ $avatar }}" alt="{{ $name }}" class="dp-profile-avatar" loading="lazy">

                <div class="dp-profile-who">
                    <h2 class="dp-profile-name">{{ $name }}</h2>

                    <p class="dp-profile-job">{{ filled($job) ? $job : 'کاربر پنل مدیریت' }}</p>

                    @if ($ranks !== [])
                        <div class="dp-profile-ranks">
                            @foreach ($ranks as $rank)
                                <x-filament::badge :icon="Heroicon::OutlinedShieldCheck">{{ $rank }}</x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="dp-profile-meta">
                    @if ($joined)
                        <span>
                            <x-filament::icon :icon="Heroicon::OutlinedCalendarDays" class="dp-profile-meta-icon" />
                            عضو از {{ $joined }}
                        </span>
                    @endif

                    @if ($seen)
                        <span>
                            <x-filament::icon :icon="Heroicon::OutlinedClock" class="dp-profile-meta-icon" />
                            آخرین ورود {{ $seen }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="dp-profile-stats">
                @foreach ($stats as $stat)
                    <div class="dp-profile-stat">
                        <x-filament::icon :icon="$stat['icon']" class="dp-profile-stat-icon" />
                        <span class="dp-profile-stat-value">{{ $stat['value'] }}</span>
                        <span class="dp-profile-stat-label">{{ $stat['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="dp-profile-grid">
            <x-filament::section :icon="Heroicon::OutlinedIdentification" heading="اطلاعات حساب">
                <dl class="dp-profile-list">
                    @foreach ($account as $row)
                        <div class="dp-profile-row">
                            <dt>
                                <x-filament::icon :icon="$row['icon']" class="dp-profile-row-icon" />
                                {{ $row['label'] }}
                            </dt>
                            <dd @if ($row['ltr']) dir="ltr" @endif>{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>

            <x-filament::section :icon="Heroicon::OutlinedBriefcase" heading="مشخصات پرسنلی">
                <dl class="dp-profile-list">
                    @foreach ($personal as $row)
                        <div class="dp-profile-row">
                            <dt>{{ $row['label'] }}</dt>
                            <dd>{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>

            <x-filament::section
                :icon="Heroicon::OutlinedKey"
                heading="دسترسی‌ها"
                :description="$owner ? 'مالک سامانه به همه بخش‌ها دسترسی دارد.' : 'بخش‌هایی از پنل که می‌توانید باز کنید.'"
            >
                @if ($links === [])
                    <p class="dp-profile-empty">هنوز بخشی برای شما باز نشده است.</p>
                @else
                    <div class="dp-profile-links">
                        @foreach ($links as $link)
                            <a href="{{ $link['url'] }}" wire:navigate class="dp-profile-link">
                                {{ $link['label'] }}
                                <x-filament::icon :icon="Heroicon::ChevronLeft" class="dp-profile-link-icon" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section :icon="Heroicon::OutlinedNewspaper" heading="آخرین نوشته‌های من">
                @if ($mine && $latest !== [])
                    <x-slot name="afterHeader">
                        <x-filament::link :href="$mine" size="sm" :icon="Heroicon::ArrowLeft" icon-position="after">
                            همه نوشته‌های من
                        </x-filament::link>
                    </x-slot>
                @endif

                @if ($latest === [])
                    <div class="dp-profile-blank">
                        <p class="dp-profile-empty">هنوز نوشته‌ای با نام شما منتشر نشده است.</p>

                        @if ($write)
                            <x-filament::button :href="$write" tag="a" size="sm" :icon="Heroicon::OutlinedPencilSquare">
                                نوشتن اولین نوشته
                            </x-filament::button>
                        @endif
                    </div>
                @else
                    <ul class="dp-profile-posts">
                        @foreach ($latest as $post)
                            <li>
                                @if ($post['url'])
                                    <a href="{{ $post['url'] }}" wire:navigate class="dp-profile-post">
                                @else
                                    <div class="dp-profile-post">
                                @endif
                                        <span class="dp-profile-post-title">{{ $post['title'] }}</span>
                                        <span class="dp-profile-post-side">
                                            <x-filament::badge :color="$post['live'] ? 'success' : 'gray'" size="sm">
                                                {{ $post['live'] ? 'منتشرشده' : 'زمان‌بندی‌شده' }}
                                            </x-filament::badge>
                                            <span class="dp-profile-post-date">{{ $post['date'] }}</span>
                                        </span>
                                @if ($post['url'])
                                    </a>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
