<?php

namespace App\Console\Commands;

use App\Http\Controllers\Playlist\PlaylistsController;
use App\Models\Language\Language;
use App\Models\Playlist\Playlist;
use App\Models\Playlist\PlaylistItems;
use App\Models\User\User;
use App\Models\Bible\Bible;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Plans\PlaylistService;

class TranslatePlaylist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate:playlist {playlist_id} {bible_ids}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to translate a playlist to a list of bibles';

    private $playlist_service;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->playlist_service = new PlaylistService();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $playlist_id = $this->argument('playlist_id');
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

        $playlist = Playlist::findOne($playlist_id);

        if (!$playlist) {
            $this->error('Playlist with ID:' . $playlist_id . ' does not exist');
        } else {
            $this->alert('Translating playlist ' . $playlist->name . ' starting: ' . Carbon::now());
            $bible_ids = explode(',', $bible_ids);
            foreach ($bible_ids as $key => $bible_id) {
                try {
                    $this->line('Translating playlist to bible ' . $bible_id . ' started ' . Carbon::now());
                    $bible = cacheRemember(
                        'bible_translate',
                        [$bible_id],
                        now()->addDay(),
                        function () use ($bible_id) {
                            return Bible::whereId($bible_id)->first();
                        }
                    );
                    $translated_playlist = $this->playlist_service->translate($playlist_id, $bible, $playlist->user_id);
                    $playlist = Playlist::findOne($translated_playlist['id']);

                    $playlist_items = PlaylistItems::when($playlist_id, function ($query, $playlist_id) {
                        $query->where('playlist_id', $playlist_id);
                    })->get();
                    $this->line(Carbon::now() . ' Sync starting for ' . sizeof($playlist_items) . ' items');
                    foreach ($playlist_items as $key => $playlist_item) {
                        $this->line(
                            Carbon::now() . ' Calculating duration and verses of item ' . ($key + 1) . ' started'
                        );
                        $playlist_item->calculateDuration()->save();
                        $playlist_item->calculateVerses()->save();
                        $this->info(
                            Carbon::now() . ' Calculating duration and verses of item ' . ($key + 1) . ' finalized'
                        );
                        $this->line('');
                    }

                    $this->info('Translating playlist to bible ' . $bible_id . ' finalized ' . Carbon::now());
                    $this->info('Translating playlist to language ID' . $bible->language_id . ' ' . Carbon::now());
                    $this->info('Plan Translated ID: ' . $playlist->id . ' ' . Carbon::now());
                    $this->info('Plan Translated Language ID: ' . $playlist->language_id . ' ' . Carbon::now());

                    $this->line('');
                } catch (Exception $e) {
                    $this->error('Error translating playlist to bible ' . $bible_id . ' ');
                    $this->error('Error message: ' . $e->getMessage() . ' ');
                    $this->error('Error timestamp: ' . Carbon::now() . ' ');
                    $this->line('');
                    $this->question('Please fix the issue translating the playlisy to ' . $bible_id . ' ');
                    $this->question('To continue the process please run the following command: ');
                    $this->line('');

                    $this->comment("\t<fg=green>php artisan translate:playlisy " . $playlist_id . ' ' . implode(',', array_splice($bible_ids, $key)));
                    break;
                }
            }
        }

        $this->line('');
        $this->alert('Translating playlist end: ' . Carbon::now());
    }
}
