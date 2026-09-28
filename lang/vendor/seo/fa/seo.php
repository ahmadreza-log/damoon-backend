<?php

/*
 * Persian strings from rankbeam/laravel-seo that show up in the panel's SEO box.
 * Only the editor warnings and status words are translated. The rest falls back to English.
 */

return [

    'warnings' => [
        'title_too_long' => 'عنوان :length نویسه است (بیشینهٔ پیشنهادی: :max). ممکن است در گوگل کوتاه نمایش داده شود.',
        'title_is_fallback' => 'عنوان سئو خالی است؛ عنوان خود محتوا استفاده می‌شود.',
        'description_too_long' => 'توضیحات :length نویسه است (بیشینهٔ پیشنهادی: :max). ممکن است کوتاه نمایش داده شود.',
        'description_is_fallback' => 'توضیحات سئو خالی است؛ از متن محتوا به‌طور خودکار ساخته می‌شود.',
        'no_image' => 'تصویری برای پیش‌نمایش اشتراک‌گذاری نیست. یک تصویر اشتراک‌گذاری یا تصویر شاخص بگذارید.',
        'image_is_fallback' => 'تصویر اشتراک‌گذاری جداگانه‌ای نیست؛ تصویر شاخص استفاده می‌شود.',
        'image_too_small' => 'تصویر خیلی کوچک است (:widthx:height). شبکه‌های اجتماعی دست‌کم :min_widthx:min_height پیکسل می‌خواهند.',
        'image_not_ideal' => 'تصویر :widthx:height پیکسل است. اندازهٔ مناسب برای شبکه‌های اجتماعی :ideal_widthx:ideal_height پیکسل است.',
    ],

    'status' => [
        'pass' => 'قبول',
        'warn' => 'هشدار',
        'fail' => 'رد',
        'skipped' => 'بررسی نشد',
    ],

    'severity' => [
        'critical' => 'بحرانی',
        'warning' => 'هشدار',
        'notice' => 'نکته',
    ],
];
