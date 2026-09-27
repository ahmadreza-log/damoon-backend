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

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AccountFields::make());
    }

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

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof User && $record->isOwner()) {
            return Response::deny();
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
