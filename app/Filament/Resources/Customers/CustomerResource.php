<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Schemas\Fields;
use App\Support\Shamsi;
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
 * The form is shared with users through Fields::account.
 *
 * Extending:
 * - Add a new column in table, and in Fields::account when it should be editable.
 * - Filament owns the form, table, and getPages method names.
 */
class CustomerResource extends Resource
{
    /** The Eloquent model this page lists, creates, and edits: site members. */
    protected static ?string $model = Customer::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'مشتریان';

    /** The singular name used in buttons and headings, such as "ایجاد مشتری". */
    protected static ?string $modelLabel = 'مشتری';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'مشتریان';

    /** The menu group shared with users and roles. */
    protected static string|\UnitEnum|null $navigationGroup = 'دسترسی';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    /** The position inside the access group; customers come after users. */
    protected static ?int $navigationSort = 2;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'username';

    /**
     * Create and edit form for a customer.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components(Fields::account());
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
