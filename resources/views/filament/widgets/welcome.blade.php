<x-filament-widgets::widget class="dp-welcome-widget">
    <div class="dp-welcome">
        <div class="dp-welcome-main">
            <p class="dp-welcome-date">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" class="dp-welcome-date-icon" />
                {{ $date }}
            </p>

            <h2 class="dp-welcome-title">{{ $greeting }}{{ $name === '' ? '' : '، '.$name }}</h2>

            <p class="dp-welcome-text">به پنل مدیریت {{ $brand }} خوش آمدید. از اینجا می‌توانید محتوا، فرم‌ها و پیام‌ها را مدیریت کنید.</p>
        </div>

        @if ($shortcuts !== [])
            <nav class="dp-welcome-links" aria-label="میان‌برها">
                @foreach ($shortcuts as $shortcut)
                    <a href="{{ $shortcut['url'] }}" wire:navigate class="dp-welcome-link">
                        <x-filament::icon :icon="$shortcut['icon']" class="dp-welcome-link-icon" />
                        <span>{{ $shortcut['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </div>
</x-filament-widgets::widget>
