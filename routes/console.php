<?php

use App\Models\Entry;
use App\Support\Library;
use App\Support\Sizes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Artisan commands written as closures.
 *
 * Commands here run with php artisan <name>. Scheduled tasks are added with Schedule in this
 * file as well; they run when php artisan schedule:run is called every minute (cron on the server).
 *
 * Extending:
 * - Move a command into app/Console/Commands once it grows options or needs its own tests.
 */

/**
 * Laravel's sample command: prints a random quote.
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Builds the WebP sizes (thumb, small, medium, large) for every image already on the public disk.
 *
 * New uploads get their sizes when they are saved; this command catches images that
 * were uploaded before sizes existed or whose size files were removed. Files that
 * Sizes::fits() rejects, such as non-images, are skipped. The count only includes
 * images for which at least one size file was written.
 */
Artisan::command('media:sizes', function () {
    $count = 0;

    foreach (Library::rows() as $row) {
        if (Sizes::fits($row['path']) && Sizes::make($row['path']) !== []) {
            $count++;
        }
    }

    $this->info("Built sizes for {$count} images.");
})->purpose('Build the WebP sizes for every image on the public disk');

/**
 * Deletes form messages older than the retention period in the forms settings, with their files.
 *
 * Nothing is deleted while the retention period is empty.
 */
Schedule::command('model:prune', ['--model' => [Entry::class]])->daily();
