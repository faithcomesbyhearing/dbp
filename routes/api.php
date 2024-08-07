<?php

// VERSION 4 | Countries
Route::name('v4_countries.all')
->middleware('AccessControl')
->get(
    'countries',
    'Wiki\CountriesController@index'
);
Route::name('v4_countries.one')
->middleware('AccessControl')
->get(
    'countries/{country_id}',
    'Wiki\CountriesController@show'
);
Route::name('v4_countries.search')
->middleware('AccessControl')
->get(
    'countries/search/{search_text}',
    'Wiki\CountriesController@search'
);

// VERSION 4 | Languages
Route::name('v4_languages.all')
->middleware('AccessControl')
->get(
    'languages',
    'Wiki\LanguagesController@index'
);
Route::name('v4_languages.one')
->middleware('AccessControl')
->get(
    'languages/{language_id}',
    'Wiki\LanguagesController@show'
);
Route::name('v4_languages.search')
->middleware('AccessControl')
->get(
    'languages/search/{search_text}',
    'Wiki\LanguagesController@search'
);

// VERSION 4 | Alphabets and Numbers
Route::name('v4_alphabets.all')->get(
    'alphabets',
    'Wiki\AlphabetsController@index'
);
Route::name('v4_alphabets.one')->get(
    'alphabets/{alphabet_id}',
    'Wiki\AlphabetsController@show'
);
Route::name('v4_numbers.all')->get('numbers/', 'Wiki\NumbersController@index');
Route::name('v4_numbers.range')->get(
    'numbers/range',
    'Wiki\NumbersController@customRange'
);
Route::name('v4_numbers.one')->get(
    'numbers/{number_id}',
    'Wiki\NumbersController@show'
);

// VERSION 4 | Search
// Even though TextController fields the search, it returns content from text, audio and video. Rename to SearchController?
Route::name('v4_text_search')->get('search', 'Bible\TextController@search');

// VERSION 4 | Bibles
Route::name('v4_bible.defaults')->get(
    'bibles/defaults/types',
    'Bible\BiblesController@defaults'
); // used
Route::name('v4_bible.books')
->middleware('AccessControl')
->get(
    'bibles/{bible_id}/book',
    'Bible\BiblesController@books'
); // used by bible.is, but book is not specified. suggest unifying on this one. (fixed)The signature looks wrong - the code doesn't accept book_id as a path param, only a query param
Route::name('v4_bible_by_id.search')
->middleware('AccessControl')
->get(
    'bibles/search',
    'Bible\BiblesController@searchByBibleVersion'
);
Route::name('v4_bible.one')
->middleware('AccessControl')
->get(
    'bibles/{bible_id}',
    'Bible\BiblesController@show'
); // see note in Postman. the content is suspect
Route::name('v4_bible.search')
->middleware('AccessControl')
->get(
    'bibles/search/{search_text}',
    'Bible\BiblesController@search'
);
Route::name('v4_bible.all')
    ->middleware('AccessControl')
    ->get('bibles', 'Bible\BiblesController@index'); // used
Route::name('v4_bible.copyright')->get(
    'bibles/{bible_id}/copyright',
    'Bible\BiblesController@copyright'
); // used
Route::name('v4_internal_bible.chapter')
    ->middleware(['APIToken', 'AccessControl'])
    ->get('bibles/{bible_id}/chapter', 'Bible\BiblesController@chapter'); //used
Route::name('v4_internal_bible.chapter.annotations')
    ->middleware('APIToken:check')
    ->get(
        'bibles/{bible_id}/chapter/annotations',
        'Bible\BiblesController@annotations'
    );



// VERSION 4 | Filesets
Route::name('v4_filesets.types')->get(
    'bibles/filesets/media/types',
    'Bible\BibleFileSetsController@mediaTypes'
);
Route::name('v4_internal_filesets.checkTypes')->post(
    'bibles/filesets/check/types',
    'Bible\BibleFileSetsController@checkTypes'
);
// DEPRECATED. not used by bible.is after 3.0.x. But needs to remain in the API for backward compatibility until 3.0.x and older are gone
Route::name('v4_internal_bible_filesets.copyright')->get('bibles/filesets/{fileset_id}/copyright', 'Bible\BibleFileSetsController@copyright');

