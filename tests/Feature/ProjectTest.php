<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the projects section in the panel, the Project model, and the project routes of the content API.
 *
 * Extending:
 * - A new project field needs an assertFormFieldExists, a value in the create test, and a key in the API test.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class ProjectTest extends TestCase
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
     * A project is created with every field, edited, listed, and deleted without losing its files.
     *
     * A member needs the projects section to open the list.
     */
    public function test_a_project_can_be_created_edited_and_deleted(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Project::LOGOS, $this->picture('logo.png'), 'logo.png');
        $other = Project::factory()->create(['title' => 'راه‌اندازی مرکز داده']);
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/projects/create')
            ->assertOk()
            ->assertSee('عنوان')
            ->assertSee('نامک')
            ->assertSee('توضیحات')
            ->assertSee('لوگوی برند')
            ->assertSee('سال اجرای پروژه')
            ->assertSee('خدمات ارائه شده')
            ->assertSee('صنعت فعالیت')
            ->assertSee('مدت اجرا')
            ->assertSee('محل احداث')
            ->assertSee('رضایت کارفرما')
            ->assertSee('نام کارفرما')
            ->assertSee('فایل پیام صوتی')
            ->assertSee('سمت')
            ->assertSee('پروژه‌های مشابه')
            ->assertSee('سئو')
            ->assertSee('اجازه به ارسال دیدگاه');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateProject::class)
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('content')
            ->assertFormFieldExists('logo')
            ->assertFormFieldExists('year')
            ->assertFormFieldExists('services')
            ->assertFormFieldExists('industry')
            ->assertFormFieldExists('duration')
            ->assertFormFieldExists('location')
            ->assertFormFieldExists('employer')
            ->assertFormFieldExists('position')
            ->assertFormFieldExists('testimony')
            ->assertFormFieldExists('voice')
            ->assertFormFieldExists('similar')
            ->assertFormFieldExists('commentable')
            ->assertFormFieldExists('seo_meta.title')
            ->fillForm([
                'title' => 'استقرار سامانه مالی',
                'content' => '<p>شرح پروژه</p>',
                'logo' => Project::LOGOS.'/logo.png',
                'year' => 1402,
                'services' => ['مشاوره', 'پیاده‌سازی'],
                'industry' => 'بانکداری',
                'duration' => '۸ ماه',
                'location' => 'تهران',
                'employer' => 'علی رضایی',
                'position' => 'مدیرعامل',
                'testimony' => 'از همکاری راضی بودیم.',
                'voice' => [UploadedFile::fake()->create('voice.mp3', 100, 'audio/mpeg')],
                'similar' => [$other->getKey()],
                'commentable' => false,
                'seo_meta' => ['title' => 'سامانه مالی', 'description' => 'پروژهٔ بانکی'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('title', 'استقرار سامانه مالی')->firstOrFail();

        $this->assertSame('استقرار-سامانه-مالی', $project->slug);
        $this->assertStringContainsString('شرح پروژه', $project->html());
        $this->assertSame(Project::LOGOS.'/logo.png', $project->logo);
        $this->assertSame(1402, $project->year);
        $this->assertSame(['مشاوره', 'پیاده‌سازی'], $project->services);
        $this->assertSame('بانکداری', $project->industry);
        $this->assertSame('۸ ماه', $project->duration);
        $this->assertSame('تهران', $project->location);
        $this->assertSame('علی رضایی', $project->employer);
        $this->assertSame('مدیرعامل', $project->position);
        $this->assertSame('از همکاری راضی بودیم.', $project->testimony);
        $this->assertStringStartsWith(Project::VOICES.'/', (string) $project->voice);
        $disk->assertExists((string) $project->voice);
        $this->assertSame([$other->getKey()], $project->similar()->pluck('projects.id')->all());
        $this->assertFalse($project->commentable);
        $this->assertSame('سامانه مالی', $project->seoMeta->title);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateProject::class)
            ->fillForm(['title' => 'سال نادرست', 'year' => 999])
            ->call('create')
            ->assertHasFormErrors(['year']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditProject::class, ['record' => $project->getKey()])
            ->fillForm(['title' => 'سامانه مالی بانک'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('سامانه مالی بانک', $project->refresh()->title);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/projects')
            ->assertOk()
            ->assertSee('سامانه مالی بانک')
            ->assertSee('بانکداری');

        $voice = (string) $project->voice;
        Comment::factory()->for($project, 'subject')->create();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditProject::class, ['record' => $project->getKey()])
            ->callAction('delete');

        $this->assertNull(Project::query()->find($project->getKey()));
        $this->assertSame(0, Comment::query()->count());
        $this->assertDatabaseCount('project_similar', 0);
        $disk->assertExists(Project::LOGOS.'/logo.png');
        $disk->assertExists($voice);

        $editor = User::factory()->create(['username' => 'editor_user', 'email' => 'editor@example.com', 'phone' => '09120000003']);
        $editor->grant([Section::PROJECTS]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($editor))->get('/admin/projects')->assertOk();

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::BRANDS]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))->get('/admin/projects')->assertForbidden();
    }

    /**
     * The API lists projects by year a page at a time, searches them, and sends one project with its details.
     */
    public function test_project_api_lists_and_shows_projects(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Project::LOGOS, $this->picture('logo.png'), 'logo.png');
        $disk->put(Project::VOICES.'/voice.mp3', 'ID3');

        $project = Project::factory()->create([
            'title' => 'راه‌اندازی مرکز داده',
            'content' => '<p>درباره پروژه</p>',
            'logo' => Project::LOGOS.'/logo.png',
            'year' => 1403,
            'services' => ['طراحی', 'پشتیبانی'],
            'industry' => 'مخابرات',
            'duration' => '۱۲ ماه',
            'location' => 'اصفهان',
            'employer' => 'مریم احمدی',
            'position' => 'مدیر فنی',
            'testimony' => 'کار دقیق و به‌موقع.',
            'voice' => Project::VOICES.'/voice.mp3',
        ]);
        $older = Project::factory()->create(['title' => 'پورتال سازمانی', 'year' => 1398, 'industry' => 'سلامت', 'location' => 'شیراز']);
        $undated = Project::factory()->create([
            'title' => 'شبکه اداری',
            'year' => null,
            'industry' => 'آموزش',
            'location' => 'تبریز',
            'employer' => null,
            'position' => null,
            'testimony' => null,
        ]);
        $project->similar()->sync([$older->getKey()]);
        Comment::factory()->approved()->for($project, 'subject')->count(2)->create();
        Comment::factory()->for($project, 'subject')->create();

        $this->get('/v1/projects')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'راه‌اندازی مرکز داده')
            ->assertJsonPath('data.0.year', 1403)
            ->assertJsonPath('data.0.logo.path', Project::LOGOS.'/logo.png')
            ->assertJsonPath('data.1.title', 'پورتال سازمانی')
            ->assertJsonPath('data.2.title', 'شبکه اداری')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.services');
        $this->get('/v1/projects?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->get('/v1/projects?q=شیراز')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'پورتال سازمانی');
        $this->get('/v1/projects?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');

        $response = $this->get('/v1/projects/'.urlencode((string) $project->slug))
            ->assertOk()
            ->assertJsonPath('data.services', ['طراحی', 'پشتیبانی'])
            ->assertJsonPath('data.duration', '۱۲ ماه')
            ->assertJsonPath('data.location', 'اصفهان')
            ->assertJsonPath('data.testimonial.name', 'مریم احمدی')
            ->assertJsonPath('data.testimonial.position', 'مدیر فنی')
            ->assertJsonPath('data.testimonial.text', 'کار دقیق و به‌موقع.')
            ->assertJsonPath('data.testimonial.voice.path', Project::VOICES.'/voice.mp3')
            ->assertJsonPath('data.testimonial.voice.name', 'voice.mp3')
            ->assertJsonPath('data.similar.0.title', 'پورتال سازمانی')
            ->assertJsonPath('data.commentable', true)
            ->assertJsonPath('data.comments_count', 2)
            ->assertJsonPath('data.content.content.0.type', 'paragraph');

        $this->assertStringStartsWith('راه‌اندازی مرکز داده', (string) $response->json('data.seo.title'));
        $this->assertSame(url('/storage/'.Project::VOICES.'/voice.mp3'), $response->json('data.testimonial.voice.url'));
        $this->assertSame(url('/projects/'.$project->slug), $response->json('data.url'));

        $this->get('/v1/projects/'.urlencode((string) $undated->slug))->assertOk()->assertJsonPath('data.testimonial', null);
        $this->get('/v1/projects/missing')->assertNotFound()->assertJsonPath('message', 'پروژه پیدا نشد.');
    }

    /**
     * The media page shows who uses a project file, and deleting the file there clears it from the project.
     */
    public function test_deleting_a_library_file_clears_it_from_projects(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Project::LOGOS, $this->picture('logo.png'), 'logo.png');
        $disk->put(Project::VOICES.'/voice.mp3', 'ID3');
        $project = Project::factory()->create([
            'title' => 'توسعه اپلیکیشن فروش',
            'logo' => Project::LOGOS.'/logo.png',
            'voice' => Project::VOICES.'/voice.mp3',
        ]);

        $usage = collect(Library::rows())->pluck('usage', 'path');
        $this->assertSame('لوگوی پروژه توسعه اپلیکیشن فروش', $usage[Project::LOGOS.'/logo.png']);
        $this->assertSame('پیام صوتی کارفرمای توسعه اپلیکیشن فروش', $usage[Project::VOICES.'/voice.mp3']);

        Library::drop(Project::LOGOS.'/logo.png');
        Library::drop(Project::VOICES.'/voice.mp3');

        $project->refresh();
        $this->assertNull($project->logo);
        $this->assertNull($project->voice);
        $disk->assertMissing(Project::VOICES.'/voice.mp3');
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

    /**
     * A one-pixel PNG upload.
     */
    private function picture(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }
}
