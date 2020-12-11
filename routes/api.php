<?php

// VERSION 4 | Access Groups
Route::name('v4_internal_access_groups.index')->get('access/groups',                        'User\AccessGroupController@index');
Route::name('v4_internal_access_groups.store')->post('access/groups/',                      'User\AccessGroupController@store');
Route::name('v4_internal_access_groups.show')->get('access/groups/{group_id}',              'User\AccessGroupController@show');
Route::name('v4_internal_access_groups.access')->get('access/current',                      'User\AccessGroupController@current');
Route::name('v4_internal_access_groups.update')->put('access/groups/{group_id}',            'User\AccessGroupController@update');
Route::name('v4_internal_access_groups.destroy')->delete('access/groups/{group_id}',        'User\AccessGroupController@destroy');

// VERSION 4 | Stream
Route::name('v4_media_stream')->get('bible/filesets/{fileset_id}/{file_id}/playlist.m3u8',    'Bible\StreamController@index');
Route::name('v4_media_stream_ts')->get('bible/filesets/{fileset_id}/{file_id}/{file_name}',   'Bible\StreamController@transportStream');
Route::name('v4_media_stream')->get('bible/filesets/{fileset_id}/{book_id}-{chapter}-{verse_start}-{verse_end}/playlist.m3u8',    'Bible\StreamController@index');
Route::name('v4_media_stream_ts')->get('bible/filesets/{fileset_id}/{book_id}-{chapter}-{verse_start}-{verse_end}/{file_name}',   'Bible\StreamController@transportStream');

// VERSION 4 | Bible
Route::name('v4_bible.books')->get('bibles/{bible_id}/book/{book?}',               'Bible\BiblesController@books');
Route::name('v4_bible_equivalents.all')->get('bible/equivalents',                  'Bible\BibleEquivalentsController@index');
Route::name('v4_bible.links')->get('bibles/links',                                 'Bible\BibleLinksController@index');
Route::name('v4_bible_books_all')->get('bibles/books/',                            'Bible\BooksController@index');
Route::name('v4_bible.one')->get('bibles/{bible_id}',                              'Bible\BiblesController@show');
Route::name('v4_bible.all')->get('bibles',                                         'Bible\BiblesController@index');
Route::name('v4_bible.defaults')->get('bibles/defaults/types',                     'Bible\BiblesController@defaults');
Route::name('v4_bible.copyright')->get('bibles/{bible_id}/copyright',              'Bible\BiblesController@copyright');
Route::name('v4_internal_bible.chapter')
    ->middleware('APIToken')->get('bibles/{bible_id}/chapter',                     'Bible\BiblesController@chapter');
Route::name('v4_internal_bible.chapter.annotations')
    ->middleware('APIToken:check')->get('bibles/{bible_id}/chapter/annotations',          'Bible\BiblesController@annotations');

// VERSION 4 | Filesets
Route::name('v4_filesets.types')->get('bibles/filesets/media/types',               'Bible\BibleFileSetsController@mediaTypes');
Route::name('v4_internal_filesets.checkTypes')->post('bibles/filesets/check/types', 'Bible\BibleFileSetsController@checkTypes');
Route::name('v4_internal_filesets.podcast')->get('bibles/filesets/{fileset_id}/podcast',    'Bible\BibleFilesetsPodcastController@index');
Route::name('v4_filesets.show')->get('bibles/filesets/{fileset_id?}',              'Bible\BibleFileSetsController@show');
Route::name('v4_filesets.books')->get('bibles/filesets/{fileset_id}/books',        'Bible\BooksController@show');

// VERSION 4 | Text
Route::name('v4_filesets.chapter')->get('bibles/filesets/{fileset_id}/{book}/{chapter}', 'Bible\TextController@index');
Route::name('v4_text_search')->get('search',                                             'Bible\TextController@search');
Route::name('v4_internal_library_search')->middleware('APIToken:check')->get('search/library',    'Bible\TextController@searchLibrary');

// VERSION 4 | Commentaries

Route::name('v4_internal_commentary_index')->get('commentaries/',                                       'Bible\Study\CommentaryController@index');
Route::name('v4_internal_commentary_chapters')->get('commentaries/{commentary_id}/chapters',            'Bible\Study\CommentaryController@chapters');
Route::name('v4_internal_commentary_chapters')->get('commentaries/{commentary_id}/{book_id}/{chapter}', 'Bible\Study\CommentaryController@sections');

// VERSION 4 | Study Lexicons
Route::name('v4_internal_lexicon_index')->get('lexicons',                                   'Bible\Study\LexiconController@index');

