<?php

/**
 * Persian labels the Filament rich editor ships without.
 *
 * Laravel merges this file over filament-forms::components for the fa locale,
 * so only missing keys belong here.
 *
 * Extending:
 * - After a Filament update, compare rich_editor keys in the en file with fa and add the new ones.
 */
return [

    'rich_editor' => [

        'actions' => [

            'close_panel' => [
                'label' => 'بستن پنل',
            ],

            'text_color' => [
                'modal' => [
                    'form' => [
                        'color' => [
                            'options' => [
                                'slate' => 'خاکستری آبی',
                                'gray' => 'خاکستری',
                                'zinc' => 'روی',
                                'neutral' => 'خنثی',
                                'stone' => 'سنگی',
                                'mauve' => 'ارغوانی کم‌رنگ',
                                'olive' => 'زیتونی',
                                'mist' => 'مه',
                                'taupe' => 'قهوه‌ای خاکستری',
                                'red' => 'قرمز',
                                'orange' => 'نارنجی',
                                'amber' => 'کهربایی',
                                'yellow' => 'زرد',
                                'lime' => 'لیمویی',
                                'green' => 'سبز',
                                'emerald' => 'زمردی',
                                'teal' => 'سبزآبی',
                                'cyan' => 'فیروزه‌ای',
                                'sky' => 'آسمانی',
                                'blue' => 'آبی',
                                'indigo' => 'نیلی',
                                'violet' => 'بنفش',
                                'purple' => 'ارغوانی',
                                'fuchsia' => 'سرخابی',
                                'pink' => 'صورتی',
                                'rose' => 'گلی',
                            ],
                        ],
                    ],
                ],
            ],

        ],

        'custom_blocks' => [
            'actions' => [
                'delete' => [
                    'label' => 'حذف بلوک',
                ],
                'edit' => [
                    'label' => 'ویرایش بلوک',
                ],
            ],
            'no_search_results_message' => 'بلوکی با این جستجو پیدا نشد.',
            'search_label' => 'جستجوی بلوک‌ها',
            'search_prompt' => 'جستجوی بلوک‌ها',
        ],

        'toolbar' => [
            'label' => 'نوار ابزار ویرایشگر',
        ],

        'tools' => [
            'h4' => 'تیتر ۴',
            'h5' => 'تیتر ۵',
            'h6' => 'تیتر ۶',
        ],

    ],

];
