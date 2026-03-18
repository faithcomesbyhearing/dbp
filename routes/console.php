<?php

use App\Console\Commands\DeleteDraftPlaylistsPlans;
use App\Console\Commands\DeleteTemporaryZipFiles;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DeleteDraftPlaylistsPlans::class)
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command(DeleteTemporaryZipFiles::class)
    ->hourly()
    ->withoutOverlapping();
