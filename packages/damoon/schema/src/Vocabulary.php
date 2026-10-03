<?php

namespace Damoon\Schema;

use Damoon\Schema\Contracts\Schemable;
use Illuminate\Support\Arr;

/**
 * The schema.org types the package offers, their fields and default values, and the renderer.
 *
 * Each type has a Persian label, a short note on what it is for, and its fields. A field key
 * is the schema.org property, with a dot for a nested node: address.streetAddress becomes
 * "address": {"@type": "PostalAddress", "streetAddress": ...}, and NODES gives each nested
 * node its @type. The key type is the exception: it picks a more specific @type, such as
 * BlogPosting instead of Article. Field kinds are text (the default), textarea, list, select,
 * faq, toggle, and code; the panel draws them in Filament\SchemaEditor. Types marked record
 * only make sense on a record, so the site editor does not offer them.
 *
 * Values may hold the placeholders in PLACEHOLDERS. render fills them through Schemas::values,
 * drops what is left empty, and returns null when nothing remains. FAQPage, BreadcrumbList,
 * and Custom are built by their own methods.
 *
 * Extending:
 * - A new type is one more entry in types; a new nested node is one more entry in NODES.
 * - A new placeholder is one more entry in PLACEHOLDERS, filled by the site callback or the record.
 */
class Vocabulary
{
    /** The context every document is written in. */
    public const CONTEXT = 'https://schema.org';

    /** The @type of each nested node, parents before their children. */
    public const NODES = [
        'address' => 'PostalAddress',
        'geo' => 'GeoCoordinates',
        'author' => 'Person',
        'publisher' => 'Organization',
        'publisher.logo' => 'ImageObject',
        'brand' => 'Brand',
        'offers' => 'Offer',
        'provider' => 'Organization',
        'location' => 'Place',
        'location.address' => 'PostalAddress',
        'organizer' => 'Organization',
        'worksFor' => 'Organization',
        'potentialAction' => 'SearchAction',
        'isPartOf' => 'WebSite',
    ];

    /** The Persian heading of each group of nested fields in the panel. */
    public const GROUPS = [
        'address' => 'نشانی',
        'geo' => 'مختصات جغرافیایی',
        'author' => 'نویسنده',
        'publisher' => 'ناشر',
        'brand' => 'برند',
        'offers' => 'پیشنهاد فروش',
        'provider' => 'ارائه‌دهنده',
        'location' => 'محل برگزاری',
        'organizer' => 'برگزارکننده',
        'worksFor' => 'محل کار',
        'potentialAction' => 'جعبهٔ جستجوی گوگل',
        'isPartOf' => 'بخشی از سایت',
    ];

    /** Placeholders a value may hold, with what each one becomes. Those marked record are empty for site-wide schemas. */
    public const PLACEHOLDERS = [
        '{site_name}' => ['label' => 'نام سایت از تنظیمات عمومی', 'record' => false],
        '{site_url}' => ['label' => 'نشانی سایت از تنظیمات عمومی', 'record' => false],
        '{site_description}' => ['label' => 'توضیح سایت از تنظیمات عمومی', 'record' => false],
        '{year}' => ['label' => 'سال جاری میلادی', 'record' => false],
        '{title}' => ['label' => 'عنوان محتوا', 'record' => true],
        '{description}' => ['label' => 'توضیح سئو، یا آغاز متن محتوا', 'record' => true],
        '{url}' => ['label' => 'نشانی محتوا در سایت', 'record' => true],
        '{image}' => ['label' => 'تصویر شبکه‌های اجتماعی یا کاور محتوا', 'record' => true],
        '{published}' => ['label' => 'تاریخ انتشار (ISO 8601)', 'record' => true],
        '{modified}' => ['label' => 'تاریخ آخرین ویرایش (ISO 8601)', 'record' => true],
        '{author}' => ['label' => 'نام نویسنده', 'record' => true],
        '{section}' => ['label' => 'نام بخش محتوا، مثل نوشته‌ها', 'record' => true],
    ];

    /** Product and event availability values, with their Persian labels. */
    public const AVAILABILITY = [
        'https://schema.org/InStock' => 'موجود',
        'https://schema.org/OutOfStock' => 'ناموجود',
        'https://schema.org/PreOrder' => 'پیش‌فروش',
        'https://schema.org/BackOrder' => 'سفارشی',
        'https://schema.org/Discontinued' => 'توقف تولید',
    ];

