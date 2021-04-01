<?php

namespace App\Console\Commands;

use App\Models\Bible\Bible;
use App\Models\User\Study\Highlight;
use App\Models\User\Study\Bookmark;
use App\Models\User\Study\Note;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class syncV4Annotations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:v4Annotations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the v4 annotations that still use v2 bibles';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    private function update_annotations($annotation_type, $transition_bibles, $target_annotations) 
    {
        if (sizeof($target_annotations) >= 1) {
            foreach ($target_annotations->chunk(5000) as $key=>$chunk) {
                $ids = [];
                $cases = [];
                $v4_bibles = [];
                
                foreach ($chunk as $target_annotation) {
                    $cases[] = "WHEN {$target_annotation->id} then ?";
                    $v4_bibles[] = $transition_bibles[$target_annotation->bible_id];
                    $ids[] = $target_annotation->id;
                }
                
                $query_ids = implode(',', $ids);
                $query_cases = implode(' ', $cases);

                if (!empty($ids)) {
                    DB::connection('dbp_users')->update("UPDATE user_{$annotation_type} SET `bible_id` = CASE `id` {$query_cases} END WHERE `id` in ({$query_ids})", $v4_bibles);
                }
                $this->line(Carbon::now() . ' Sync was successfull for the first ' . sizeof($ids) * ($key + 1) . ' v4 ' . $annotation_type);
            }
        } else {
            $this->line(Carbon::now() . ' No v4 ' . $annotation_type . ' to sync');
        }
    }

    public function handle()
    {
        // when the queries are too big, this is needed for php to run
        ini_set('memory_limit', '-1');

        $this->alert(Carbon::now() . ' Sync starting for v4 annotations');
        $syncFile = config('settings.bibleSyncFilePath');
        $file = fopen($syncFile, 'r');
        $transition_bibles = [];
        
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($line && $line[0] && $line[1] && $line[0] !== " " && $line[1] !== " ") {
                $transition_bibles[$line[0]] = $line[1];
            }
        }
        fclose($file);

        $valid_bibles = Bible::whereIn('id', array_values($transition_bibles))->count();
        // does not count the title of the csv
        if ((sizeof($transition_bibles) - 1) > $valid_bibles) {
          $this->error('One or more bibles on the translation to v4 dont exist');
          return;
        }

        $this->alert(Carbon::now() . ' Sync starting for v4 Highlights');
        $highlights = Highlight::whereIn('bible_id', array_keys($transition_bibles))->get();
        $this->update_annotations('highlights', $transition_bibles, $highlights);
        

        $this->alert(Carbon::now() . ' Sync starting for v4 Bookmarks');
        $bookmarks = Bookmark::whereIn('bible_id', array_keys($transition_bibles))->get();
        $this->update_annotations('bookmarks', $transition_bibles, $bookmarks);


        $this->alert(Carbon::now() . ' Sync starting for v4 Notes');
        $notes = Note::whereIn('bible_id', array_keys($transition_bibles))->get();
        $this->update_annotations('notes', $transition_bibles, $notes);
    }
}
