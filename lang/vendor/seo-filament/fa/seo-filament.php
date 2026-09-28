<?php

/*
 * Persian strings for the SEO box from rankbeam/laravel-seo-filament.
 * Keys follow the package's English file. A key missing here falls back to English.
 */

return [

    'section' => [
        'title' => 'سئو',
        'description' => 'نمایش این صفحه در نتایج جستجو و هنگام اشتراک‌گذاری در شبکه‌های اجتماعی.',
    ],

    'fields' => [
        'title' => 'عنوان سئو',
        'description' => 'توضیحات سئو',
        'focus_keywords' => 'کلمات کلیدی اصلی',
        'focus_keywords_placeholder' => 'افزودن کلمهٔ کلیدی',
        'focus_keywords_help' => 'عبارت‌هایی که این صفحه باید با آن‌ها در جستجو دیده شود. اولین کلمه، کلمهٔ کلیدی اصلی است.',
        'canonical' => 'نشانی کنونیکال',
        'canonical_help' => 'اگر خالی بماند، نشانی خود صفحه بدون پارامترها استفاده می‌شود.',
        'robots' => 'دستور ربات‌ها',
        'robots_placeholder' => 'خودکار (پیش‌فرض سایت)',
        'og_image' => 'تصویر اشتراک‌گذاری',
        'og_image_help' => 'برای og:image و twitter:image استفاده می‌شود. اندازهٔ مناسب: :widthx:height پیکسل.',
        'counter' => ':length / :max نویسه',
    ],

    'robots_options' => [
        'index_follow' => 'ایندکس شود، پیوندها دنبال شوند',
        'index_nofollow' => 'ایندکس شود، پیوندها دنبال نشوند',
        'noindex_follow' => 'ایندکس نشود، پیوندها دنبال شوند',
        'noindex_nofollow' => 'ایندکس نشود، پیوندها دنبال نشوند',
    ],

    'sources' => [
        'manual' => 'دستی',
        'content' => 'از محتوا',
        'model_defaults' => 'پیش‌فرض این نوع',
        'global_defaults' => 'پیش‌فرض کلی',
        'config' => 'تنظیمات سایت',
        'url' => 'از نشانی صفحه',
        'none' => 'تعیین نشده',
    ],

    'indicators' => [
        'heading' => 'مقدارهای نهایی و منبع آن‌ها',
        'note' => 'منبع هر مقدار را بر اساس آخرین ذخیره نشان می‌دهد. برای به‌روزرسانی، فرم را ذخیره کنید.',
        'title' => 'عنوان',
        'description' => 'توضیحات',
        'og_image' => 'تصویر اشتراک‌گذاری',
        'robots' => 'ربات‌ها',
        'canonical' => 'نشانی کنونیکال',
    ],

    'preview' => [
        'tab_google' => 'گوگل',
        'tab_social' => 'شبکه‌های اجتماعی',
        'serp' => 'پیش‌نمایش نتیجهٔ جستجو',
        'social' => 'پیش‌نمایش اشتراک‌گذاری',
        'no_image' => 'بدون تصویر',
        'no_description' => 'توضیحی موجود نیست.',
        'note' => 'مطابق فرم فعلی، همراه با تغییرهای ذخیره‌نشده.',
        'title' => 'عنوان',
        'description' => 'توضیحات',
        'image' => 'تصویر',
    ],

    'schema' => [
        'section_title' => 'داده‌های ساختاریافته',
        'section_description' => 'JSON-LD از نوع schema.org برای نتایج غنی. از فیلدهای زیر ساخته و در صفحه گذاشته می‌شود، بدون نیاز به کد.',
        'auto_breadcrumb' => 'مسیر راهنمای خودکار',
        'auto_breadcrumb_help' => 'از زنجیرهٔ صفحه‌های مادر این صفحه یک BreadcrumbList می‌سازد.',
        'blocks' => 'بلوک‌های اسکیما',
        'add_block' => 'افزودن دادهٔ ساختاریافته',
        'type' => 'نوع',
        'type_faq' => 'پرسش و پاسخ',
        'type_product' => 'محصول',
        'questions' => 'پرسش‌ها',
        'add_question' => 'افزودن پرسش',
        'question' => 'پرسش',
        'answer' => 'پاسخ',
        'product_name' => 'نام محصول',
        'brand' => 'برند',
        'description' => 'توضیحات',
        'image_url' => 'نشانی تصویر',
        'sku' => 'شناسهٔ کالا',
        'price' => 'قیمت',
        'currency' => 'واحد پول',
        'availability' => 'موجودی',
    ],

    'locales' => [
        'heading' => 'زبان‌ها',
        'badge_tooltip' => ':count از :total فیلد برای :language پر شده است',
    ],
];
