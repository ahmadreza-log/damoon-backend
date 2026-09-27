<?php

namespace App\Filament\Resources\Users;

use App\Auth\Section;
use App\Support\Shamsi;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Schemas\Fields;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * The panel users section, in the access group.
 *
 * Deleting the owner is blocked here. The model also refuses the delete in deleting.
 * The account fields stay shared with customers. Staff profile and the password box are only on this form.
 * The edit page adds the section checklist after the password box.
 *
 * Extending:
 * - Add a shared column in the table and in Fields::account.
 * - Add a staff-only field in Fields::staff, not on the customer form.
 * - A new panel section is a permission in App\Auth\Section, not a new column here.
 * - Filament owns the form, table, and getPages method names.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'کاربران';

    protected static ?string $modelLabel = 'کاربر';

    protected static ?string $pluralModelLabel = 'کاربران';

    protected static string|\UnitEnum|null $navigationGroup = 'دسترسی';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    /**
     * Create and edit form for a user.
     *
     * The section checklist is only on edit. A new user has no sections until then.
     * The owner sees every section checked and cannot change that list.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Fields::avatar(),
            ...Fields::account(password: false),
            Fields::staff(),
            Fields::password(),
            Fields::status(),
            FormSection::make('دسترسی بخش‌ها')
                ->description('بخش‌هایی از پنل که این کاربر می‌تواند باز کند.')
                ->visible(fn(?User $record): bool => $record instanceof User)
                ->columnSpan(2)
                ->schema([
                    CheckboxList::make('sections')
                        ->label('بخش‌ها')
                        ->options(Section::options())
                        ->columns(1)
                        ->bulkToggleable()
                        ->disabled(fn(?User $record): bool => (bool) $record?->owner())
                        ->helperText(fn(?User $record): ?string => $record?->owner()
                            ? 'مالک سامانه به همه بخش‌ها دسترسی دارد.'
                            : null),
                ]),
        ]);
    }

    /**
     * User list. Username, email, and sections stay on the edit page.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('firstname')->label('نام')->searchable(),
                TextColumn::make('lastname')->label('نام خانوادگی')->searchable(),
                TextColumn::make('phone')->label('شماره تلفن'),
                TextColumn::make('last_login')->label('آخرین ورود')->jalaliDateTime(timezone: Shamsi::ZONE)->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Makes the owner undeletable, even if another policy would allow it.
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof User && $record->owner()) {
            return Response::deny();
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    /**
     * Index, create, and edit pages.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
