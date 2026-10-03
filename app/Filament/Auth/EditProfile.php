<?php

namespace App\Filament\Auth;

use App\Filament\Pages\Profile;
use App\Filament\Schemas\Fields;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * The signed-in user's own edit page at /admin/profile/edit, opened from the user menu and the profile page.
 *
 * It reuses the user form fields: avatar, name, username, email, phone, education, gender, and the
 * password box that asks for the current password. Personnel code, national code, and job are shown
 * locked, and sections, roles, and status are not on this page at all. Saving keeps only the keys in
 * EDITABLE, so a crafted request cannot change anything else.
 *
 * Extending:
 * - A field a user may change about themselves goes in the form and in EDITABLE together.
 * - The password is hashed before saving so Filament's session check keeps the user signed in.
 * - Filament's EditProfile owns form, defaultForm, mutateFormDataBeforeSave, getRedirectUrl, getSlug, and getBreadcrumbs.
 */
class EditProfile extends BaseEditProfile
{
    /** The only columns this page may write. */
    private const EDITABLE = ['avatar', 'firstname', 'lastname', 'username', 'email', 'phone', 'degree', 'major', 'gender', 'password'];

    /** The page heading and browser tab title. */
    protected static ?string $title = 'ویرایش پروفایل';

    /** Sits under the profile page, which owns /admin/profile. */
    protected static ?string $slug = 'profile/edit';

    /**
     * Stacked labels in two columns, like the user form, instead of Filament's inline labels.
     *
     * Filament owns this method name.
     */
    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)
            ->inlineLabel(false)
            ->columns(2);
    }

    /**
     * The avatar, account details, personnel details, and password box.
     *
     * Filament owns this method name.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Fields::avatar(),
            Section::make('اطلاعات حساب')
                ->columns(2)
                ->columnSpan(2)
                ->schema(Fields::account(password: false)),
            Fields::staff(locked: true),
            Fields::password(),
        ]);
    }

    /**
     * Keeps only the editable columns and hashes a new password.
     *
     * Filament owns this method name.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $data = Arr::only($data, self::EDITABLE);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make((string) $data['password']);
        }

        return $data;
    }

    /**
     * Back to the profile page after saving.
     *
     * Filament owns this method name.
     */
    protected function getRedirectUrl(): ?string
    {
        return Profile::getUrl();
    }

    /**
     * پروفایل من › ویرایش.
     *
     * Filament owns this method name.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            Profile::getUrl() => 'پروفایل من',
            'ویرایش',
        ];
    }
}
