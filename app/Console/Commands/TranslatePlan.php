<?php

namespace App\Console\Commands;

use App\Http\Controllers\Plan\PlansController;
use App\Models\Language\Language;
use App\Models\Plan\Plan;
use App\Models\Playlist\PlaylistItems;
use App\Models\User\User;
use App\Models\Bible\Bible;
use App\Services\Plans\PlanService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TranslatePlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate:plan {plan_id} {bible_ids}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to translate a plan to a list of bibles';

    private $plan_service;
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->plan_service = new PlanService();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $plan_id = $this->argument('plan_id');
        $bible_ids = $this->argument('bible_ids');

        $cache_params = ['eng'];
        $current_language = cacheRemember('selected_api_language', $cache_params, now()->addDay(), function () {
            $language = Language::where('iso', 'eng')->select(['iso', 'id'])->first();
            return [
                'i18n_iso' => $language->iso,
                'i18n_id'  => $language->id
            ];
        });
        $GLOBALS['i18n_iso'] = $current_language['i18n_iso'];
        $GLOBALS['i18n_id']  = $current_language['i18n_id'];

        $plan = $this->plan_service->getPlanById($plan_id);

        if (!$plan) {
            $this->error('Plan with ID:' . $plan_id . ' do not exist');
        } else {
            $this->alert('Translating plan ' . $plan->name . ' starting: ' . Carbon::now());
            $bible_ids = explode(',', $bible_ids);

            foreach ($bible_ids as $key => $bible_id) {
                try {
                    $this->line('Translating plan to bible ' . $bible_id . ' started ' . Carbon::now());
                    $bible = cacheRemember(
                        'bible_translate',
                        [$bible_id],
                        now()->addDay(),
                        function () use ($bible_id) {
                            return Bible::whereId($bible_id)->first();
                        }
                    );
                    $translated_plan = $this->plan_service->translate($plan_id, $bible, $plan->user_id, false, false);
                    $plan = Plan::where('id', $translated_plan['id'])->first();

                    $this->line('Calculating duration and verses ' . Carbon::now());
                    foreach ($plan->days as $day) {
                        $playlist_items = PlaylistItems::where('playlist_id', $day['playlist_id'])->get();
                        foreach ($playlist_items as $playlist_item) {
                            $playlist_item->calculateDuration()->save();
                            $playlist_item->calculateVerses()->save();
                        }
                    }

                    $this->info('Translating plan to bible ' . $bible_id . ' finalized ' . Carbon::now());
                    $this->info('Plan Translated ID: ' . $plan->id . ' ' . Carbon::now());
                    $this->info('Plan Translated Language ID: ' . $plan->language_id . ' ' . Carbon::now());
                    $this->line('');
                } catch (Exception $e) {
                    $this->error('Error translating plan to bible ' . $bible_id . ' ');
                    $this->error('Error message: ' . $e->getMessage() . ' ');
                    $this->error('Error timestamp: ' . Carbon::now() . ' ');
                    $this->line('');
                    $this->question('Please fix the issue translating the plan to ' . $bible_id . ' ');
                    $this->question('To continue the process please run the following command: ');
                    $this->line('');

                    $this->comment(
                        "\t<fg=green>php artisan translate:plan " . $plan_id . ' ' . implode(
                            ',',
                            array_splice($bible_ids, $key)
                        )
                    );
                    break;
                }
            }
        }

        $this->line('');
        $this->alert('Translating plan end: ' . Carbon::now());
    }
}