// VERSION 4 | Timestamps
Route::name('v4_internal_timestamps')->get('timestamps',                                    'Bible\AudioController@availableTimestamps');
Route::name('v4_internal_timestamps.tag')->get('timestamps/search',                         'Bible\AudioController@timestampsByTag');
Route::name('v4_internal_timestamps.verse')->get('timestamps/{id}/{book}/{chapter}',        'Bible\AudioController@timestampsByReference');

// VERSION 4 | Countries
Route::name('v4_countries.all')->get('countries',                                  'Wiki\CountriesController@index');
Route::name('v4_countries.jsp')->get('countries/joshua-project/',                  'Wiki\CountriesController@joshuaProjectIndex');
Route::name('v4_countries.one')->get('countries/{country_id}',                     'Wiki\CountriesController@show');

// VERSION 4 | Languages
Route::name('v4_languages.all')->get('languages',                                  'Wiki\LanguagesController@index');
Route::name('v4_languages.one')->get('languages/{language_id}',                    'Wiki\LanguagesController@show');

// VERSION 4 | Alphabets
Route::name('v4_alphabets.all')->get('alphabets',                                  'Wiki\AlphabetsController@index');
Route::name('v4_alphabets.one')->get('alphabets/{alphabet_id}',                    'Wiki\AlphabetsController@show');
Route::name('v4_numbers.all')->get('numbers/',                                     'Wiki\NumbersController@index');
Route::name('v4_numbers.range')->get('numbers/range',                              'Wiki\NumbersController@customRange');
Route::name('v4_numbers.one')->get('numbers/{number_id}',                          'Wiki\NumbersController@show');

// VERSION 4 | Users
Route::name('v4_internal_user.index')->get('users',                                'User\UsersController@index');
Route::name('v4_internal_user.store')->post('users',                               'User\UsersController@store');
Route::name('v4_internal_user.show')->get('users/{user_id}',                       'User\UsersController@show');
Route::name('v4_internal_user.update')->put('users/{user_id}',                     'User\UsersController@update');
Route::name('v4_internal_user.destroy')->middleware('APIToken:check')->delete('users', 'User\UsersController@destroy');
Route::name('v4_internal_user.login')->post('/login',                              'User\UsersController@login');
Route::name('v4_internal_user.oAuth')->get('/login/{driver}',                      'User\SocialController@redirect');
Route::name('v4_internal_user.oAuthCallback')->get('/login/{driver}/callback',     'User\SocialController@callback');
Route::name('v4_internal_user.password_reset')
    ->middleware('APIToken')->post('users/password/reset/{token?}',                'User\PasswordsController@validatePasswordReset');
Route::name('v4_internal_user.password_email')->post('users/password/email',       'User\PasswordsController@triggerPasswordResetEmail');
Route::name('v4_internal_user.logout')
    ->middleware('APIToken:check')->post('/logout',                                'User\UsersController@logout');
Route::name('v4_internal_api_token.validate')
    ->middleware('APIToken')->post('/token/validate',                               'User\UsersController@validateApiToken');

// VERSION 4 | Accounts
Route::name('v4_internal_user_accounts.index')->get('accounts',                     'User\AccountsController@index');
Route::name('v4_internal_user_accounts.store')->post('accounts',                    'User\AccountsController@store');
Route::name('v4_internal_user_accounts.update')->put('accounts',                    'User\AccountsController@update');
Route::name('v4_internal_user_accounts.destroy')->delete('accounts',                'User\AccountsController@destroy');

// VERSION 4 | Annotations with api_token
Route::middleware('APIToken')->group(function () {
    Route::name('v4_internal_notes.index')->get('users/{user_id}/notes',            'User\NotesController@index');
    Route::name('v4_internal_notes.show')->get('users/{user_id}/notes/{id}',        'User\NotesController@show');
    Route::name('v4_internal_notes.store')->post('users/{user_id}/notes',           'User\NotesController@store');
    Route::name('v4_internal_notes.update')->put('users/{user_id}/notes/{id}',      'User\NotesController@update');
    Route::name('v4_internal_notes.destroy')->delete('users/{user_id}/notes/{id}',  'User\NotesController@destroy');
    Route::name('v4_internal_bookmarks.index')->get('users/{user_id}/bookmarks',    'User\BookmarksController@index');
    Route::name('v4_internal_bookmarks.store')->post('users/{user_id}/bookmarks',   'User\BookmarksController@store');
    Route::name('v4_internal_bookmarks.update')->put('users/{user_id}/bookmarks/{id}', 'User\BookmarksController@update');
    Route::name('v4_internal_bookmarks.destroy')->delete('users/{user_id}/bookmarks/{id}', 'User\BookmarksController@destroy');
    Route::name('v4_internal_highlights.index')->get('users/{user_id}/highlights',   'User\HighlightsController@index');
    Route::name('v4_internal_highlights.store')->post('users/{user_id}/highlights',           'User\HighlightsController@store');
    Route::name('v4_internal_highlights.update')->put('users/{user_id}/highlights/{id}',      'User\HighlightsController@update');
    Route::name('v4_internal_highlights.destroy')->delete('users/{user_id}/highlights/{id}',  'User\HighlightsController@destroy');
});

