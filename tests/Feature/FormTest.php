<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Resources\Forms\Pages\CreateForm;
use App\Filament\Resources\Forms\Pages\DesignForm;
use App\Filament\Resources\Forms\Pages\EditForm;
use App\Filament\Resources\Forms\Pages\ListForms;
use App\Filament\Schemas\FormFields;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Form;
use App\Models\FormSetting;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\EntryReceived;
use App\Support\Fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the form builder in the panel and the form routes of the v1 API: reading a form and sending it.
 *
 * Extending:
 * - A new field type gets a value in the builder test and a rule check in the sending test.
 * - Tests that store files call Storage::fake first so nothing reaches the real disk.
 */
class FormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed, so panel pages do not redirect to /install.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
        ]);
    }

    /**
     * A form is built with blocks, checked for unique keys and valid conditions, copied, and deleted with its messages.
     *
     * A member needs the forms section to open the builder and the settings page.
     */
    public function test_forms_are_built_in_the_panel(): void
    {
        $disk = Storage::fake(Fields::DISK);
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/forms/create')
            ->assertOk()
            ->assertSee('فرم‌ها')
            ->assertSee('فیلدها')
            ->assertSee('افزودن فیلد')
            ->assertSee('پس از ارسال')
            ->assertSee('صندوق پیام‌ها')
            ->assertSee('تنظیمات');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateForm::class)
            ->fillForm([
                'title' => 'تماس با ما',
                'slug' => 'contact',
                'fields' => [
                    'a' => ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'required' => true, 'width' => 'half']],
                    'b' => ['type' => 'text', 'data' => ['label' => 'نام خانوادگی', 'key' => 'name']],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['fields']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateForm::class)
            ->fillForm([
                'title' => 'تماس با ما',
                'slug' => 'contact',
                'fields' => [
                    'a' => ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'when' => 'missing', 'equals' => 'x']],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['fields']);

        foreach ([
            ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'min' => 20, 'max' => 5]],
            ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'when' => 'topic', 'equals' => 'نامعتبر']],
            ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'when' => 'consent', 'equals' => 'بله']],
        ] as $block) {
            Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
                ->test(CreateForm::class)
                ->fillForm([
                    'title' => 'تماس با ما',
                    'slug' => 'contact',
                    'fields' => [
                        'a' => ['type' => 'select', 'data' => ['label' => 'موضوع', 'key' => 'topic', 'options' => ['فروش', 'سایر']]],
                        'b' => ['type' => 'checkbox', 'data' => ['label' => 'قوانین', 'key' => 'consent']],
                        'c' => $block,
                    ],
                ])
                ->call('create')
                ->assertHasFormErrors(['fields']);
        }

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateForm::class)
            ->fillForm([
                'title' => 'تماس با ما',
                'slug' => 'contact',
                'description' => 'پیام خود را بفرستید.',
                'fields' => [
                    'a' => ['type' => 'paragraph', 'data' => ['label' => 'راهنما', 'content' => 'همهٔ فیلدهای ستاره‌دار الزامی‌اند.', 'width' => 'full']],
                    'b' => ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'required' => true, 'width' => 'half']],
                    'c' => ['type' => 'select', 'data' => ['label' => 'موضوع', 'key' => 'topic', 'options' => ['فروش', 'سایر'], 'required' => true, 'width' => 'half']],
                    'd' => ['type' => 'text', 'data' => ['label' => 'موضوع دیگر', 'key' => 'other', 'when' => 'topic', 'equals' => 'سایر', 'width' => 'full']],
                    'e' => ['type' => 'file', 'data' => ['label' => 'پیوست', 'key' => 'attachment', 'accept' => ['pdf'], 'size' => 1024, 'width' => 'full']],
                ],
                'button' => 'بفرست',
                'recipients' => ['sales@example.com'],
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $form = Form::query()->where('slug', 'contact')->firstOrFail();
        $fields = $form->definition();

        $this->assertCount(5, $fields);
        $this->assertSame(['paragraph', 'text', 'select', 'text', 'file'], array_column($fields, 'type'));
        $this->assertNull($fields[0]['key']);
        $this->assertSame('half', $fields[1]['width']);
        $this->assertTrue($fields[1]['required']);
        $this->assertSame(['فروش', 'سایر'], $fields[2]['options']);
        $this->assertSame(['field' => 'topic', 'value' => 'سایر'], $fields[3]['condition']);
        $this->assertSame(['pdf'], $fields[4]['accept']);
        $this->assertSame(1024, $fields[4]['size']);
        $this->assertSame('بفرست', $form->label());
        $this->assertSame(['sales@example.com'], $form->recipients);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/forms/'.$form->getKey().'/edit')
            ->assertOk()
            ->assertSee('موضوع دیگر');

        $disk->put(Fields::FOLDER.'/'.$form->getKey().'/note.pdf', '%PDF-1.4');
        $form->entries()->create([
            'answers' => [['key' => 'attachment', 'label' => 'پیوست', 'type' => 'file', 'value' => ['path' => Fields::FOLDER.'/'.$form->getKey().'/note.pdf', 'name' => 'note.pdf', 'size' => 8]]],
            'status' => Entry::NEW,
        ]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/forms')
            ->assertOk()
            ->assertSee('تماس با ما')
            ->assertSee('/v1/forms/contact')
            ->assertSee('1 جدید');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListForms::class)
            ->callTableAction('replicate', $form)
            ->assertHasNoTableActionErrors();

        $copy = Form::query()->whereKeyNot($form->getKey())->sole();
        $this->assertSame('تماس با ما (رونوشت)', $copy->title);
        $this->assertFalse($copy->active);
        $this->assertNotSame('contact', $copy->slug);
        $this->assertCount(5, $copy->definition());

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListForms::class)
            ->callTableAction('delete', $form);

        $this->assertNull(Form::query()->find($form->getKey()));
        $this->assertSame(0, Entry::query()->count());
        $disk->assertMissing(Fields::FOLDER.'/'.$form->getKey().'/note.pdf');

        $builder = User::factory()->create(['username' => 'builder_user', 'email' => 'builder@example.com', 'phone' => '09120000003']);
        $builder->grant([Section::FORMS]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($builder))->get('/admin/forms')->assertOk();

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::INBOX]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))->get('/admin/forms')->assertForbidden();
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))->get('/admin/form-settings')->assertForbidden();
    }

    /**
     * The full-screen form builder opens for form editors, saves only fields that pass the builder checks, and shows the API output.
     *
     * A new form opens in it right after it is created; the list and edit page link to it.
     */
    public function test_forms_are_laid_out_in_the_form_builder(): void
    {
        $owner = $this->owner();
        $token = $this->token($owner);
        $cookie = (string) config('sanctum.panel_cookie');

        $this->withCookie($cookie, $token)->get('/admin/forms')->assertOk();

        $created = Livewire::withCookie($cookie, $token)
            ->test(CreateForm::class)
            ->fillForm(['title' => 'همکاری با ما', 'slug' => 'jobs'])
            ->call('create')
            ->assertHasNoFormErrors();

        $form = Form::query()->where('slug', 'jobs')->firstOrFail();
        $created->assertRedirect(FormResource::getUrl('design', ['record' => $form]));

        $this->withCookie($cookie, $token)
            ->get('/admin/forms/'.$form->getKey().'/design')
            ->assertOk()
            ->assertSee('فرم‌ساز: همکاری با ما')
            ->assertSee('damoon-forms', false)
            ->assertSee('formbuilder.js', false)
            ->assertSee('گروه‌های آماده')
            ->assertSee('مشخصات فرم');

        Livewire::withCookie($cookie, $token)->test(EditForm::class, ['record' => $form->getKey()])->assertActionExists('design');
        Livewire::withCookie($cookie, $token)->test(ListForms::class)->assertTableActionExists('design', record: $form);

        $good = [
            ['type' => 'paragraph', 'data' => ['label' => null, 'content' => 'فرم همکاری', 'width' => 'full']],
            ['type' => 'text', 'data' => ['label' => ' نام ', 'key' => 'name', 'required' => true, 'width' => 'half', 'min' => '2', 'max' => null, 'evil' => '<script>']],
            ['type' => 'select', 'data' => ['label' => 'حوزه', 'key' => 'area', 'options' => ['فنی', 'فروش'], 'multiple' => false, 'width' => 'half']],
            ['type' => 'text', 'data' => ['label' => 'توضیح', 'key' => 'note', 'when' => 'area', 'equals' => 'فروش']],
        ];

        $designer = Livewire::withCookie($cookie, $token)->test(DesignForm::class, ['record' => $form->getKey()]);

        foreach ([
            [...$good, ['type' => 'email', 'data' => ['label' => 'ایمیل', 'key' => 'name']]],
            [['type' => 'text', 'data' => ['label' => '', 'key' => 'name']]],
            [['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'Bad Key']]],
            [['type' => 'select', 'data' => ['label' => 'حوزه', 'key' => 'area', 'options' => []]]],
            [['type' => 'script', 'data' => ['label' => 'x', 'key' => 'x']]],
            [...$good, ['type' => 'text', 'data' => ['label' => 'x', 'key' => 'x', 'when' => 'area', 'equals' => 'نامعتبر']]],
        ] as $bad) {
            $designer->call('save', $bad)->assertNotified('فرم ذخیره نشد.');
            $this->assertSame([], $form->refresh()->fields);
        }

        $this->assertSame('فیلد «متن کوتاه»: «برچسب» الزامی است.', FormFields::problems([['type' => 'text', 'data' => ['key' => 'name']]])[0] ?? null);
        $this->assertSame([1], array_keys(FormFields::problems([$good[1], ['type' => 'email', 'data' => ['label' => 'ایمیل', 'key' => 'name']]])));

        $designer->call('save', $good)->assertNotified('فرم‌ساز ذخیره شد.');

        $form->refresh();
        $this->assertCount(4, $form->fields);
        $this->assertSame('نام', $form->fields[1]['data']['label']);
        $this->assertArrayNotHasKey('evil', $form->fields[1]['data']);
        $this->assertSame(['paragraph', 'text', 'select', 'text'], array_column($form->definition(), 'type'));
        $this->assertSame(['field' => 'area', 'value' => 'فروش'], $form->definition()[3]['condition']);

        $preview = $designer->instance()->definition($good);
        $this->assertSame('name', $preview[1]['key']);
        $this->assertSame(2, $preview[1]['min']);

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::INBOX]);
        $this->withCookie($cookie, $this->token($member))->get('/admin/forms/'.$form->getKey().'/design')->assertForbidden();
    }

    /**
     * The API lists active forms and sends one form's fields in the same shape for every type.
     */
    public function test_form_api_sends_definitions(): void
    {
        $form = $this->contact();
        Form::factory()->inactive()->create(['title' => 'فرم خاموش', 'slug' => 'off']);

        $this->getJson('/v1/forms')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'contact')
            ->assertJsonMissingPath('data.0.fields');
        $this->getJson('/v1/forms?per_page=1')->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/v1/forms/contact')
            ->assertOk()
            ->assertJsonPath('data.title', 'تماس با ما')
            ->assertJsonPath('data.button', Form::BUTTON)
            ->assertJsonPath('data.action', url('v1/forms/contact'))
            ->assertJsonPath('data.multipart', true)
            ->assertJsonPath('data.honeypot', Fields::HONEYPOT)
            ->assertJsonPath('data.fields.0.key', 'name')
            ->assertJsonPath('data.fields.0.type', 'text')
            ->assertJsonPath('data.fields.0.required', true)
            ->assertJsonPath('data.fields.2.options', ['فروش', 'سایر'])
            ->assertJsonPath('data.fields.2.multiple', false)
            ->assertJsonPath('data.fields.3.condition', ['field' => 'topic', 'value' => 'سایر'])
            ->assertJsonPath('data.fields.6.accept', ['pdf'])
            ->assertJsonStructure(['data' => ['fields' => [['key', 'type', 'label', 'placeholder', 'help', 'required', 'width', 'options', 'multiple', 'min', 'max', 'accept', 'size', 'condition', 'content']]]]);

        $this->getJson('/v1/forms/off')->assertNotFound()->assertJsonPath('message', 'فرم پیدا نشد.');
        $this->getJson('/v1/forms/missing')->assertNotFound();
        $this->postJson('/v1/forms/off', ['name' => 'مریم'])->assertNotFound();

        $form->update(['active' => false]);
        $this->getJson('/v1/forms/contact')->assertNotFound();
    }

    /**
     * Sent answers are checked with Persian errors, hidden fields are dropped, and the message lands in the inbox with a notice.
     */
    public function test_form_api_collects_messages(): void
    {
        Notification::fake();
        FormSetting::query()->create(['notify' => true, 'recipients' => ['team@example.com'], 'rate' => 30, 'size' => 5120]);
        $form = $this->contact(['recipients' => ['sales@example.com'], 'message' => 'ممنون، به‌زودی تماس می‌گیریم.']);

        $this->postJson('/v1/forms/contact', ['email' => 'bad', 'topic' => 'نامعتبر', 'phone' => '12'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'فیلد «نام» الزامی است.')
            ->assertJsonPath('errors.email.0', '«ایمیل» ایمیل معتبری نیست.')
            ->assertJsonPath('errors.topic.0', 'گزینهٔ انتخاب‌شده برای «موضوع» معتبر نیست.')
            ->assertJsonPath('errors.phone.0', '«تلفن» معتبر نیست.')
            ->assertJsonPath('errors.consent.0', '«قوانین را می‌پذیرم.» باید تأیید شود.')
            ->assertJsonMissingPath('errors.other');

        $this->postJson('/v1/forms/contact', ['name' => 'مریم', 'email' => 'maryam@example.com', 'topic' => 'سایر', 'consent' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('other');

        $this->assertSame(0, Entry::query()->count());

        $this->withHeader('Referer', 'https://damoon.test/contact')
            ->postJson('/v1/forms/contact', [
                'name' => ' مریم احمدی ',
                'email' => 'maryam@example.com',
                'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
                'topic' => 'فروش',
                'other' => 'نباید ذخیره شود',
                'consent' => true,
                'extra' => 'ناشناخته',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'ممنون، به‌زودی تماس می‌گیریم.')
            ->assertJsonStructure(['message', 'data' => ['id', 'created_at']]);

        $entry = Entry::query()->sole();
        $answers = collect($entry->answers)->keyBy('key');

        $this->assertTrue($entry->form->is($form));
        $this->assertSame(Entry::NEW, $entry->status);
        $this->assertSame('https://damoon.test/contact', $entry->source);
        $this->assertNull($entry->customer_id);
        $this->assertSame('مریم احمدی', $answers['name']['value']);
        $this->assertSame('نام', $answers['name']['label']);
        $this->assertSame('09121234567', $answers['phone']['value']);
        $this->assertSame('فروش', $answers['topic']['value']);
        $this->assertTrue($answers['consent']['value']);
        $this->assertNull($answers['attachment']['value']);
        $this->assertFalse($answers->has('other'));
        $this->assertFalse($answers->has('extra'));

        Notification::assertSentTo(new AnonymousNotifiable, EntryReceived::class, function (EntryReceived $notice, array $channels, AnonymousNotifiable $notifiable) use ($entry): bool {
            return $notice->entry->is($entry) && $notifiable->routes['mail'] === ['sales@example.com', 'team@example.com'];
        });

        $customer = Customer::factory()->create(['email' => 'reza@example.com']);
        $token = app(AccessTokens::class)->issue($customer, AccessTokens::ABILITY_API, AccessTokens::ABILITY_API, 60);
        app('auth')->forgetGuards();

        $this->withToken($token)
            ->postJson('/v1/forms/contact', ['name' => 'رضا', 'email' => 'reza@example.com', 'topic' => 'سایر', 'other' => 'همکاری', 'consent' => '1'])
            ->assertCreated();

        $second = Entry::query()->latest('id')->firstOrFail();
        $this->assertSame($customer->getKey(), $second->customer_id);
        $this->assertSame('همکاری', collect($second->answers)->firstWhere('key', 'other')['value']);

        FormSetting::query()->update(['notify' => false]);
        $form->update(['recipients' => [], 'message' => null]);
        Notification::fake();

        $this->postJson('/v1/forms/contact', ['name' => 'سارا', 'email' => 'sara@example.com', 'topic' => 'فروش', 'consent' => true])
            ->assertCreated()
            ->assertJsonPath('message', FormSetting::MESSAGE);
        Notification::assertNothingSent();
    }

    /**
     * An uploaded answer goes to the private disk; a wrong type or a file over the settings ceiling is refused.
     */
    public function test_file_answers_are_stored_privately(): void
    {
        $disk = Storage::fake(Fields::DISK);
        Storage::fake('public');
        FormSetting::query()->create(['notify' => false, 'rate' => 30, 'size' => 500]);
        $form = $this->contact();
        $answers = ['name' => 'مریم', 'email' => 'maryam@example.com', 'topic' => 'فروش', 'consent' => '1'];

        $this->post('/v1/forms/contact', $answers + ['attachment' => UploadedFile::fake()->create('photo.png', 10, 'image/png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.attachment.0', 'نوع فایل «پیوست» مجاز نیست. پسوندهای مجاز: pdf');

        $this->post('/v1/forms/contact', $answers + ['attachment' => UploadedFile::fake()->create('big.pdf', 700, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.attachment.0', 'حجم «پیوست» نباید بیشتر از 500 کیلوبایت باشد.');

        $this->post('/v1/forms/contact', $answers + ['attachment' => UploadedFile::fake()->create('shell.php', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.attachment.0', 'نوع فایل «پیوست» مجاز نیست. پسوندهای مجاز: pdf');

        $this->post('/v1/forms/contact', $answers + ['attachment' => UploadedFile::fake()->create('res"u*me.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated();

        $file = collect(Entry::query()->sole()->answers)->firstWhere('key', 'attachment')['value'];

        $this->assertSame('res_u_me.pdf', $file['name']);
        $this->assertStringEndsWith('.pdf', $file['path']);
        $this->assertStringStartsWith(Fields::FOLDER.'/'.$form->getKey().'/', $file['path']);
        $disk->assertExists($file['path']);
        Storage::disk('public')->assertMissing($file['path']);
    }

    /**
     * Answers are cleaned before validation, wrong shapes are refused, and nothing stored becomes a script link, email markup, or formula.
     */
    public function test_answers_are_sanitized_before_they_are_stored(): void
    {
        FormSetting::query()->create(['notify' => false, 'rate' => 30, 'size' => 5120]);
        Form::factory()->create([
            'title' => 'نظرسنجی',
            'slug' => 'survey',
            'fields' => [
                ['type' => 'text', 'data' => ['label' => 'نام <b>کامل</b>', 'key' => 'name', 'required' => true]],
                ['type' => 'email', 'data' => ['label' => 'ایمیل', 'key' => 'email']],
                ['type' => 'phone', 'data' => ['label' => 'تلفن', 'key' => 'phone']],
                ['type' => 'url', 'data' => ['label' => 'وب‌سایت', 'key' => 'site']],
                ['type' => 'number', 'data' => ['label' => 'بودجه', 'key' => 'budget']],
                ['type' => 'date', 'data' => ['label' => 'تاریخ', 'key' => 'day']],
                ['type' => 'checkboxes', 'data' => ['label' => 'علاقه‌ها', 'key' => 'likes', 'options' => ['كتاب', 'فیلم']]],
                ['type' => 'textarea', 'data' => ['label' => 'پیام', 'key' => 'message']],
                ['type' => 'text', 'data' => ['label' => 'بد', 'key' => 'Bad-Key']],
            ],
        ]);

        $fields = collect(Form::query()->sole()->definition());
        $this->assertSame('نام کامل', $fields[0]['label']);
        $this->assertSame(['کتاب', 'فیلم'], $fields->firstWhere('key', 'likes')['options']);
        $this->assertNull($fields->firstWhere('key', 'Bad-Key'));

        $this->postJson('/v1/forms/survey', [
            'name' => '<img src=x onerror=alert(1)>',
            'site' => 'javascript:alert(1)//https://x.com',
            'budget' => '1e5',
            'day' => '1405-07-08',
            'likes' => ['فیلم', 'فیلم'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'فیلد «نام کامل» الزامی است.')
            ->assertJsonPath('errors.site.0', '«وب‌سایت» پیوند معتبری نیست.')
            ->assertJsonPath('errors.budget.0', '«بودجه» معتبر نیست.')
            ->assertJsonPath('errors.day.0', '«تاریخ» نباید پیش از 1900-01-01 باشد.')
            ->assertJsonValidationErrors(['likes.1' => 'یک گزینه برای «علاقه‌ها» بیش از یک بار فرستاده شده است.']);

        $this->postJson('/v1/forms/survey', ['name' => 'مریم', Fields::HONEYPOT => 'https://spam.test'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.'.Fields::HONEYPOT.'.0', 'ارسال پذیرفته نشد.');

        $this->assertSame(0, Entry::query()->count());

        $this->withHeader('Referer', 'javascript:alert(1)')
            ->postJson('/v1/forms/survey', [
                'name' => "  <b>علي</b>\u{202E}   رضايي<script>alert(1)</script>\n",
                'email' => ' Ali@Example.COM ',
                'phone' => '۰۹۱۲-۱۲۳ (۴۵۶۷)',
                'site' => 'https://damoon.test/مقاله',
                'budget' => '۱٬۲۰۰٫۵',
                'day' => '2026/09/30',
                'likes' => ['كتاب', ''],
                'message' => "سطر یک  \r\n\r\n\r\n\r\n  سطر\t\tدو\u{200B}",
                Fields::HONEYPOT => '',
            ])
            ->assertCreated();

        $entry = Entry::query()->sole();
        $answers = collect($entry->answers)->keyBy('key');

        $this->assertNull($entry->source);
        $this->assertSame('علی رضایی', $answers['name']['value']);
        $this->assertSame('ali@example.com', $answers['email']['value']);
        $this->assertSame('09121234567', $answers['phone']['value']);
        $this->assertSame('https://damoon.test/مقاله', $answers['site']['value']);
        $this->assertSame(1200.5, $answers['budget']['value']);
        $this->assertSame('2026-09-30', $answers['day']['value']);
        $this->assertSame(['کتاب'], $answers['likes']['value']);
        $this->assertSame("سطر یک\n\nسطر دو", $answers['message']['value']);

        $this->assertNull(Fields::link('javascript:alert(1)'));
        $this->assertNull(Fields::link('data:text/html,<b>x</b>'));
        $this->assertSame('https://damoon.test/a', Fields::link('https://damoon.test/a'));
        $this->assertSame('\[x\]\(https://evil.test\)', Fields::markdown('[x](https://evil.test)'));
        $this->assertSame("'=HYPERLINK(\"https://evil.test\")", Fields::cell('=HYPERLINK("https://evil.test")'));
        $this->assertSame('-5', Fields::cell('-5'));
        $this->assertFalse(Fields::inside('forms/1/../../.env', 1));
        $this->assertFalse(Fields::inside('forms/2/a.pdf', 1));
        $this->assertTrue(Fields::inside('forms/1/a.pdf', 1));
    }

    /**
     * Sending is limited per IP by the rate in the forms settings.
     */
    public function test_sending_is_limited_by_the_settings_rate(): void
    {
        FormSetting::query()->create(['notify' => false, 'rate' => 2, 'size' => 5120]);
        $this->contact();
        $answers = ['name' => 'مریم', 'email' => 'maryam@example.com', 'topic' => 'فروش', 'consent' => true];

        $this->postJson('/v1/forms/contact', $answers)->assertCreated();
        $this->postJson('/v1/forms/contact', $answers)->assertCreated();
        $this->postJson('/v1/forms/contact', $answers)->assertTooManyRequests();
        $this->getJson('/v1/forms/contact')->assertOk();
    }

    /**
     * The contact form used across these tests: name, email, phone, topic, a conditional other topic, consent, and a PDF attachment.
     *
     * @param  array<string, mixed>  $values
     */
    private function contact(array $values = []): Form
    {
        return Form::factory()->create($values + [
            'title' => 'تماس با ما',
            'slug' => 'contact',
            'fields' => [
                ['type' => 'text', 'data' => ['label' => 'نام', 'key' => 'name', 'required' => true]],
                ['type' => 'email', 'data' => ['label' => 'ایمیل', 'key' => 'email', 'required' => true]],
                ['type' => 'select', 'data' => ['label' => 'موضوع', 'key' => 'topic', 'required' => true, 'options' => ['فروش', 'سایر']]],
                ['type' => 'text', 'data' => ['label' => 'موضوع دیگر', 'key' => 'other', 'required' => true, 'when' => 'topic', 'equals' => 'سایر']],
                ['type' => 'phone', 'data' => ['label' => 'تلفن', 'key' => 'phone']],
                ['type' => 'checkbox', 'data' => ['label' => 'قوانین را می‌پذیرم.', 'key' => 'consent', 'required' => true]],
                ['type' => 'file', 'data' => ['label' => 'پیوست', 'key' => 'attachment', 'accept' => ['pdf']]],
            ],
        ]);
    }

    /**
     * The first user, who becomes the owner.
     */
    private function owner(): User
    {
        return User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
    }

    /**
     * A panel token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
