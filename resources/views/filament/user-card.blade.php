@php
    $user = filament()->auth()->user();
    $ranks = $user instanceof \App\Models\User ? $user->ranks() : [];
    $role = filled($user?->job) ? $user->job : ($ranks[0] ?? 'کاربر پنل مدیریت');
@endphp

@if ($user)
    <div class="dp-usercard">
        <x-filament-panels::avatar.user :user="$user" size="lg" class="dp-usercard-avatar" />

        <div class="dp-usercard-text">
            <span class="dp-usercard-name">{{ filament()->getUserName($user) }}</span>
            <span class="dp-usercard-role">{{ $role }}</span>
            <span class="dp-usercard-mail" dir="ltr">{{ $user->email }}</span>
        </div>
    </div>
@endif
