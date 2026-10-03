<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * The API keys list, with create and edit in modals.
 *
 * Creating a key goes through ApiKey::issue, so the plain key exists only long enough to be
 * shown once in ApiKeyResource::reveal.
 *
 * Filament owns the getHeaderActions and getSubheading method names.
 */
class ManageApiKeys extends ManageRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = ApiKeyResource::class;

    /** The page heading and browser tab title. */
    protected static ?string $title = 'تنظیمات API';

    /**
     * The line under the heading.
     */
    public function getSubheading(): ?string
    {
        return 'هر درخواست به API باید سرآیند X-Api-Key با یکی از این کلیدها داشته باشد. درخواست مرورگر فقط از دامنهٔ همان کلید پذیرفته می‌شود و درخواست سرور (بدون Origin) فقط با کلید.';
    }

    /**
     * The create key button.
     *
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('ساخت کلید API')
                ->modalHeading('ساخت کلید API')
                ->using(function (array $data): ApiKey {
                    [$key, $plain] = ApiKey::issue($data);

                    ApiKeyResource::reveal($plain);

                    return $key;
                })
                ->successNotification(null),
        ];
    }
}