    /**
     * Every type the package offers, keyed by its schema.org name.
     *
     * @return array<string, array{label: string, about: string, record?: bool, fields: array<string, array<string, mixed>>, fixed?: array<string, string>}>
     */
    public static function types(): array
    {
        return [
            'Organization' => [
                'label' => 'سازمان',
                'about' => 'شناسنامهٔ شرکت در نتایج گوگل: نام، لوگو، راه‌های تماس و شبکه‌های اجتماعی. معمولاً یک بار برای کل سایت.',
                'fields' => [
                    'name' => ['label' => 'نام', 'default' => '{site_name}'],
                    'alternateName' => ['label' => 'نام دیگر', 'help' => 'مثلاً نام انگلیسی شرکت.'],
                    'url' => ['label' => 'نشانی سایت', 'default' => '{site_url}'],
                    'logo' => ['label' => 'نشانی لوگو', 'help' => 'نشانی کامل تصویر، دست‌کم ۱۱۲ در ۱۱۲ پیکسل.'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{site_description}'],
                    'email' => ['label' => 'ایمیل'],
                    'telephone' => ['label' => 'تلفن', 'help' => 'با کد کشور، مثل ‎+982112345678.'],
                    ...self::address('address'),
                    'sameAs' => ['label' => 'صفحه‌های شبکه‌های اجتماعی', 'kind' => 'list', 'help' => 'نشانی کامل هر صفحه، مثل اینستاگرام یا لینکدین.'],
                ],
            ],
            'LocalBusiness' => [
                'label' => 'کسب‌وکار محلی',
                'about' => 'برای فروشگاه یا دفتری که مشتری به آن سر می‌زند: نشانی، ساعت کاری و موقعیت روی نقشه.',
                'fields' => [
                    'type' => ['label' => 'نوع کسب‌وکار', 'kind' => 'select', 'default' => 'LocalBusiness', 'options' => [
                        'LocalBusiness' => 'کسب‌وکار محلی',
                        'Store' => 'فروشگاه',
                        'ProfessionalService' => 'خدمات تخصصی',
                        'Restaurant' => 'رستوران',
                        'MedicalBusiness' => 'مرکز درمانی',
                        'AutomotiveBusiness' => 'خدمات خودرو',
                        'HomeAndConstructionBusiness' => 'ساختمان و تأسیسات',
                    ]],
                    'name' => ['label' => 'نام', 'default' => '{site_name}'],
                    'url' => ['label' => 'نشانی سایت', 'default' => '{site_url}'],
                    'image' => ['label' => 'نشانی تصویر', 'help' => 'عکسی از محل کسب‌وکار؛ گوگل آن را لازم می‌داند.'],
                    'logo' => ['label' => 'نشانی لوگو'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{site_description}'],
                    'telephone' => ['label' => 'تلفن', 'help' => 'با کد کشور، مثل ‎+982112345678.'],
                    'email' => ['label' => 'ایمیل'],
                    'priceRange' => ['label' => 'بازهٔ قیمت', 'help' => 'مثل $$ یا «۱۰۰ تا ۵۰۰ هزار تومان».'],
                    'openingHours' => ['label' => 'ساعت کاری', 'kind' => 'list', 'help' => 'هر بازه جدا، مثل Sa-We 09:00-17:00.'],
                    ...self::address('address'),
                    'geo.latitude' => ['label' => 'عرض جغرافیایی', 'help' => 'مثل 35.6892'],
                    'geo.longitude' => ['label' => 'طول جغرافیایی', 'help' => 'مثل 51.3890'],
                    'sameAs' => ['label' => 'صفحه‌های شبکه‌های اجتماعی', 'kind' => 'list'],
                ],
            ],
            'WebSite' => [
                'label' => 'وب‌سایت',
                'about' => 'نام سایت در نتایج گوگل و جعبهٔ جستجوی داخل نتیجه. یک بار برای کل سایت.',
                'fields' => [
                    'name' => ['label' => 'نام سایت', 'default' => '{site_name}'],
                    'alternateName' => ['label' => 'نام دیگر'],
                    'url' => ['label' => 'نشانی', 'default' => '{site_url}'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{site_description}'],
                    'inLanguage' => ['label' => 'زبان', 'default' => 'fa-IR'],
                    'potentialAction.target' => ['label' => 'نشانی جستجو', 'help' => 'نشانی صفحهٔ جستجو با {search_term_string} به جای عبارت، مثل {site_url}/search?q={search_term_string}. اگر خالی بماند، جعبهٔ جستجو ساخته نمی‌شود.'],
                ],
                'fixed' => [
                    'potentialAction.query-input' => 'required name=search_term_string',
                ],
            ],
            'WebPage' => [
                'label' => 'صفحهٔ وب',
                'about' => 'معرفی خود صفحه: عنوان، توضیح، تصویر و تاریخ‌ها. برای برگه‌ها، پروژه‌ها و هر صفحهٔ دیگری که نوع خاص‌تری ندارد.',
                'record' => true,
                'fields' => [
                    'type' => ['label' => 'نوع صفحه', 'kind' => 'select', 'default' => 'WebPage', 'options' => [
                        'WebPage' => 'صفحهٔ وب',
                        'AboutPage' => 'دربارهٔ ما',
                        'ContactPage' => 'تماس با ما',
                        'CollectionPage' => 'فهرست',
                        'ItemPage' => 'معرفی یک مورد',
                    ]],
                    'name' => ['label' => 'عنوان', 'default' => '{title}'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{description}'],
                    'url' => ['label' => 'نشانی', 'default' => '{url}'],
                    'image' => ['label' => 'تصویر', 'default' => '{image}'],
                    'datePublished' => ['label' => 'تاریخ انتشار', 'default' => '{published}'],
                    'dateModified' => ['label' => 'تاریخ ویرایش', 'default' => '{modified}'],
                    'inLanguage' => ['label' => 'زبان', 'default' => 'fa-IR'],
                    'isPartOf.name' => ['label' => 'نام سایت', 'default' => '{site_name}'],
                    'isPartOf.url' => ['label' => 'نشانی سایت', 'default' => '{site_url}'],
                ],
            ],
            'Article' => [
                'label' => 'مقاله',
                'about' => 'برای نوشته‌ها: عنوان، تصویر، تاریخ‌ها و نویسنده در نتایج گوگل و Discover.',
                'record' => true,
                'fields' => [
                    'type' => ['label' => 'نوع مقاله', 'kind' => 'select', 'default' => 'BlogPosting', 'options' => [
                        'Article' => 'مقاله',
                        'BlogPosting' => 'نوشتهٔ وبلاگ',
                        'NewsArticle' => 'خبر',
                        'TechArticle' => 'مقالهٔ فنی',
                    ]],
                    'headline' => ['label' => 'عنوان', 'default' => '{title}'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{description}'],
                    'image' => ['label' => 'تصویر', 'default' => '{image}'],
                    'mainEntityOfPage' => ['label' => 'نشانی صفحه', 'default' => '{url}'],
                    'datePublished' => ['label' => 'تاریخ انتشار', 'default' => '{published}'],
                    'dateModified' => ['label' => 'تاریخ ویرایش', 'default' => '{modified}'],
                    'inLanguage' => ['label' => 'زبان', 'default' => 'fa-IR'],
                    'author.name' => ['label' => 'نام', 'default' => '{author}'],
                    'author.url' => ['label' => 'نشانی صفحهٔ نویسنده'],
                    'publisher.name' => ['label' => 'نام', 'default' => '{site_name}'],
                    'publisher.logo.url' => ['label' => 'نشانی لوگو'],
                ],
            ],
            'Brand' => [
                'label' => 'برند',
                'about' => 'معرفی یک برند با نام، لوگو و توضیح. برای صفحهٔ هر برند.',
                'record' => true,
                'fields' => [
                    'name' => ['label' => 'نام', 'default' => '{title}'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{description}'],
                    'logo' => ['label' => 'لوگو', 'default' => '{image}'],
                    'url' => ['label' => 'نشانی', 'default' => '{url}'],
                    'slogan' => ['label' => 'شعار'],
                    'sameAs' => ['label' => 'سایت رسمی و صفحه‌های برند', 'kind' => 'list'],
                ],
            ],
            'Product' => [
                'label' => 'محصول',
                'about' => 'قیمت، موجودی و برند در نتیجهٔ جستجو. برای برندها یا پروژه‌هایی که محصول معرفی می‌کنند.',
                'fields' => [
                    'name' => ['label' => 'نام', 'default' => '{title}'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{description}'],
                    'image' => ['label' => 'تصویر', 'default' => '{image}'],
                    'url' => ['label' => 'نشانی', 'default' => '{url}'],
                    'sku' => ['label' => 'شناسهٔ کالا (SKU)'],
                    'brand.name' => ['label' => 'نام برند', 'default' => '{site_name}'],
                    'offers.price' => ['label' => 'قیمت', 'help' => 'فقط عدد، بدون جداکننده.'],
                    'offers.priceCurrency' => ['label' => 'واحد پول', 'default' => 'IRR', 'help' => 'کد سه‌حرفی؛ ریال IRR است.'],
                    'offers.availability' => ['label' => 'موجودی', 'kind' => 'select', 'options' => self::AVAILABILITY],
                    'offers.url' => ['label' => 'نشانی خرید', 'default' => '{url}'],
                ],
            ],
            'Service' => [
                'label' => 'خدمت',
                'about' => 'خدمتی که شرکت ارائه می‌دهد، مثل طراحی یا نصب، با ارائه‌دهنده و محدودهٔ خدمت.',
                'fields' => [
                    'name' => ['label' => 'نام', 'default' => '{title}'],
                    'serviceType' => ['label' => 'نوع خدمت'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{description}'],
                    'image' => ['label' => 'تصویر', 'default' => '{image}'],
                    'url' => ['label' => 'نشانی', 'default' => '{url}'],
                    'areaServed' => ['label' => 'محدودهٔ خدمت', 'help' => 'مثل ایران یا تهران.'],
                    'provider.name' => ['label' => 'نام', 'default' => '{site_name}'],
                    'provider.url' => ['label' => 'نشانی', 'default' => '{site_url}'],
                ],
            ],
            'Person' => [
                'label' => 'شخص',
                'about' => 'معرفی یک شخص، مثل مدیر شرکت یا نویسنده، با سمت و صفحه‌هایش.',
                'fields' => [
                    'name' => ['label' => 'نام'],
                    'jobTitle' => ['label' => 'سمت'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea'],
                    'image' => ['label' => 'نشانی تصویر'],
                    'url' => ['label' => 'نشانی صفحه'],
                    'email' => ['label' => 'ایمیل'],
                    'telephone' => ['label' => 'تلفن'],
                    'worksFor.name' => ['label' => 'نام', 'default' => '{site_name}'],
                    'sameAs' => ['label' => 'صفحه‌های شبکه‌های اجتماعی', 'kind' => 'list'],
                ],
            ],
            'Event' => [
                'label' => 'رویداد',
                'about' => 'همایش، نمایشگاه یا وبینار با تاریخ، محل و بلیت در نتایج گوگل.',
                'fields' => [
                    'name' => ['label' => 'نام', 'default' => '{title}'],
                    'description' => ['label' => 'توضیح', 'kind' => 'textarea', 'default' => '{description}'],
                    'image' => ['label' => 'تصویر', 'default' => '{image}'],
                    'url' => ['label' => 'نشانی', 'default' => '{url}'],
                    'startDate' => ['label' => 'آغاز', 'help' => 'تاریخ میلادی، مثل 2026-10-20T18:00+03:30.'],
                    'endDate' => ['label' => 'پایان'],
                    'eventStatus' => ['label' => 'وضعیت', 'kind' => 'select', 'default' => 'https://schema.org/EventScheduled', 'options' => [
                        'https://schema.org/EventScheduled' => 'برگزار می‌شود',
                        'https://schema.org/EventPostponed' => 'به تعویق افتاده',
                        'https://schema.org/EventRescheduled' => 'زمان تازه',
                        'https://schema.org/EventCancelled' => 'لغو شده',
                    ]],
                    'eventAttendanceMode' => ['label' => 'شیوهٔ حضور', 'kind' => 'select', 'default' => 'https://schema.org/OfflineEventAttendanceMode', 'options' => [
                        'https://schema.org/OfflineEventAttendanceMode' => 'حضوری',
                        'https://schema.org/OnlineEventAttendanceMode' => 'آنلاین',
                        'https://schema.org/MixedEventAttendanceMode' => 'حضوری و آنلاین',
                    ]],
                    'location.name' => ['label' => 'نام محل'],
                    ...self::address('location.address'),
                    'organizer.name' => ['label' => 'نام', 'default' => '{site_name}'],
                    'organizer.url' => ['label' => 'نشانی', 'default' => '{site_url}'],
                    'offers.price' => ['label' => 'قیمت بلیت'],
                    'offers.priceCurrency' => ['label' => 'واحد پول', 'default' => 'IRR'],
                    'offers.url' => ['label' => 'نشانی خرید بلیت'],
                    'offers.availability' => ['label' => 'موجودی بلیت', 'kind' => 'select', 'options' => self::AVAILABILITY],
                ],
            ],
            'FAQPage' => [
                'label' => 'پرسش‌های متداول',
                'about' => 'پرسش و پاسخ‌ها زیر نتیجهٔ جستجو. پرسش‌هایی که اینجا بنویسید با پرسش‌های خود محتوا یکی می‌شوند.',
                'fields' => [
                    'mainEntity' => ['label' => 'پرسش‌ها', 'kind' => 'faq'],
                    'own' => ['label' => 'پرسش‌های خود محتوا را هم بیفزا', 'kind' => 'toggle', 'default' => true, 'help' => 'بخش «سوالات» محتوا به این فهرست افزوده می‌شود.'],
                ],
            ],
            'BreadcrumbList' => [
                'label' => 'مسیر راهنما',
                'about' => 'مسیر صفحه، مثل خانه › نوشته‌ها › عنوان، به جای نشانی خام در نتیجهٔ جستجو. خودکار از نشانی و والدهای محتوا ساخته می‌شود.',
                'record' => true,
                'fields' => [
                    'home' => ['label' => 'نام صفحهٔ اصلی', 'default' => 'خانه'],
                    'section' => ['label' => 'نام بخش', 'default' => '{section}', 'help' => 'مثل «نوشته‌ها»، با پیوند به نشانی بخش. اگر خالی بماند یا نشانی محتوا بخشی نداشته باشد، این پله ساخته نمی‌شود.'],
                ],
            ],
            'Custom' => [
                'label' => 'سفارشی',
                'about' => 'هر اسکیمای دیگری که اینجا نیست، به صورت JSON-LD. متغیرها در متن‌ها پر می‌شوند.',
                'fields' => [
                    'json' => ['label' => 'JSON-LD', 'kind' => 'code', 'default' => "{\n    \"@context\": \"https://schema.org\",\n    \"@type\": \"Thing\",\n    \"name\": \"{title}\"\n}"],
                ],
            ],
        ];
    }

    /**
     * Type keys with their Persian labels, for the type select. The site leaves out record types.
     *
     * @return array<string, string>
     */
    public static function options(bool $site = false): array
    {
        $types = array_filter(self::types(), fn (array $type): bool => ! $site || ! ($type['record'] ?? false));

        return array_map(fn (array $type): string => $type['label'], $types);
    }

    /**
     * The default values of a type, nested the way the editor and the stored items hold them.
     *
     * @return array<string, mixed>
     */
    public static function defaults(?string $type): array
    {
        $values = [];

        foreach (self::types()[$type]['fields'] ?? [] as $path => $field) {
            $kind = $field['kind'] ?? 'text';
            $value = $field['default'] ?? match ($kind) {
                'list', 'faq' => [],
                'toggle' => false,
                default => null,
            };

            Arr::set($values, $path, $value);
        }

        return $values;
    }

    /**
     * One type filled for an owner: a document, a list of documents for a custom one, or null.
     *
     * @param  array<string, mixed>  $fields
     * @return array<int|string, mixed>|null
     */
    public static function render(string $type, array $fields, ?Schemable $owner = null): ?array
    {
        $spec = self::types()[$type] ?? null;

        if ($spec === null) {
            return null;
        }

        $values = Schemas::values($owner);

        return match ($type) {
            'Custom' => self::custom($fields['json'] ?? null, $values),
            'BreadcrumbList' => self::breadcrumbs($fields, $owner, $values),
            'FAQPage' => self::faq($fields, $owner, $values),
            default => self::document($type, $spec, $fields, $values),
        };
    }

    /**
     * The fields of a regular type as one document, with nested nodes typed and empty values dropped.
     *
     * @param  array{fields: array<string, array<string, mixed>>, fixed?: array<string, string>}  $spec
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $values
     * @return array<string, mixed>|null
     */
    private static function document(string $type, array $spec, array $fields, array $values): ?array
    {
        $body = [];

        foreach (array_keys($spec['fields']) as $path) {
            if ($path === 'type') {
                continue;
            }

            $value = self::resolve(data_get($fields, $path), $values);

            if ($value !== null) {
                Arr::set($body, $path, $value);
            }
        }

        if ($body === []) {
            return null;
        }

        foreach (self::NODES as $path => $node) {
            $current = data_get($body, $path);

            if (is_array($current) && ! array_is_list($current)) {
                Arr::set($body, $path, ['@type' => $node] + $current);
            }
        }

        foreach ($spec['fixed'] ?? [] as $path => $value) {
            $parent = substr($path, 0, (int) strrpos($path, '.'));

            if (is_array(data_get($body, $parent))) {
                Arr::set($body, $path, $value);
            }
        }

        $chosen = self::resolve($fields['type'] ?? null, $values);

        return ['@context' => self::CONTEXT, '@type' => is_string($chosen) ? $chosen : $type] + $body;
    }

    /**
     * A FAQPage from the typed questions and, when asked, the owner's own questions.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $values
     * @return array<string, mixed>|null
     */
    private static function faq(array $fields, ?Schemable $owner, array $values): ?array
    {
        $rows = array_values((array) ($fields['mainEntity'] ?? []));

        if (($fields['own'] ?? false) && $owner !== null) {
            array_push($rows, ...array_values($owner->faqs()));
        }

        $questions = [];

        foreach ($rows as $row) {
            $question = is_array($row) ? self::resolve($row['question'] ?? null, $values) : null;
            $answer = is_array($row) ? self::resolve($row['answer'] ?? null, $values) : null;

            if (! is_string($question) || ! is_string($answer)) {
                continue;
            }

            $questions[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }

        if ($questions === []) {
            return null;
        }

        return ['@context' => self::CONTEXT, '@type' => 'FAQPage', 'mainEntity' => $questions];
    }

    /**
     * A BreadcrumbList for an owner: home, its section, its parents, then the owner itself.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $values
     * @return array<string, mixed>|null
     */
    private static function breadcrumbs(array $fields, ?Schemable $owner, array $values): ?array
    {
        if ($owner === null || $values['{url}'] === '') {
            return null;
        }

        $steps = [];
        $home = self::resolve($fields['home'] ?? null, $values);

        if (is_string($home) && $values['{site_url}'] !== '') {
            $steps[] = [$home, $values['{site_url}']];
        }

        $section = self::resolve($fields['section'] ?? null, $values);
        $address = $owner->section();

        if (is_string($section) && is_string($address) && $address !== '') {
            $steps[] = [$section, $address];
        }

        foreach ($owner->crumbs() as [$name, $item]) {
            $steps[] = [$name, $item];
        }

        $steps[] = [$values['{title}'], $values['{url}']];

        $items = [];

        foreach ($steps as $index => [$name, $item]) {
            $items[] = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $name, 'item' => $item];
        }

        return ['@context' => self::CONTEXT, '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * The JSON-LD typed in a custom schema, with placeholders filled in every string.
     *
     * @param  array<string, string>  $values
     * @return array<int|string, mixed>|null
     */
    private static function custom(mixed $json, array $values): ?array
    {
        $data = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($data) || $data === []) {
            return null;
        }

        array_walk_recursive($data, function (mixed &$item) use ($values): void {
            if (is_string($item)) {
                $item = strtr($item, $values);
            }
        });

        if (! array_is_list($data) && ! isset($data['@context'])) {
            $data = ['@context' => self::CONTEXT] + $data;
        }

        return $data;
    }

    /**
     * A stored value with placeholders filled: a trimmed string, a list of them, or null when empty.
     *
     * @param  array<string, string>  $values
     * @return string|list<string>|null
     */
    private static function resolve(mixed $value, array $values): string|array|null
    {
        if (is_array($value)) {
            $list = array_values(array_filter(
                array_map(fn (mixed $item): ?string => is_string($item) ? self::resolve($item, $values) : null, $value),
                fn (?string $item): bool => $item !== null,
            ));

            return $list === [] ? null : $list;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim(strtr($value, $values));

        return $text === '' ? null : $text;
    }

    /**
     * The postal address fields under a path, such as address or location.address.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function address(string $path): array
    {
        return [
            $path.'.streetAddress' => ['label' => 'خیابان و پلاک'],
            $path.'.addressLocality' => ['label' => 'شهر'],
            $path.'.addressRegion' => ['label' => 'استان'],
            $path.'.postalCode' => ['label' => 'کد پستی'],
            $path.'.addressCountry' => ['label' => 'کشور', 'help' => 'کد دوحرفی؛ ایران IR است.'],
        ];
    }
}
