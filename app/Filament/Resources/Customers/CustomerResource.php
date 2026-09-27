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

/**
 * The panel customers section, in the access group.
 *
 * These accounts sign in through the API. Their token ability is api, and this screen only shows the profile.
 * The form is shared with users through AccountFields.
 *
 * Extending:
 * - Add a new column in table, and in AccountFields when it should be editable.
 * - Filament owns the form, table, and getPages method names.
 */
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

    /**
     * Create and edit form for a customer.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components(AccountFields::make());
    }

    /**
     * Customer list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')->label('نام کاربری')->searchable(),
                TextColumn::make('firstname')->label('نام')->searchable(),
                TextColumn::make('lastname')->label('نام خانوادگی')->searchable(),
                TextColumn::make('email')->label('ایمیل')->searchable(),
                TextColumn::make('phone')->label('شماره تلفن'),
                TextColumn::make('last_login')->label('آخرین ورود')->dateTime()->placeholder('—'),
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
     * Index, create, and edit pages.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
