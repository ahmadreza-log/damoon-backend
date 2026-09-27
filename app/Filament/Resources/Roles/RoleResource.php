<?php

namespace App\Filament\Resources\Roles;

use App\Auth\Section;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * The panel roles section, in the access group.
 *
 * A role has a Persian name, an English key, and the sections it may open.
 * Developer and owner are always present and always keep every section.
 *
 * Extending:
 * - Add a section in App\Auth\Section. The checklist and the fixed roles both read it.
 * - A fixed role belongs in RoleName::fixed, not as a row someone can delete.
 * - Filament owns the form, table, and getPages method names.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationLabel = 'نقش‌ها';

    protected static ?string $modelLabel = 'نقش';

    protected static ?string $pluralModelLabel = 'نقش‌ها';

    protected static string|\UnitEnum|null $navigationGroup = 'دسترسی';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'label';

    /**
     * Create and edit form for a role.
     *
     * The key and the section list are locked on developer and owner.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('نام')
                ->required(fn (?Role $record): bool => ! $record?->locked())
                ->maxLength(255)
                ->disabled(fn (?Role $record): bool => (bool) $record?->locked()),
            TextInput::make('name')
                ->label('کلید')
                ->required(fn (?Role $record): bool => ! $record?->locked())
                ->maxLength(64)
                ->rule('alpha_dash:ascii')
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record): bool => (bool) $record?->locked())
                ->dehydrateStateUsing(fn (mixed $state): ?string => self::slug($state))
                ->helperText(fn (?Role $record): string => $record?->locked()
                    ? 'این نقش همیشه وجود دارد و به همه بخش‌ها دسترسی دارد.'
                    : 'فقط حروف انگلیسی، عدد، خط تیره و زیرخط.'),
            FormSection::make('دسترسی بخش‌ها')
                ->description('بخش‌هایی از پنل که این نقش می‌تواند باز کند.')
                ->columnSpan(2)
                ->schema([
                    CheckboxList::make('sections')
                        ->label('بخش‌ها')
                        ->options(Section::options())
                        ->columns(1)
                        ->bulkToggleable()
                        ->disabled(fn (?Role $record): bool => (bool) $record?->locked())
                        ->helperText(fn (?Role $record): ?string => $record?->locked()
                            ? 'این نقش همیشه به همه بخش‌ها دسترسی دارد.'
                            : null),
                ]),
        ]);
    }

    /**
     * Role list. Sections stay on the edit page.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('نام')->searchable(),
                TextColumn::make('name')->label('کلید')->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => ! $record->locked()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Makes developer and owner undeletable, even if another policy would allow it.
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof Role && $record->locked()) {
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    /**
     * Stores the key in lower case.
     */
    private static function slug(mixed $state): ?string
    {
        if (! is_string($state)) {
            return null;
        }

        $state = strtolower(trim($state));

        return $state === '' ? null : $state;
    }
}