// DEPRECATED. Prefer instead v4_filesets.chapter. Reasons: It takes book and chapter as query parameters.
Route::name('v4_internal_filesets.show')
->middleware('AccessControl')
->get(
    'bibles/filesets/{fileset_id?}',
    'Bible\BibleFileSetsController@show'
);

//  DEPRECATED. not used by bible.is after 3.0.x. But needs to remain in the API for backward compatibility until 3.0.x and older are gone
Route::name('v4_filesets.books')->get(
    'bibles/filesets/{fileset_id}/books',
    'Bible\BooksController@show'
);

// the order of these next two routes matters, the most general (bibles/filesets/bulk) must go before /bibles/filesets/{fileset_id}
Route::name('v4_filesets.bulk')
->middleware('AccessControl')
->get(
    'bibles/filesets/bulk/{fileset_id}/{book?}',
    'Bible\BibleFileSetsController@showBulk'
);
Route::name('v4_filesets.chapter')
->middleware('AccessControl')
->get(
    'bibles/filesets/{fileset_id}/{book}/{chapter}',
    'Bible\BibleFileSetsController@showChapter'
);

Route::name('v4_bible_verses.verse_by_language')->get(
    '/bibles/verses/{language_code}/{book_id}/{chapter_id}/{verse_number?}',
    'Bible\BibleVersesController@showVerseByLanguage'
)->middleware('AccessControl')
->whereAlphaNumeric('language_code')
->whereAlphaNumeric('book_id')
->whereNumber('chapter_id')
->whereAlphaNumeric('verse_number');

Route::name('v4_bible_verses.verse_by_bible')->get(
    '/bible/{bible_id}/verses/{book_id}/{chapter_id}/{verse_number?}',
    'Bible\BibleVersesController@showVerseByBible'
)->middleware('AccessControl')
->whereAlphaNumeric('bible_id')
->whereAlphaNumeric('book_id')
->whereNumber('chapter_id')
->whereAlphaNumeric('verse_number');

// BibleFileSet download version 4

Route::name('v4_bible_filesets_download.list')
->middleware('AccessControl')
->get(
    'download/list',
    'Bible\BibleFilesetsDownloadController@list'
);

Route::name('v4_bible_filesets_download.index')
    ->middleware(['APIToken', 'AccessControl'])
    ->get(
        'download/{fileset_id}/{book?}/{chapter_id?}',
        'Bible\BibleFilesetsDownloadController@index'
    );

// VERSION 4 | Text
// This is new, added Dec 28, to provide just the verses for a bible or chapter. Note that this does not have filesets in path
Route::name('v4_bible.verseinfo')
->middleware('AccessControl')
->get(
    'bibles/{bible_id}/{book}/{chapter?}',
    'Bible\TextController@index'
);

// VERSION 4 | Timestamps
Route::name('v4_timestamps')->get(
    'timestamps',
    'Bible\AudioController@availableTimestamps'
);
Route::name('v4_timestamps.tag')->get(
    'timestamps/search',
    'Bible\AudioController@timestampsByTag'
);
Route::name('v4_timestamps.verse')->get(
    'timestamps/{id}/{book}/{chapter}',
    'Bible\AudioController@timestampsByReference'
);

// VERSION 4 | Stream
Route::name('v4_media_stream')->get(
    'bible/filesets/{fileset_id}/{file_id}/playlist.m3u8',
    'Bible\StreamController@index'
);
Route::name('v4_media_stream_ts')->get(
    'bible/filesets/{fileset_id}/{file_id}/{file_name}',
    'Bible\StreamController@transportStream'
);
## this is no good. StreamController::index does not process book_id/chapter/verse_start/verse_end
Route::name('v4_media_stream')->get(
    'bible/filesets/{fileset_id}/{book_id}-{chapter}-{verse_start?}-{verse_end?}/playlist.m3u8',
    'Bible\StreamController@index'
);
Route::name('v4_media_stream_ts')->get(
    'bible/filesets/{fileset_id}/{book_id}-{chapter}-{verse_start}-{verse_end}/{file_name}',
    'Bible\StreamController@transportStream'
);

