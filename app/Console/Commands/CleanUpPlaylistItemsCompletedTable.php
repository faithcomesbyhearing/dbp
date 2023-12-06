<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanUpPlaylistItemsCompletedTable extends Command
{
    protected $signature = 'userPlaylistItems:cleanup';
    protected $description = 'Cleans up the user playlists';

    public function handle()
    {
        $this->info('DB:Table (playlist_items_completed) cleanup has started ...');

        $db = DB::connection('dbp_users');

        $db->transaction(function () use ($db) {
            $db->statement('DROP TEMPORARY TABLE IF EXISTS playlist_items_completed_temp');
            $db->statement('CREATE TEMPORARY TABLE playlist_items_completed_temp AS SELECT pic.user_id, pic.playlist_item_id, COUNT(pic.user_id) FROM playlist_items_completed pic GROUP BY pic.user_id, pic.playlist_item_id HAVING COUNT(pic.user_id) > 1');
            $db->statement('DELETE playlist_items_completed FROM playlist_items_completed INNER JOIN playlist_items_completed_temp ON playlist_items_completed_temp.user_id = playlist_items_completed.user_id AND playlist_items_completed_temp.playlist_item_id = playlist_items_completed.playlist_item_id'
            );
            $db->statement('INSERT INTO playlist_items_completed (user_id, playlist_item_id) SELECT user_id, playlist_item_id FROM playlist_items_completed_temp');
            $db->statement('DROP TEMPORARY TABLE IF EXISTS playlist_items_completed_temp');
        });

        $this->info('Trying to create (playlist_items_completed_user_id_playlist_item_id_unique) constraint ...');

        if (!constraintExists($db, 'playlist_items_completed', 'playlist_items_completed_user_id_playlist_item_id_unique')) {
            $db->statement('ALTER TABLE playlist_items_completed ADD CONSTRAINT playlist_items_completed_user_id_playlist_item_id_unique UNIQUE (user_id, playlist_item_id)');
        }

        $this->info('DB:Table (playlist_items_completed) cleanup complete.');
    }
}
