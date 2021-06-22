<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible\BibleFileset;
use App\Models\Bible\Book;
use App\Models\User\Study\Note;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class syncLiveBibleIsNotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncLiveBibleIs:notes {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the Notes with the live bibleis Database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $db_name = config('database.connections.livebibleis_users.database');

        $from_date = $this->argument('date');
        $from_date = Carbon::createFromFormat('Y-m-d', $from_date)->startOfDay();

        $filesets = BibleFileset::with('bible')->get();
        $this->bible_ids = [];

        echo "\n" . Carbon::now() . ': liveBibleis to v4 notes sync started.';
        $chunk_size = config('settings.v2V4SyncChunkSize');
        $syncFile = config('settings.bibleSyncFilePath');
        $transition_bibles = convertCsvToArrayMap($syncFile);

        DB::connection($db_name)
            ->table('user_notes')
            ->where('created_at', '>=', $from_date)
            ->orderBy('id')->chunk($chunk_size, function ($notes) use ($filesets, $transition_bibles) {
                $bible_ids = $notes->pluck('bible_id')->reduce(function ($carry, $item) use ($filesets) {
                    // checks if filesets actually exist for the given bible
                    $fileset = getFilesetFromDamId($item, true, $filesets);
                    if ($fileset) {
                        $carry[$item] = $fileset;
                        $this->bible_ids[$item] = $fileset;
                    }
                    return $carry;
                }, []);

                $notes = $notes->map(function ($note) use ($bible_ids, $transition_bibles) {
                    $bible_id = $note->bible_id;
                    $v4_bible_id = array_key_exists($bible_id, $transition_bibles) ? $transition_bibles[$bible_id] : $bible_id;
                    return [
                        'user_id'     => $note->user_id,
                        'bible_id'    => $bible_ids[$v4_bible_id]->bible->first()->id,
                        'book_id'     => $note->book_id,
                        'notes'       => $note->notes,
                        'chapter'     => $note->chapter,
                        'verse_start' => $note->verse_start,
                        'verse_end'   => $note->verse_end,
                        'created_at'  => Carbon::createFromTimeString($note->created_at),
                        'updated_at'  => Carbon::createFromTimeString($note->updated_at),
                    ];
                });

                $notes = $notes->filter(function ($note) use ($bible_ids) {
                    $note_exists = Note::where([
                        'user_id'       => $note['user_id'], 
                        'bible_id'      => $note['bible_id'],
                        'book_id'       => $note['book_id'],
                        'chapter'       => $note['chapter'],
                        'verse_start'   => $note['verse_start'],
                        'verse_end'     => $note['verse_end'],
                        'notes'         => $note['notes'],
                    ])->first();
                    return validateLiveBibleIsAnnotation($note, $bible_ids, $note_exists);
                });

                $chunks = $notes->chunk(5000);
                foreach ($chunks as $chunk) {
                    Note::insert($chunk->toArray());
                }

                echo "\n" . Carbon::now() . ': Inserted ' . sizeof($notes) . ' new liveBibleis notes.';
            });
        echo "\n" . Carbon::now() . ": liveBibleis to v4 notes sync finalized.\n";
    }
}