// VERSION 4 - Jesus Film
Route::name('v4_video_jesus_film_languages')->get(
    'arclight/jesus-film/languages',
    'Bible\VideoStreamController@jesusFilmsLanguages'
); // used by bible.is
Route::name('v4_video_jesus_film_chapters')->get(
    'arclight/jesus-film/chapters',
    'Bible\VideoStreamController@jesusFilmChapters'
);// used by bible.is
Route::name('v4_video_jesus_film_file')->get(
    'arclight/jesus-film',
    'Bible\VideoStreamController@jesusFilmFile'
);// used by bible.is
Route::name('v4_video_jesus_film_chapter')->get(
    'jesus-film/{language_iso}/{book}/{chapter}',
    'Bible\VideoStreamController@jesusFilmGetChapter'
);// used by bible.is


Route::name('v4_internal_api.refreshDevCache')->get(
    '/refresh-dev-cache',
    'ApiMetadataController@refreshDevCache'
);


// ................. bible.is private .....................
// this search includes plans/playlist/notes and requires API token
Route::name('v4_internal_library_search')
    ->middleware('APIToken:check')
    ->get('search/library', 'Bible\TextController@searchLibrary');

// VERSION 4 | Users (bible.is private)
Route::name('v4_internal_user.index')->get(
    'users',
    'User\UsersController@index'
);
Route::name('v4_internal_user.store')->post(
    'users',
    'User\UsersController@store'
);
Route::name('v4_internal_user.show')->get(
    'users/{user_id}',
    'User\UsersController@show'
);
Route::name('v4_internal_user.update')->put(
    'users/{user_id}',
    'User\UsersController@update'
);
Route::name('v4_internal_user.destroy')
    ->middleware('APIToken:check')
    ->delete('users', 'User\UsersController@destroy');
Route::name('v4_internal_user.login')->post(
    '/login',
    'User\UsersController@login'
);
Route::name('v4_internal_user.oAuth')->get(
    '/login/{driver}',
    'User\SocialController@redirect'
);
Route::name('v4_internal_user.oAuthCallback')->get(
    '/login/{driver}/callback',
    'User\SocialController@callback'
);
Route::name('v4_internal_user.password_reset')
    ->middleware('APIToken')
    ->post(
        'users/password/reset/{token?}',
        'User\PasswordsController@validatePasswordReset'
    );
Route::name('v4_internal_user.password_email')->post(
    'users/password/email',
    'User\PasswordsController@triggerPasswordResetEmail'
);
Route::name('v4_internal_user.logout')
    ->middleware('APIToken:check')
    ->post('/logout', 'User\UsersController@logout');
Route::name('v4_internal_api_token.validate')
    ->middleware('APIToken')
    ->post('/token/validate', 'User\UsersController@validateApiToken');

// VERSION 4 | Playlists (bible.is private)
Route::name('v4_internal_playlists.index')
    ->middleware('APIToken')
    ->get('playlists', 'Playlist\PlaylistsController@index');
Route::name('v4_internal_playlists.store')
    ->middleware('APIToken:check')
    ->post('playlists', 'Playlist\PlaylistsController@store');
Route::name('v4_internal_playlists.show')
    ->middleware('APIToken')
    ->get('playlists/{playlist_id}', 'Playlist\PlaylistsController@show');
Route::name('v4_internal_playlists.show_text')
    ->middleware('APIToken')
    ->get(
        'playlists/{playlist_id}/text',
        'Playlist\PlaylistsController@showText'
    );
Route::name('v4_internal_playlists.update')
    ->middleware('APIToken:check')
    ->put('playlists/{playlist_id}', 'Playlist\PlaylistsController@update');
Route::name('v4_internal_playlists.destroy')
    ->middleware('APIToken:check')
    ->delete('playlists/{playlist_id}', 'Playlist\PlaylistsController@destroy');
Route::name('v4_internal_playlists.follow')
    ->middleware('APIToken:check')
    ->post(
        'playlists/{playlist_id}/follow',
        'Playlist\PlaylistsController@follow'
    );
Route::name('v4_internal_playlists_items.store')
    ->middleware('APIToken:check')
    ->post(
        'playlists/{playlist_id}/item',
        'Playlist\PlaylistsController@storeItem'
    );
Route::name('v4_internal_playlists_items.complete')
    ->middleware('APIToken:check')
    ->post(
        'playlists/item/{item_id}/complete',
        'Playlist\PlaylistsController@completeItem'
    );
