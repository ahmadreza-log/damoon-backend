{{--
    The panel logo: a brand-coloured tile with the first letter of the site name, then the
    name and «پنل مدیریت». AdminPanelProvider passes it to brandLogo, so it shows in the
    topbar and on the login card; resources/css/panel.css styles the dp-brand classes.
--}}
@php
    $name = \App\Models\Setting::brand();
@endphp

<span class="dp-brand">
    <span class="dp-brand-mark" aria-hidden="true">{{ mb_substr($name, 0, 1) }}</span>
    <span class="dp-brand-text">
        <span class="dp-brand-name">{{ $name }}</span>
        <span class="dp-brand-tag">پنل مدیریت</span>
    </span>
</span>