Route::middleware('APIToken:check')->group(function () {
    Route::name('v4_internal_highlights.colors')->get('users/highlights/colors',   'User\HighlightsController@colors');
});

// VERSION 4 | User Settings
Route::name('v4_internal_UserSettings.show')->get('users/{user_id}/settings',      'User\UserSettingsController@show');
Route::name('v4_internal_UserSettings.store')->post('users/{user_id}/settings',    'User\UserSettingsController@store');

// VERSION 4 | Community
Route::name('v4_articles.index')->get('articles',                                  'User\ArticlesController@index');
Route::name('v4_articles.show')->get('articles/{id}',                              'User\ArticlesController@show');
Route::name('v4_articles.update')->put('articles/{id}',                            'User\ArticlesController@update');
Route::name('v4_articles.store')->post('articles',                                 'User\ArticlesController@store');
Route::name('v4_articles.destroy')->delete('articles/{id}',                        'User\ArticlesController@destroy');
Route::name('v4_organizations.compare')->get('organizations/compare/',             'Organization\OrganizationsController@compare');
Route::name('v4_organizations.one')->get('organizations/{organization_id}',        'Organization\OrganizationsController@show');
Route::name('v4_organizations.all')->get('organizations/',                         'Organization\OrganizationsController@index');
Route::name('v4_internal_projects.index')->get('projects',                         'Organization\ProjectsController@index');
Route::name('v4_internal_projects.show')->get('projects/{project_id}',             'Organization\ProjectsController@show');
Route::name('v4_internal_projects.update')->put('projects/{project_id}',           'Organization\ProjectsController@update');
Route::name('v4_internal_projects.store')->post('projects',                        'Organization\ProjectsController@store');
Route::name('v4_internal_projects.destroy')->delete('projects/{project_id}',       'Organization\ProjectsController@destroy');
Route::name('v4_oAuth.index')->get('projects/{project_id}/oauth/',                 'Organization\OAuthProvidersController@index');
Route::name('v4_oAuth.update')->put('projects/{project_id}/oauth/{id}',            'Organization\OAuthProvidersController@update');
Route::name('v4_oAuth.store')->post('projects/{project_id}/oauth',                 'Organization\OAuthProvidersController@store');
Route::name('v4_oAuth.destroy')->delete('projects/{project_id}/oauth/{id}',        'Organization\OAuthProvidersController@destroy');

// VERSION 4 | Resources
Route::name('v4_resources.index')->get('resources',                                'Organization\ResourcesController@index');
Route::name('v4_resources.show')->get('resources/{resource_id}',                   'Organization\ResourcesController@show');

Route::name('v4_video_jesus_film_languages')->get('arclight/jesus-film/languages', 'Bible\VideoStreamController@jesusFilmsLanguages');
Route::name('v4_video_jesus_film_chapters')->get('arclight/jesus-film/chapters',   'Bible\VideoStreamController@jesusFilmChapters');
Route::name('v4_video_jesus_film_file')->get('arclight/jesus-film',                'Bible\VideoStreamController@jesusFilmFile');

// VERSION 4 | API METADATA
Route::name('v4_internal_api.versions')->get('/api/versions',                       'HomeController@versions');
Route::name('v4_internal_api.buckets')->get('/api/buckets',                         'HomeController@buckets');
Route::name('v4_internal_api.stats')->get('/stats',                                 'HomeController@stats');
Route::name('v4_internal_api.gitVersion')->get('/api/git/version',                  'ApiMetadataController@gitVersion');
Route::name('v4_internal_api.refreshDevCache')->get('/refresh-dev-cache',           'ApiMetadataController@refreshDevCache');
Route::name('v4_internal_api.changes')->get('/api/changelog',                       'ApiMetadataController@changelog');

// VERSION 4 | GENERATOR
Route::name('v4_internal_api.generator')->get('/api/gen/bibles',                    'Connections\GeneratorController@bibles');

