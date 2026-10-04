<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\CleanupCompletedJobFilesJob;
use App\Jobs\CleanupExpiredSessionsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CleanupExpiredSessionsJob)->hourly();
Schedule::job(new CleanupCompletedJobFilesJob)->everyThirtyMinutes();

