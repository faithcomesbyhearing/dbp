<?php

// VERSION 2 | Metadata
Route::name('v2_pass_through')->get('pass-through/{path1?}/{path2?}',              'ApiMetadataController@passThrough');
Route::name('v2_library_asset')->get('library/asset',                              'ApiMetadataController@assets');
Route::name('v2_api_versionLatest')->get('api/apiversion',                         'ApiMetadataController@versionLatest');
Route::name('v2_api_apiReply')->get('api/reply',                                   'ApiMetadataController@replyTypes');

// VERSION 2 | Books
Route::name('v2_library_book')->get('library/book',                                'Bible\BooksControllerV2@book');
Route::name('v2_library_bookOrder')->get('library/bookorder',                      'Bible\BooksControllerV2@bookOrder');
Route::name('v2_library_bookName')->get('library/bookname',                        'Bible\BooksControllerV2@bookNames');
Route::name('v2_library_chapter')->get('library/chapter',                          'Bible\BooksControllerV2@chapters');

// VERSION 2 | Languages
Route::name('v2_library_language')->get('library/language',                        'Wiki\LanguageControllerV2@languageListing');
Route::name('v2_library_volumeLanguage')->get('library/volumelanguage',            'Wiki\LanguageControllerV2@volumeLanguage');
Route::name('v2_library_volumeLanguageFamily')->middleware('AccessControl')->get('library/volumelanguagefamily', 'Wiki\LanguageControllerV2@volumeLanguageFamily');
Route::name('v2_country_lang')->middleware('AccessControl')->get('country/countrylang',                         'Wiki\LanguageControllerV2@countryLang');

// VERSION 2 | Library
Route::name('v2_library_version')->get('library/version',                          'Bible\LibraryController@version');
Route::name('v2_library_metadata')->get('library/metadata',                        'Bible\LibraryController@metadata');
Route::name('v2_library_volume')->middleware('AccessControl')->get('library/volume','Bible\LibraryController@volume');
Route::name('v2_library_verse')->get('library/verse',                              'Bible\TextControllerV2@index');
Route::name('v2_library_verseInfo')->get('library/verseinfo',                      'Bible\TextController@info');
Route::name('v2_library_numbers')->get('library/numbers',                          'Wiki\NumbersController@customRange');
Route::name('v2_library_organization')->get('library/organization',                'Organization\OrganizationsController@index');
Route::name('v2_volume_history')->get('library/volumehistory',                     'Bible\LibraryController@history');
Route::name('v2_volume_organization_list')->get('library/volumeorganization',      'Organization\OrganizationsController@index');

// VERSION 2 | Text
Route::name('v2_text_font')->get('text/font',                                      'Bible\TextController@fonts');
Route::name('v2_text_verse')->middleware('AccessControl')->get('text/verse',                                    'Bible\TextControllerV2@index');
Route::name('v2_verseInfo')->get('text/verseinfo',                                 'Bible\TextController@info'); // I cannot see a difference between library/verseinfo and text/verseinfo
Route::name('v2_text_search')->get('text/search',                                  'Bible\TextControllerV2@search');
Route::name('v2_text_search_group')->get('text/searchgroup',                       'Bible\TextController@searchGroup');
Route::name('v2_text_volume')->middleware('AccessControl')->get('/text/volume',    'Bible\BiblesController@show');


// VERSION 2 | Audio
Route::name('v2_audio_location')->get('audio/location',                            'Bible\AudioControllerV2@location');
Route::name('v2_audio_path')->get('audio/path',                                    'Bible\AudioControllerV2@index');
Route::name('v2_audio_timestamps')->get('audio/versestart',                        'Bible\AudioControllerV2@timestampsByReference');

// VERSION 2 | Video
Route::name('v2_video_location')->get('video/videolocation',                       'Organization\FilmsController@location');
Route::name('v2_video_path')->get('video/videopath',                               'Organization\FilmsController@videoPath');
Route::name('v2_api_jesusFilms')->get('library/jesusfilm',                         'Organization\ResourcesController@jesusFilmListing');

Route::name('v2_api_jesusFilm_index')->get('video/jesusfilm',                     'Connections\ArclightController@index');
Route::name('v2_api_jesusFilm_stream')->get('video/jesusfilm/{id}.m3u8',          'Connections\ArclightController@chapter');

// VERSION 2 | Users
Route::name('v2_users_banners_banner')->get('/banners/banner',                     'User\UsersControllerV2@banner');
Route::name('v2_users_user')->match(['get', 'post', 'options'], '/users/user',       'User\UsersControllerV2@user');
Route::name('v2_users_profile')->post('/users/profile',                            'User\UsersControllerV2@profile');
Route::name('v2_user_login')->match(['put', 'post', 'options'], '/users/login',      'User\UsersControllerV2@login');
Route::name('v2_annotations')->get('/annotations/list',                            'User\UsersControllerV2@annotationList');
Route::name('v2_bookmarks')->get('/annotations/bookmark',                          'User\UsersControllerV2@bookmark');
Route::name('v2_bookmarks_alter')->post('/annotations/bookmark',                   'User\UsersControllerV2@bookmarkAlter');
Route::name('v2_bookmarks_delete')->delete('/annotations/bookmark',                'User\UsersControllerV2@bookmarkAlter');
Route::name('v2_notes')->get('/annotations/note',                                  'User\UsersControllerV2@note');
Route::name('v2_notes_store')->post('/annotations/note',                           'User\UsersControllerV2@noteAlter');
Route::name('v2_notes_delete')->delete('/annotations/note',                        'User\UsersControllerV2@noteAlter');
Route::name('v2_highlights')->get('/annotations/highlight',                        'User\UsersControllerV2@highlight');
Route::name('v2_highlights_store')->post('/annotations/highlight',                 'User\UsersControllerV2@highlightAlter');
Route::name('v2_highlights_delete')->delete('/annotations/highlight',              'User\UsersControllerV2@highlightAlter');

Route::prefix('v3')->group(function () {
    Route::name('v3_query')->get('search',                                         'Connections\V3Controller@search');
    Route::name('v3_books')->get('books',                                          'Connections\V3Controller@books');
});
