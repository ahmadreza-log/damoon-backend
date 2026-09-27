<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Schemas\AccountFields;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
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
 * The form comes from AccountFields so it stays the same as the customer form.
 *
 * Extending:
 * - Add a new column in table, and in AccountFields when the user should edit it.
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
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components(AccountFields::make());
    }

    /**
     * User list. Roles are shown as badges.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')->label('کاربری')->searchable(),
                TextColumn::make('firstname')->label('نام')->searchable(),
                TextColumn::make('lastname')->label('خانوادگی')->searchable(),
                TextColumn::make('email')->label('ایمیل')->searchable(),
                TextColumn::make('phone')->label('تلفن'),
                TextColumn::make('roles')->label('نقش‌ها')->badge(),
                TextColumn::make('last_login')->label('ورود')->dateTime()->placeholder('—'),
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
