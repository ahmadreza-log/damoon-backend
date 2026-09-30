<?php

namespace Database\Factories;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake forms for tests and local sample data.
 *
 * The default form is a contact form: name, email, phone, a subject picked from a list, the
 * message, and a consent tick. SAMPLES holds the sample forms FormSeeder creates, each with
 * an English slug so its API address reads well.
 *
 * Extending:
 * - A new forms column needs a default here so Form::factory()->create() keeps working.
 * - Another sample form is one more entry in SAMPLES.
 */
class FormFactory extends Factory
{
    /**
     * Sample forms: slug => [title, description, fields].
     *
     * @var array<string, array{0: string, 1: string, 2: list<array{type: string, data: array<string, mixed>}>}>
     */
    public const SAMPLES = [
        'contact' => ['تماس با ما', 'پرسش یا پیشنهادتان را بنویسید؛ در کمتر از یک روز کاری پاسخ می‌دهیم.', [
            ['type' => 'text', 'data' => ['label' => 'نام و نام خانوادگی', 'key' => 'name', 'required' => true, 'width' => 'half']],
            ['type' => 'email', 'data' => ['label' => 'ایمیل', 'key' => 'email', 'required' => true, 'width' => 'half']],
            ['type' => 'phone', 'data' => ['label' => 'تلفن همراه', 'key' => 'phone', 'placeholder' => '09120000000', 'width' => 'half']],
            ['type' => 'select', 'data' => ['label' => 'موضوع', 'key' => 'topic', 'required' => true, 'width' => 'half', 'options' => ['پشتیبانی', 'فروش', 'پیشنهاد', 'سایر']]],
            ['type' => 'text', 'data' => ['label' => 'موضوع دیگر', 'key' => 'other', 'when' => 'topic', 'equals' => 'سایر']],
            ['type' => 'textarea', 'data' => ['label' => 'پیام', 'key' => 'message', 'required' => true, 'min' => 10]],
            ['type' => 'checkbox', 'data' => ['label' => 'قوانین حریم خصوصی را می‌پذیرم.', 'key' => 'consent', 'required' => true]],
        ]],
        'cooperation' => ['درخواست همکاری', 'اگر دوست دارید به تیم ما بپیوندید، این فرم را پر کنید.', [
            ['type' => 'paragraph', 'data' => ['label' => 'پیش از شروع', 'content' => 'پر کردن این فرم حدود پنج دقیقه زمان می‌برد.']],
            ['type' => 'text', 'data' => ['label' => 'نام و نام خانوادگی', 'key' => 'name', 'required' => true, 'width' => 'half']],
            ['type' => 'email', 'data' => ['label' => 'ایمیل', 'key' => 'email', 'required' => true, 'width' => 'half']],
            ['type' => 'radio', 'data' => ['label' => 'حوزهٔ همکاری', 'key' => 'field', 'required' => true, 'options' => ['برنامه‌نویسی', 'طراحی', 'فروش', 'پشتیبانی']]],
            ['type' => 'number', 'data' => ['label' => 'سال‌های سابقه', 'key' => 'experience', 'min' => 0, 'max' => 50, 'width' => 'half']],
            ['type' => 'date', 'data' => ['label' => 'تاریخ آمادگی شروع', 'key' => 'start', 'width' => 'half']],
            ['type' => 'url', 'data' => ['label' => 'نمونه‌کار یا لینکدین', 'key' => 'portfolio']],
            ['type' => 'file', 'data' => ['label' => 'رزومه', 'key' => 'resume', 'accept' => ['pdf', 'document'], 'size' => 2048]],
        ]],
        'survey' => ['نظرسنجی رضایت', 'نظر شما به بهتر شدن خدمات ما کمک می‌کند.', [
            ['type' => 'radio', 'data' => ['label' => 'چقدر از خدمات ما راضی هستید؟', 'key' => 'rating', 'required' => true, 'options' => ['خیلی زیاد', 'زیاد', 'متوسط', 'کم']]],
            ['type' => 'checkboxes', 'data' => ['label' => 'از کدام خدمات استفاده کرده‌اید؟', 'key' => 'services', 'options' => ['مشاوره', 'پیاده‌سازی', 'پشتیبانی', 'آموزش']]],
            ['type' => 'textarea', 'data' => ['label' => 'پیشنهاد شما', 'key' => 'suggestion']],
        ]],
    ];

    /**
     * The default column values for one fake form: the sample contact form.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$title, $description, $fields] = self::SAMPLES['contact'];

        return [
            'title' => $title,
            'slug' => null,
            'description' => $description,
            'fields' => $fields,
            'button' => null,
            'message' => null,
            'recipients' => [],
            'active' => true,
        ];
    }

    /**
     * A form that is switched off.
     */
    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
