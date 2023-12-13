<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use App\Models\Plan\Plan;
use App\Models\Playlist\PlaylistItems;

/**
 * Command to synchronize the duration of all plans
 *
 * This command will find all the featured plans that have attached playlist items with
 * a duration of 0 and synchronize them. It also offers the possibility to only
 * print out the plans without performing any changes.
 */
class SyncFeaturedPlansDuration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sync-featured-plans-without-duration
                            {--only-info : Option to only display the plans that need synchronization without actually synchronizing them}';

    /**
     * The console Sync all plans with Plan ID, Plan Name, and Number of Items with Duration 0.
     *
     * @var string
     */
    protected $description = 'Synchronize all featured plans that have associated playlist items with a duration of 0';

    /**
     * Headers for the table to be displayed
     *
     * @var array
     */
    protected $headers = ['Plan ID', 'Num. Items with Duration 0', 'Plan Name'];

    /**
     * Retrieve all plans that have items with a duration of 0
     *
     * @return Collection A collection of Plan models
     */
    protected function getPlansWithoutDuration(): Collection
    {
        return Plan::select([
            'plans.id',
            \DB::raw('COUNT(playlist_items.id) as num_items'),
            'plans.name',
        ])
            ->join('user_playlists', 'user_playlists.plan_id', 'plans.id')
            ->join(
                'playlist_items',
                'playlist_items.playlist_id',
                'user_playlists.id'
            )
            ->groupBy(['plans.id', 'plans.name'])
            ->where('playlist_items.duration', 0)
            ->where('plans.featured', 1)
            ->whereNull('plans.deleted_at')
            ->limit(25)
            ->get()
            ->each(function ($plan) {
                $plan->name = "\u{202D}" . $plan->name . "\u{202C}";
                return $plan;
            });
    }

    /**
     * Render a table displaying the plans that have items with a duration of 0
     *
     * @param Collection $plans A collection of Plan models
     * @return void
     */
    protected function renderTablePlansWithoutDuration(
        Collection $plans
    ): void {
        $max_lengths = [
            'id' => max(
                $plans->max(function (Plan $plan) {
                    return mb_strlen($plan->id);
                }),
                mb_strlen($this->headers[0])
            ),
            'num_items' => max(
                $plans->max(function (Plan $plan) {
                    return mb_strlen($plan->num_items);
                }),
                mb_strlen($this->headers[1])
            ),
            'name' => max(
                $plans->max(function (Plan $plan) {
                    return mb_strlen($plan->name);
                }),
                mb_strlen($this->headers[2])
            ),
        ];

        $table = new Table($this->output);
        $table
            ->setHeaders($this->headers)
            ->setRows($plans->toArray())
            ->setStyle('borderless')
            ->setColumnMaxWidth(0, $max_lengths['id'])
            ->setColumnMaxWidth(1, $max_lengths['num_items'])
            ->setColumnMaxWidth(2, $max_lengths['name']);

        $table->render();

        $table_footer = new Table($this->output);
        $table_footer
            ->setHeaders(['', '', 'Plans with Duration 0'])
            ->setRows([['Total: ', '', $plans->count()]])
            ->setStyle('box')
            ->setColumnWidth(0, $max_lengths['id'])
            ->setColumnWidth(1, $max_lengths['num_items'])
            ->setColumnWidth(2, $max_lengths['name']);

        $table_footer->render();
    }

    /**
     * Synchronize a plan
     *
     * This method will go through all the items of a plan, and for each of them,
     * it will recalculate and update its duration and verses.
     *
     * @param Plan $plan The plan to be synchronize
     * @return void
     */
    protected function syncPlan(Plan $plan): void
    {
        $this->info(
            PHP_EOL .
                Carbon::now() .
                ' Sync duration values for plan: ' .
                $plan->name
        );

        $bar_by_plan = new ProgressBar($this->output, $plan->num_items);
        $plan = Plan::where('id', $plan->id)->first();
        foreach ($plan->days as $day) {
            $playlist_items = PlaylistItems::where(
                'playlist_id',
                $day->playlist_id
            )->get();

            foreach ($playlist_items as $playlist_item) {
                $playlist_item->calculateDuration()->save();
                $playlist_item->calculateVerses()->save();
                $bar_by_plan->advance();
            }
        }
        $bar_by_plan->finish();
        $this->info('');
    }

    /**
     * Execute the console command.
     *
     * Depending on the only-info option, this command will either only print
     * the plans that need synchronization or it will also perform the
     * synchronization.
     *
     * @return int 0 if the command was successful, 1 otherwise
     */
    public function handle()
    {
        $only_info = $this->option('only-info');
        $this->info(Carbon::now() . ' Calculing plans to sync... ');

        $plans = $this->getPlansWithoutDuration();
        $this->renderTablePlansWithoutDuration($plans);

        if (!$only_info) {
            $this->info(
                Carbon::now() . ' Sync playlist items of the plans... '
            );
            ProgressBar::setFormatDefinition(
                'custom',
                ' %current%/%max% -- %message% %percent%%'
            );
            $bar = new ProgressBar($this->output, $plans->count());
            $bar->setFormat('custom');
            $bar->setMessage('Sync Plans...');
            $bar->start();

            foreach ($plans as $plan) {
                $this->syncPlan($plan);
                $bar->setMessage('Sync Plans...');
                $bar->advance();
            }

            $bar->finish();
            $this->info(PHP_EOL . Carbon::now() . ' items sync Finalized! ');
        }

        return 0;
    }
}