Route::name('v4_internal_playlists.translate')
    ->middleware(['APIToken:check', 'AccessControl'])
    ->get('playlists/{playlist_id}/translate', 'Playlist\PlaylistsController@translate');
Route::name('v4_internal_playlists.hls')
    ->get('playlists/{playlist_id}/hls', 'Playlist\PlaylistsController@hls');
Route::name('v4_internal_playlists_item.hls')
    ->get(
        'playlists/{fileset_id}-{book_id}-{chapter}-{verse_start}-{verse_end}/item-hls',
        'Playlist\PlaylistsController@itemHls'
    );
Route::name('v4_internal_playlists_item.hls')
    ->get('playlists/{playlist_item_id}/item-hls', 'Playlist\PlaylistsController@itemHls');
Route::name('v4_internal_playlists.draft')
    ->middleware('APIToken:check')
    ->post('playlists/{playlist_id}/draft', 'Playlist\PlaylistsController@draft');
Route::name('v4_internal_playlists_item.metadata')
    ->get('playlists/item/metadata', 'Playlist\PlaylistsController@itemMetadata');
Route::name('v4_internal_playlists.notes')
    ->middleware('APIToken:check')
    ->get('playlists/{playlist_id}/{book_id}/notes', 'Playlist\PlaylistsController@notes')
    ->whereNumber('playlist_id')
    ->whereAlphaNumeric('book_id');
Route::name('v4_internal_playlists.highlights')
    ->middleware('APIToken:check')
    ->get('playlists/{playlist_id}/{book_id}/highlights', 'Playlist\PlaylistsController@highlights')
    ->whereNumber('playlist_id')
    ->whereAlphaNumeric('book_id');
Route::name('v4_internal_playlists.bookmarks')
    ->middleware('APIToken:check')
    ->get('playlists/{playlist_id}/{book_id}/bookmarks', 'Playlist\PlaylistsController@bookmarks')
    ->whereNumber('playlist_id')
    ->whereAlphaNumeric('book_id');
// VERSION 4 | Plans (bible.is private)
Route::name('v4_internal_plans.index')
    ->middleware('APIToken')
    ->get('plans', 'Plan\PlansController@index');
Route::name('v4_internal_plans.store')
    ->middleware('APIToken:check')
    ->post('plans', 'Plan\PlansController@store');
Route::name('v4_internal_plans.show')
    ->middleware('APIToken')
    ->get('plans/{plan_id}', 'Plan\PlansController@show');
Route::name('v4_internal_plans.update')
    ->middleware('APIToken:check')
    ->put('plans/{plan_id}', 'Plan\PlansController@update');
Route::name('v4_internal_plans.destroy')
    ->middleware('APIToken:check')
    ->delete('plans/{plan_id}', 'Plan\PlansController@destroy');
Route::name('v4_internal_plans.start')
    ->middleware('APIToken:check')
    ->post('plans/{plan_id}/start', 'Plan\PlansController@start');
Route::name('v4_internal_plans.reset')
    ->middleware('APIToken:check')
    ->post('plans/{plan_id}/reset', 'Plan\PlansController@reset');
Route::name('v4_internal_plans.stop')
    ->middleware('APIToken:check')
    ->delete('plans/{plan_id}/stop', 'Plan\PlansController@stop');
Route::name('v4_internal_plans.translate')
    ->middleware(['APIToken:check', 'AccessControl'])
    ->get('plans/{plan_id}/translate', 'Plan\PlansController@translate');
Route::name('v4_internal_plans_days.store')
    ->middleware('APIToken:check')
    ->post('plans/{plan_id}/day', 'Plan\PlansController@storeDay');
Route::name('v4_internal_plans_days.complete')
    ->middleware('APIToken:check')
    ->post('plans/day/{day_id}/complete', 'Plan\PlansController@completeDay');
Route::name('v4_internal_plans.draft')
    ->middleware('APIToken:check')
    ->post('plans/{plan_id}/draft', 'Plan\PlansController@draft');
Route::name('v4_internal_plans_days.delete')
    ->middleware('APIToken:check')
    ->delete('plans/{plan_id}/day', 'Plan\PlansController@deleteDays');

