<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Schemas\AccountFields;
use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationLabel = 'مشتریان';

    protected static ?string $modelLabel = 'مشتری';

    protected static ?string $pluralModelLabel = 'مشتریان';

    protected static string|\UnitEnum|null $navigationGroup = 'دسترسی';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

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

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
