<?php

namespace App\Console\Commands;

use App\Models\Playlist\PlaylistItems;
use App\Models\Plan\Plan;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SyncPlaylistDuration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:playlistDuration {--playlist-id= : playlist ID} {--plan-id= : plan ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the playlist items verses and duration on the database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    private function playlist_verses_and_duration($playlist_items)
    {
        $this->line(
            Carbon::now() .
                ' Sync starting for ' .
                sizeof($playlist_items) .
                ' items'
        );
        foreach ($playlist_items as $key => $playlist_item) {
            $this->line(
                Carbon::now() .
                    ' Calculating duration and verses of item ' .
                    ($key + 1) .
                    ' --> Book: ' .
                    $playlist_item->book_id .
                    ', Chapter Start: ' .
                    $playlist_item->chapter_start .
                    ', Chapter End: ' .
                    $playlist_item->chapter_end .
                    ' started'
            );
            $playlist_item->calculateDuration()->save();
            $playlist_item->calculateVerses()->save();
            $this->info(
                Carbon::now() .
                    ' Calculating duration and verses of item ' .
                    ($key + 1) .
                    ' finalized'
            );
            $this->line('');
        }
    }

    public function handle()
    {
        $sync_type = null;
        $plan_playlist_id = null;

        if ($this->option('playlist-id')) {
            $sync_type = 'Playlist';
            $plan_playlist_id = $this->option('playlist-id');
        } elseif ($this->option('plan-id')) {
            $sync_type = 'Plan';
            $plan_playlist_id = $this->option('plan-id');
        }

        if (!$sync_type || !$plan_playlist_id) {
            $sync_type = $this->choice(
                'What do you want to sync?',
                ['Playlist', 'Plan'],
                0
            );
            $this->alert(
                Carbon::now() . ' ' . $sync_type . ' items sync started '
            );

            $plan_playlist_id = $this->ask('Enter the ' . $sync_type . ' id:');
        }

        if ($sync_type === 'Plan') {
            $plan = Plan::where('id', $plan_playlist_id)->first();

            if ($plan) {
                foreach ($plan->days as $key => $day) {
                    $this->line(
                        Carbon::now() .
                            ' Calculating duration and verses for day ' .
                            ($key + 1) .
                            ' of the plan'
                    );
                    $playlist_items = PlaylistItems::where(
                        'playlist_id',
                        $day['playlist_id']
                    )->get();
                    $this->playlist_verses_and_duration($playlist_items);
                    $this->line('');
                }
            } else {
                $this->error(
                    Carbon::now() .
                        ' Plan with id: ' .
                        $plan_playlist_id .
                        ' does not exists '
                );
            }
        } else {
            $playlist_items = PlaylistItems::when($plan_playlist_id, function (
                $query,
                $plan_playlist_id
            ) {
                $query->where('playlist_id', $plan_playlist_id);
            })->get();

            if (count($playlist_items) > 0) {
                $this->playlist_verses_and_duration($playlist_items);
            } else {
                $this->error(
                    Carbon::now() .
                        ' Playlist with id: ' .
                        $plan_playlist_id .
                        ' does not exists or has no items'
                );
            }
        }

        $this->alert(
            Carbon::now() . ' ' . $sync_type . ' items sync Finalized '
        );
    }
}