// VERSION 4 | Accounts (bible.is private)
Route::name('v4_internal_user_accounts.index')->get(
    'accounts',
    'User\AccountsController@index'
);
Route::name('v4_internal_user_accounts.store')->post(
    'accounts',
    'User\AccountsController@store'
);
Route::name('v4_internal_user_accounts.update')->put(
    'accounts',
    'User\AccountsController@update'
);
Route::name('v4_internal_user_accounts.destroy')->delete(
    'accounts',
    'User\AccountsController@destroy'
);

// VERSION 4 | Annotations with api_token (bible.is private)
Route::middleware('APIToken')->group(function () {
    Route::name('v4_internal_notes.index')->get(
        'users/{user_id}/notes',
        'User\NotesController@index'
    );
    Route::name('v4_internal_notes.show')->get(
        'users/{user_id}/notes/{id}',
        'User\NotesController@show'
    );
    Route::name('v4_internal_notes.store')->post(
        'users/{user_id}/notes',
        'User\NotesController@store'
    );
    Route::name('v4_internal_notes.update')->put(
        'users/{user_id}/notes/{id}',
        'User\NotesController@update'
    );
    Route::name('v4_internal_notes.destroy')->delete(
        'users/{user_id}/notes/{id}',
        'User\NotesController@destroy'
    );
    Route::name('v4_internal_bookmarks.index')->get(
        'users/{user_id}/bookmarks',
        'User\BookmarksController@index'
    );
    Route::name('v4_internal_bookmarks.store')->post(
        'users/{user_id}/bookmarks',
        'User\BookmarksController@store'
    );
    Route::name('v4_internal_bookmarks.update')->put(
        'users/{user_id}/bookmarks/{id}',
        'User\BookmarksController@update'
    );
    Route::name('v4_internal_bookmarks.destroy')->delete(
        'users/{user_id}/bookmarks/{id}',
        'User\BookmarksController@destroy'
    );
    Route::name('v4_internal_highlights.index')->get(
        'users/{user_id}/highlights',
        'User\HighlightsController@index'
    );
    Route::name('v4_internal_highlights.store')->post(
        'users/{user_id}/highlights',
        'User\HighlightsController@store'
    );
    Route::name('v4_internal_highlights.update')->put(
        'users/{user_id}/highlights/{id}',
        'User\HighlightsController@update'
    );
    Route::name('v4_internal_highlights.destroy')->delete(
        'users/{user_id}/highlights/{id}',
        'User\HighlightsController@destroy'
    );
    // User download annotations version 4
    Route::name('v4_users_download_annotations.index')->get(
        'users/{user_id}/annotations/{bible_id}/{book?}/{chapter?}',
        'User\UsersDownloadAnnotations@index'
    )->whereNumber('user_id');
});

Route::middleware('APIToken:check')->group(function () {
    Route::name('v4_internal_highlights.colors')->get(
        'users/highlights/colors',
        'User\HighlightsController@colors'
    );
});

// ................. attic .......................
// VERSION 4 | Study Lexicons (attic)
Route::name('v4_internal_lexicon_index')->get(
    'lexicons',
    'Bible\Study\LexiconController@index'
);

// Joshua Project -- is this current? this endpoint is not used by bible.is
Route::name('v4_countries.jsp')->get(
    'countries/joshua-project/',
    'Wiki\CountriesController@joshuaProjectIndex'
);

// VERSION 4 | Push tokens (attic)
Route::name('v4_internal_push_tokens.index')
    ->middleware('APIToken:check')
    ->get('push_notifications', 'User\PushTokensController@index');
Route::name('v4_internal_push_tokens.store')
    ->middleware('APIToken:check')
    ->post('push_notifications', 'User\PushTokensController@store');
Route::name('v4_internal_push_tokens.destroy')
    ->middleware('APIToken:check')
    ->delete('push_notifications/{token}', 'User\PushTokensController@destroy');



Route::name('v4_bible.links')->get(
    'bibles/links',
    'Bible\BibleLinksController@index'
);
// Route::name('v4_bible_books_all')->get(
//     'bibles/books/',
//     'Bible\BooksController@index'
// ); // not used by bible.is, essentially a duplicate of /bibles/id/book

# Podcast is not currently supported in DBP4, but will be in the near future.
#Route::name('v4_internal_filesets.podcast')->get('bibles/filesets/{fileset_id}/podcast',    'Bible\BibleFilesetsPodcastController@index');

// VERSION 4 | API status
Route::name('status')->get('/status', 'ApiMetadataController@getStatus');
