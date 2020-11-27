<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
| Middleware options can be located in `app/Http/Kernel.php`
|
*/

Localization::localizedRoutesGroup(function () {

    // Homepage Route
    //Route::get('/', 'WelcomeController@welcome')->name('welcome');
    Route::name('docs')->get('/', 'User\DocsController@index');

    // Legal Overview
    // site note: the API license doc will contain a link the license agreement. This route needs to match the link in the document
    Route::get('/about/legal', 'WelcomeController@legal')->name('legal_overview');
    Route::get('/about/license', 'WelcomeController@license')->name('legal_license');
    Route::get('/about/terms', 'WelcomeController@terms')->name('legal_terms');    
    Route::get('/privacy', 'WelcomeController@privacyPolicy')->name('privacy_policy'); // send it directly to FCBH site

    Route::get('/about/contact', 'User\ContactController@create')->name('contact.create');

    // About
    Route::get('/organizations', 'Organization\OrganizationsController@index')->name('organizations.index');

    // Reader
    Route::get('/reader', 'Bible\ReaderController@languages')->name('reader.languages');
    Route::get('/reader/languages/{language_id}', 'Bible\ReaderController@bibles')->name('reader.bibles');
    Route::get('/reader/bibles/{id}/', 'Bible\ReaderController@books')->name('reader.books');
    Route::get('/reader/bibles/{id}/{book}/{chapter}', 'Bible\ReaderController@chapter')->name('reader.chapter');

    // Authentication Routes | Passwords
    Route::name('login')->match(['get','post'], 'login', 'User\UsersController@login');
    Route::name('logout')->post('logout', 'User\UsersController@logout');
    Route::name('register')->get('register', 'User\UsersController@create');
    Route::post('register', 'User\UsersController@store');

    Route::name('password.request')->get('password/reset',               'User\PasswordsController@showRequestForm');
    Route::name('password.reset')->get('password/reset/{reset_token}',   'User\PasswordsController@showResetForm');
    Route::name('password.email')->post('password/email',                'User\PasswordsController@triggerPasswordResetEmail');
    Route::name('password.attempt')->post('password/reset/attempt',      'User\PasswordsController@validatePasswordReset');
    Route::name('password.attemptPage')->get('password/reset/attempt',   'User\PasswordsController@passwordAttempt');

    Route::name('api_key_email')->post('keys/email',                     'User\Dashboard\KeysController@sendKeyEmail');
    Route::name('api_key_generate')->get('keys/generate/{email_token}',  'User\Dashboard\KeysController@generateAPIKey');

    Route::name('wiki_home')->get('/wiki',                   'WikiController@home');
    Route::name('wiki_bibles.one')->get('/wiki/bibles/{id}', 'WikiController@bible');
    Route::name('wiki_bibles.all')->get('/wiki/bibles',      'WikiController@bibles');

    // Public Routes
    Route::group(['middleware' => ['web']], function () {
        Route::name('validate.index')->get('/validate',                                  'ValidateController@index');
        Route::name('validate.bibles')->get('/validate/bibles',                          'ValidateController@bibles');
        Route::name('validate.filesets')->get('/validate/filesets',                      'ValidateController@filesets');
        Route::name('validate.languages')->get('/validate/languages',                    'ValidateController@languages');
        Route::name('validate.organizations')->get('/validate/organizations',            'ValidateController@organizations');
        Route::name('validations.placeholder_books')->get('/valdiate/placeholder_books', 'ValidateController@placeholder_books');

        // Getting Started
        Route::name('apiDocs_bible_equivalents')->get('/api/bible/bible-equivalents', 'Bible\BibleEquivalentsController@index');

        // Docs Routes
        Route::name('docs')->get('docs', 'User\DocsController@index');
        Route::name('core_concepts')->get('docs/core-concepts', 'User\DocsController@coreConcepts');
        Route::name('available_content')->get('docs/available-content', 'User\DocsController@availableContent');
        Route::name('api_reference')->get('docs/api-reference', 'User\DocsController@apiReference');
        Route::name('user_flows')->get('docs/user-flows', 'User\DocsController@userFlows');


        Route::name('swagger')->get('docs/swagger/{version?}', 'User\DocsController@swagger');
        Route::name('docs.getting_started')->get('guides/getting-started', 'User\DocsController@start');
        Route::name('docs_bible_equivalents')->get('docs/bibles/equivalents', 'User\DocsController@bibleEquivalents');
        Route::name('docs_bible_books')->get('docs/bibles/books', 'User\DocsController@books');
        Route::name('docs_bibles')->get('docs/bibles', 'User\DocsController@bibles');
        Route::name('docs_language_create')->get('docs/language/create', 'User\DocsController@languages');
        Route::name('docs_language_update')->get('docs/language/update', 'User\DocsController@languages');
        Route::name('docs_languages')->get('docs/languages', 'User\DocsController@languages');
        Route::name('docs_countries')->get('docs/countries', 'User\DocsController@countries');
        Route::name('docs_alphabets')->get('docs/alphabets', 'User\DocsController@alphabets');

        // Docs Generator Routes
        Route::name('swagger_docs_gen')->get('open-api-{version}.json',          'User\SwaggerDocsController@swaggerDocsGen');

        // Activation Routes
        Route::name('projects.connect')->get('/connect/{token}', 'Organization\ProjectsController@connect');

        // Socialite Register Routes
        Route::name('social.redirect')->get('/login/redirect/{provider}', 'User\SocialController@redirect');
        Route::name('social.handle')->get('/login/{provider}/callback',   'User\SocialController@callback');
    });

    Route::group(['middleware' => ['auth']],  function () {
        Route::name('dashboard.bibles')->get('dashboard/bibles',                            'User\Dashboard\BibleManagementController@index');
        Route::name('dashboard.bibles.create')->get('dashboard/bibles/create',              'User\Dashboard\BibleManagementController@create');
        Route::name('dashboard.bibles.store')->post('dashboard/bibles',                     'User\Dashboard\BibleManagementController@store');
        Route::name('dashboard.bibles.edit')->get('dashboard/bibles/{bible_id}',            'User\Dashboard\BibleManagementController@edit');
        Route::name('dashboard.bibles.update')->put('dashboard/bibles/{bible_id}',          'User\Dashboard\BibleManagementController@update');

        Route::name('dashboard')->get('home',                                               'User\Dashboard\DashboardController@home');
        Route::name('dashboard_alt')->get('dashboard',                                      'User\Dashboard\DashboardController@home');
        Route::name('dashboard.projects.index')->get('api/projects',                        'User\Dashboard\ProjectsController@index');
        Route::name('dashboard.projects.create')->get('api/projects/create',                'User\Dashboard\ProjectsController@create');
        Route::name('dashboard.projects.store')->post('api/projects',                       'User\Dashboard\ProjectsController@store');
        Route::name('dashboard.projects.members')->get('api/projects/{project_id}/members', 'User\Dashboard\ProjectsController@members');
        Route::name('dashboard.projects.edit')->get('api/projects/{project_id}/edit',       'User\Dashboard\ProjectsController@edit');
        Route::name('dashboard.projects.update')->put('api/projects/{project_id}/',         'User\Dashboard\ProjectsController@update');

        // Keys
        Route::resource('api/keys', 'User\Dashboard\KeysController');
        Route::name('dashboard.keys.create')->get('api/keys/create',            'User\Dashboard\KeysController@create');
        Route::name('dashboard.keys.store')->post('api/keys',                   'User\Dashboard\KeysController@store');
        Route::name('dashboard.keys.clone')->post('api/keys/{id}/clone',        'User\Dashboard\KeysController@clone');
        Route::name('dashboard.keys.edit')->get('api/keys/{id}/edit',           'User\Dashboard\KeysController@edit');
        Route::name('dashboard.keys.update')->put('api/keys/{id}',              'User\Dashboard\KeysController@update');
        Route::name('dashboard.keys.access')->get('api/keys/{id}/accessGroups', 'User\Dashboard\KeysController@accessGroups');
        Route::name('dashboard.keys.delete')->get('api/keys/{id}/delete',       'User\Dashboard\KeysController@delete');
        Route::name('dashboard.keys.destroy')->post('api/keys/{id}/delete',      'User\Dashboard\KeysController@destroy');

        // Profiles
        Route::name('profile')->get('profile',                                              'User\Dashboard\ProfileController@profile');
        Route::name('profile.update')->put('profile/{user_id}',                             'User\Dashboard\ProfileController@updateProfile');
    });
});

Route::name('status')->get('/status', 'ApiMetadataController@getStatus');
Route::name('status')->get('/status/cache', 'ApiMetadataController@getCacheStatus');
