<?php

namespace App\Console\Commands;

use App\Http\Controllers\Plan\PlansController;
use App\Models\Language\Language;
use App\Models\Plan\Plan;
use App\Models\Playlist\PlaylistItems;
use App\Models\User\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class translatePlan extends Command
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

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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



        // i18n

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


        $plan = Plan::where('id', $plan_id)->first();

        $this->alert('Translating plan ' . $plan->name . ' starting: ' . Carbon::now());
        if (!$plan) {
            $this->error('Plan with ID:' . $plan_id . ' do not exist');
        } else {
            $bible_ids = explode(',', $bible_ids);
            $user = User::with('projects')->whereId($plan->user_id)->first();
            $plan_controller = new MessagedPlansController();
            foreach ($bible_ids as $key => $bible_id) {
                $request = new Request();
                request()->merge(['bible_id' => $bible_id]);
                try {
                    $this->line('Translating plan to bible ' . $bible_id . ' started ' . Carbon::now());
                    $new_plan = $plan_controller->translate($request, $plan_id, $user, false, false);
                    $plan = Plan::where('id', $new_plan['id'])->first();
                    $language_id = DB::connection('dbp')
                        ->table('bibles')
                        ->select(['language_id'])
                        ->whereId($bible_id)->first();

                    $plan->language_id = $language_id->language_id;
                    $plan->save();

                    $this->line('Calculating duration and verses ' . Carbon::now());
                    foreach ($plan->days as $day) {
                        $playlist_items = PlaylistItems::where('playlist_id', $day['playlist_id'])->get();
                        foreach ($playlist_items as $playlist_item) {
                            $playlist_item->calculateDuration()->save();
                            $playlist_item->calculateVerses()->save();
                        }
                    }

                    $this->info('Translating plan to bible ' . $bible_id . ' finalized ' . Carbon::now());
                    $this->line('');
                } catch (Exception $e) {
                    $this->error('Error translating plan to bible ' . $bible_id . ' ');
                    $this->error('Error message: ' . $e->getMessage() . ' ');
                    $this->error('Error timestamp: ' . Carbon::now() . ' ');
                    $this->line('');
                    $this->question('Please fix the issue translating the plan to ' . $bible_id . ' ');
                    $this->question('To continue the process please run the following command: ');
                    $this->line('');

                    $this->comment("\t<fg=green>php artisan translate:plan " . $plan_id . ' ' . implode(',', array_splice($bible_ids, $key)));
                    break;
                }
            }
        }

        $this->line('');
        $this->alert('Translating plan end: ' . Carbon::now());
    }
}


class MessagedPlansController extends PlansController
{
    public function replyWithError($message, $action = null)
    {
        throw (new Exception($message));
    }
}