// VERSION 4 | Playlists
Route::name('v4_internal_playlists.index')
    ->middleware('APIToken')->get('playlists',                                      'Playlist\PlaylistsController@index');
Route::name('v4_internal_playlists.store')
    ->middleware('APIToken:check')->post('playlists',                               'Playlist\PlaylistsController@store');
Route::name('v4_internal_playlists.show')
    ->middleware('APIToken')->get('playlists/{playlist_id}',                        'Playlist\PlaylistsController@show');
Route::name('v4_internal_playlists.show_text')
    ->middleware('APIToken')->get('playlists/{playlist_id}/text',                   'Playlist\PlaylistsController@showText');
Route::name('v4_internal_playlists.update')
    ->middleware('APIToken:check')->put('playlists/{playlist_id}',                  'Playlist\PlaylistsController@update');
Route::name('v4_internal_playlists.destroy')
    ->middleware('APIToken:check')->delete('playlists/{playlist_id}',               'Playlist\PlaylistsController@destroy');
Route::name('v4_internal_playlists.follow')
    ->middleware('APIToken:check')->post('playlists/{playlist_id}/follow',          'Playlist\PlaylistsController@follow');
Route::name('v4_internal_playlists_items.store')
    ->middleware('APIToken:check')->post('playlists/{playlist_id}/item',            'Playlist\PlaylistsController@storeItem');
Route::name('v4_internal_playlists_items.complete')
    ->middleware('APIToken:check')->post('playlists/item/{item_id}/complete',       'Playlist\PlaylistsController@completeItem');
Route::name('v4_internal_playlists.translate')
    ->middleware('APIToken:check')->get('playlists/{playlist_id}/translate',        'Playlist\PlaylistsController@translate');
Route::name('v4_internal_playlists.hls')->get('playlists/{playlist_id}/hls',                 'Playlist\PlaylistsController@hls');
Route::name('v4_internal_playlists_item.hls')->get('playlists/{playlist_item_id}/item-hls',  'Playlist\PlaylistsController@itemHls');
Route::name('v4_internal_playlists.draft')
    ->middleware('APIToken:check')->post('playlists/{playlist_id}/draft',           'Playlist\PlaylistsController@draft');


// VERSION 4 | Plans
Route::name('v4_internal_plans.index')
    ->middleware('APIToken')->get('plans',                                          'Plan\PlansController@index');
Route::name('v4_internal_plans.store')
    ->middleware('APIToken:check')->post('plans',                                   'Plan\PlansController@store');
Route::name('v4_internal_plans.show')
    ->middleware('APIToken')->get('plans/{plan_id}',                                'Plan\PlansController@show');
Route::name('v4_internal_plans.update')
    ->middleware('APIToken:check')->put('plans/{plan_id}',                          'Plan\PlansController@update');
Route::name('v4_internal_plans.destroy')
    ->middleware('APIToken:check')->delete('plans/{plan_id}',                       'Plan\PlansController@destroy');
Route::name('v4_internal_plans.start')
    ->middleware('APIToken:check')->post('plans/{plan_id}/start',                   'Plan\PlansController@start');
Route::name('v4_internal_plans.reset')
    ->middleware('APIToken:check')->post('plans/{plan_id}/reset',                   'Plan\PlansController@reset');
Route::name('v4_internal_plans.stop')
    ->middleware('APIToken:check')->delete('plans/{plan_id}/stop',                  'Plan\PlansController@stop');
Route::name('v4_internal_plans.translate')
    ->middleware('APIToken:check')->get('plans/{plan_id}/translate',                'Plan\PlansController@translate');
Route::name('v4_internal_plans_days.store')
    ->middleware('APIToken:check')->post('plans/{plan_id}/day',                     'Plan\PlansController@storeDay');
Route::name('v4_internal_plans_days.complete')
    ->middleware('APIToken:check')->post('plans/day/{day_id}/complete',             'Plan\PlansController@completeDay');
Route::name('v4_internal_plans.draft')
    ->middleware('APIToken:check')->post('plans/{plan_id}/draft',                   'Plan\PlansController@draft');

// VERSION 4 | Push tokens

Route::name('v4_internal_push_tokens.index')
    ->middleware('APIToken:check')->get('push_notifications',                       'User\PushTokensController@index');
Route::name('v4_internal_push_tokens.store')
    ->middleware('APIToken:check')->post('push_notifications',                      'User\PushTokensController@store');
Route::name('v4_internal_push_tokens.destroy')
    ->middleware('APIToken:check')->delete('push_notifications/{token}',            'User\PushTokensController@destroy');
