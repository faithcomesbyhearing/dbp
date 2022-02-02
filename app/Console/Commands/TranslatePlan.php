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

        $language = Language::where('iso', 'eng')->select(['iso', 'id'])->first();

        if (!$language) {
            $this->error('Language ENG do not exist');
        }

        $GLOBALS['i18n_iso'] = $language->iso;
        $GLOBALS['i18n_id']  = $language->id;

        $plan = $this->plan_service->getPlanById($plan_id);

        if (!$plan) {
            $this->error('Plan with ID:' . $plan_id . ' does not exist');
        } else {
            $this->alert('Translating plan ' . $plan->name . ' starting: ' . Carbon::now());
            $bible_ids = explode(',', $bible_ids);

            foreach ($bible_ids as $key => $bible_id) {
                try {
                    $this->line('Translating plan to bible ' . $bible_id . ' started ' . Carbon::now());
                    $bible = Bible::whereId($bible_id)->first();
                    if (!$bible) {
                        $this->alert('Bible with ID:' . $bible_id . ' does not exist' . Carbon::now());
                        continue;
                    }
                    $translated_plan = $this->plan_service->translate($plan_id, $bible, $plan->user_id, false);
                    $plan = Plan::where('id', $translated_plan['id'])->first();

                    $this->line('Calculating Duration and Verses ' . Carbon::now());
                    $this->plan_service->calculateDurationAndVersesUpdatePlan($plan, true);

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
