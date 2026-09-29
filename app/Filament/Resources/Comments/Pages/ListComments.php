<?php

namespace App\Filament\Resources\Comments\Pages;

use App\Filament\Resources\Comments\CommentResource;
use App\Models\Comment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comment index with a tab per status and the count of each.
 *
 * There is no create button: comments are written on the site.
 * Filament owns the getTabs method name.
 */
class ListComments extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = CommentResource::class;

    /**
     * All comments, then one tab per status.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Comment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $tabs = ['all' => Tab::make('همه')];

        foreach (Comment::statuses() as $status => $label) {
            $count = (int) ($counts[$status] ?? 0);

            $tabs[$status] = Tab::make($label)
                ->badge($count > 0 ? $count : null)
                ->badgeColor(CommentResource::colour($status))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }
}
