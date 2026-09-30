<?php

namespace Database\Seeders;

use App\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Seeder;

/**
 * One sample project for each title in ProjectFactory::TITLES, each linked to two others as similar projects.
 *
 * A project whose title already exists is kept, so running this again adds nothing new.
 *
 * Extending:
 * - Another sample project is one more entry in ProjectFactory::TITLES.
 */
class ProjectSeeder extends Seeder
{
    /**
     * Creates the sample projects and their similar links.
     */
    public function run(): void
    {
        $projects = collect(ProjectFactory::TITLES)->map(fn (string $title): Project => Project::query()->firstWhere('title', $title)
            ?? Project::factory()->create(['title' => $title]));

        foreach ($projects as $project) {
            if ($project->similar()->exists()) {
                continue;
            }

            $others = $projects->reject(fn (Project $other): bool => $other->is($project));

            $project->similar()->sync($others->random(min(2, $others->count()))->pluck('id')->all());
        }
    }
}
