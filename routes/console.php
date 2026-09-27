<?php

use App\Support\Library;
use App\Support\Sizes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:sizes', function () {
    $count = 0;

    foreach (Library::rows() as $row) {
        if (Sizes::fits($row['path']) && Sizes::make($row['path']) !== []) {
            $count++;
        }
    }

    $this->info("Built sizes for {$count} images.");
})->purpose('Build the WebP sizes for every image on the public disk');
