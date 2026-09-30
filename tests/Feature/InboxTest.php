<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Pages\FormSettings;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Models\Entry;
use App\Models\Form;
use App\Models\FormSetting;
use App\Models\Setting;
use App\Models\User;
use App\Support\Fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the inbox of form messages, the forms settings page, and the pruning of old messages.
 *
 * Extending:
 * - A new inbox action or setting gets a test here.
 */
class InboxTest extends TestCase
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
     * Staff with the inbox section list, open, archive, export, download, and delete messages; others get 403.
     */
    public function test_staff_read_messages_in_the_inbox(): void
    {
        $disk = Storage::fake(Fields::DISK);
        $form = Form::factory()->create(['title' => 'تماس با ما', 'slug' => 'contact']);
        $disk->put(Fields::FOLDER.'/'.$form->getKey().'/cv.pdf', '%PDF-1.4');
        $message = Entry::factory()->for($form)->create([
            'answers' => [
                ['key' => 'name', 'label' => 'نام و نام خانوادگی', 'type' => 'text', 'value' => 'مریم احمدی'],
                ['key' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'value' => 'maryam@example.com'],
                ['key' => 'topic', 'label' => 'موضوع', 'type' => 'checkboxes', 'value' => ['فروش', 'پشتیبانی']],
                ['key' => 'resume', 'label' => 'رزومه', 'type' => 'file', 'value' => ['path' => Fields::FOLDER.'/'.$form->getKey().'/cv.pdf', 'name' => 'cv.pdf', 'size' => 8]],
            ],
            'source' => 'https://damoon.test/contact',
            'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
        ]);
        $other = Entry::factory()->for($form)->read()->create([
            'answers' => [
                ['key' => 'name', 'label' => 'نام و نام خانوادگی', 'type' => 'text', 'value' => 'پیام قبلی مریم'],
                ['key' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'value' => 'Maryam@Example.com'],
            ],
            'created_at' => now()->subDay(),
        ]);
        $staff = User::factory()->create(['username' => 'support_user', 'email' => 'support@example.com', 'phone' => '09120000004']);
        $staff->grant([Section::INBOX]);
        $token = $this->token($staff);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/inbox')
            ->assertOk()
            ->assertSee('صندوق پیام‌ها')
            ->assertSee('تماس با ما')
            ->assertSee('مریم احمدی — maryam@example.com')
            ->assertSee('جدید');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/inbox/'.$message->getKey())
            ->assertOk()
            ->assertSee('پیام از «مریم احمدی»')
            ->assertSee('پاسخ با ایمیل')
            ->assertSee('mailto:maryam@example.com?subject=', false)
            ->assertSee('نام و نام خانوادگی')
            ->assertSee('فروش')
            ->assertSee('پشتیبانی')
            ->assertSee('damoon.test/contact')
            ->assertSee('Chrome روی Windows')
            ->assertSee('cv.pdf')
            ->assertSee('کپی همه')
            ->assertSee('پیام‌های دیگر این فرستنده')
            ->assertSee('پیام قبلی مریم');

        $this->assertSame(Entry::READ, $message->refresh()->status);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ViewEntry::class, ['record' => $message->getKey()])
            ->assertActionEnabled('older')
            ->assertActionDisabled('newer')
            ->callAction('download', arguments: ['index' => 3])
            ->assertFileDownloaded('cv.pdf');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ViewEntry::class, ['record' => $message->getKey()])
            ->callAction('download', arguments: ['index' => 0])
            ->assertNoFileDownloaded();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListEntries::class)
            ->assertCanSeeTableRecords([$message, $other])
            ->callTableAction('unread', $message)
            ->callTableAction('archive', $other)
            ->set('activeTab', Entry::ARCHIVED)
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$message]);

        $this->assertSame(Entry::NEW, $message->refresh()->status);
        $this->assertSame(Entry::ARCHIVED, $other->refresh()->status);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListEntries::class)
            ->callAction('export', data: ['form' => $form->getKey()])
            ->assertFileDownloaded('contact-'.now()->format('Y-m-d').'.csv');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListEntries::class)
            ->callTableAction('delete', $message);

        $this->assertNull(Entry::query()->find($message->getKey()));
        $disk->assertMissing(Fields::FOLDER.'/'.$form->getKey().'/cv.pdf');

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::FORMS]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))->get('/admin/inbox')->assertForbidden();
    }

    /**
     * The settings page saves the notice, the default message, the rate, the file ceiling, and the retention period.
     */
    public function test_forms_settings_are_saved(): void
    {
        $owner = $this->owner();
        $token = $this->token($owner);
        Form::factory()->create(['slug' => 'contact']);

        $this->assertFalse(FormSetting::current()->exists);
        $this->assertSame(5, FormSetting::current()->rate);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/form-settings')
            ->assertOk()
            ->assertSee('تنظیمات فرم‌ها')
            ->assertSee('ایمیل‌های دریافت اعلان')
            ->assertSee('نگهداری پیام‌ها (روز)');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(FormSettings::class)
            ->fillForm(['rate' => 0, 'recipients' => ['not-an-email']])
            ->call('save')
            ->assertHasFormErrors(['rate', 'recipients.0']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(FormSettings::class)
            ->fillForm([
                'notify' => true,
                'recipients' => ['team@example.com'],
                'message' => 'پیام شما رسید.',
                'rate' => 10,
                'size' => 2048,
                'retention' => 90,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = FormSetting::current();
        $this->assertTrue($settings->exists);
        $this->assertSame(['team@example.com'], $settings->recipients);
        $this->assertSame(10, $settings->rate);
        $this->assertSame(2048, $settings->size);
        $this->assertSame(90, $settings->retention);
        $this->assertSame(1, FormSetting::query()->count());

        $this->postJson('/v1/forms/contact', ['name' => 'مریم', 'email' => 'maryam@example.com', 'topic' => 'فروش', 'message' => 'سلام، یک پرسش دارم.', 'consent' => true])
            ->assertCreated()
            ->assertJsonPath('message', 'پیام شما رسید.');
    }

    /**
     * model:prune deletes messages older than the retention period with their files, and nothing while it is empty.
     */
    public function test_old_messages_are_pruned(): void
    {
        $disk = Storage::fake(Fields::DISK);
        $form = Form::factory()->create();
        $path = Fields::FOLDER.'/'.$form->getKey().'/old.pdf';
        $disk->put($path, '%PDF-1.4');
        $old = Entry::factory()->for($form)->create([
            'answers' => [['key' => 'file', 'label' => 'فایل', 'type' => 'file', 'value' => ['path' => $path, 'name' => 'old.pdf', 'size' => 8]]],
            'created_at' => now()->subDays(40),
        ]);
        $recent = Entry::factory()->for($form)->create(['created_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [Entry::class]])->assertSuccessful();
        $this->assertSame(2, Entry::query()->count());

        FormSetting::query()->create(['retention' => 30]);
        $this->artisan('model:prune', ['--model' => [Entry::class]])->assertSuccessful();

        $this->assertNull(Entry::query()->find($old->getKey()));
        $this->assertNotNull(Entry::query()->find($recent->getKey()));
        $disk->assertMissing($path);
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
