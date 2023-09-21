<?php

namespace App\Console;

use App\Console\Commands\DeleteDraftPlaylistsPlans;
use App\Console\Commands\DeleteTemporaryZipFiles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Wiki\SyncAlphabets::class,
        Commands\Wiki\SyncLanguageDescriptions::class,
        Commands\Wiki\OrgDigitalBibleLibraryCompare::class,

        Commands\StudyFormats\fetchTyndalePeople::class,

        Commands\GenerateOpenApiDoc::class,

        Commands\TranslatePlan::class,
        Commands\TranslatePlaylist::class,
        Commands\encryptNote::class,

        Commands\SyncPlaylistDuration::class,
        Commands\SyncFeaturedPlansDuration::class,
        Commands\DeleteDraftPlaylistsPlans::class,
        Commands\DeleteTemporaryZipFiles::class,

        Commands\showEnvironment::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(DeleteDraftPlaylistsPlans::class)
            ->hourly()
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command(DeleteTemporaryZipFiles::class)
            ->hourly()
            ->withoutOverlapping();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
