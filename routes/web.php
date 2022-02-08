<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Middleware options can be located in `app/Http/Kernel.php`
|
*/

Localization::localizedRoutesGroup(function () {

    // Primary documentation resides in WebFlow. Home page is now redirected to WebFlow
    // Route::get('/', 'WelcomeController@redirect')->name('webflow');
    Route::get('/', 'WelcomeController@welcome')->name('welcome');
    

    // Referenced by bible.is for password reset
    // TBD - what is the web page that we need here?
    /*
    - needed:
    - reset.blade
    - v4_internal_user.password_reset (api route)
    */

    Route::name('login')->match(
        ['get', 'post'],
        'login',
        'User\UsersController@login'
    );
    Route::name('logout')->post('logout', 'User\UsersController@logout');
    Route::name('register')->get('register', 'User\UsersController@create');
    Route::post('register', 'User\UsersController@store');

    Route::name('password.request')->get(
        'password/reset',
        'User\PasswordsController@showRequestForm'
    );
    Route::name('password.reset')->get(
        'password/reset/{reset_token}',
        'User\PasswordsController@showResetForm'
    );
    Route::name('password.email')->post(
        'password/email',
        'User\PasswordsController@triggerPasswordResetEmail'
    );
    Route::name('password.attempt')->post(
        'password/reset/attempt',
        'User\PasswordsController@validatePasswordReset'
    );
    Route::name('password.attemptPage')->get(
        'password/reset/attempt',
        'User\PasswordsController@passwordAttempt'
    );


    // API KEY routes
    Route::name('api_key.login')->match(
        ['get', 'post'],
        'admin/login',
        'User\UsersController@adminLogin'
    );
    Route::name('api_key.logout')->get(
        '/api_key/logout',
        'User\UsersController@adminLogout'
    );
    Route::name('api_key.dashboard')->get(
        '/api_key/dashboard',
        'ApiKey\DashboardController@home'
    );
    Route::name('api_key.request')->match(
        ['get', 'post'],
        '/api_key/request',
        'ApiKey\KeysController@request'
    );
    Route::name('api_key.requested')->get(
        '/api_key/requested',
        'ApiKey\KeysController@requested'
    );
    Route::name('api_key.send_email')->post(
        '/api_key/send_email',
        'ApiKey\DashboardController@sendEmail'
    );
    Route::name('api_key.save_note')->post(
        '/api_key/save_note',
        'ApiKey\DashboardController@saveNote'
    );
    Route::name('api_key.approve_api_key')->post(
        '/api_key/approve_api_key',
        'ApiKey\DashboardController@approveApiKey'
    );
    Route::name('api_key.delete_api_key')->post(
        '/api_key/delete_api_key',
        'ApiKey\DashboardController@deleteApiKey'
    );
    Route::name('api_key.change_api_key_state')->post(
        '/api_key/change_api_key_state',
        'ApiKey\DashboardController@changeApiKeyState'
    );

    Route::group(['middleware' => ['auth']], function () {
        Route::name('dashboard')->get(
            'home',
            'User\Dashboard\DashboardController@home'
        );
        Route::name('dashboard_alt')->get(
            'dashboard',
            'User\Dashboard\DashboardController@home'
        );
    });
});

Route::name('status')->get('/status', 'ApiMetadataController@getStatus');
Route::name('status')->get(
    '/status/cache',
    'ApiMetadataController@getCacheStatus'
);

Route::group(['middleware' => ['web']], function () {
    // Docs Generator Routes
    Route::name('swagger_docs_gen')->get(
        'open-api-{version}.json',
        'User\SwaggerDocsController@swaggerDocsGen'
    );
});

// ------------------------------  attic. Will likely be removed, but needs research -----------------------------------------------------
//Localization::localizedRoutesGroup(function () {

    // About
    Route::get('/about/contact', 'User\ContactController@create')->name(
        'contact.create'
    );

    Route::get(
        '/organizations',
        'Organization\OrganizationsController@index'
    )->name('organizations.index');


    // Reader
    Route::get('/reader', 'Bible\ReaderController@languages')->name(
        'reader.languages'
    );
    Route::get(
        '/reader/languages/{language_id}',
        'Bible\ReaderController@bibles'
    )->name('reader.bibles');
    Route::get('/reader/bibles/{id}/', 'Bible\ReaderController@books')->name(
        'reader.books'
    );
    Route::get(
        '/reader/bibles/{id}/{book}/{chapter}',
        'Bible\ReaderController@chapter'
    )->name('reader.chapter');

    // Wiki
    Route::name('wiki_home')->get('/wiki', 'WikiController@home');
    Route::name('wiki_bibles.one')->get(
        '/wiki/bibles/{id}',
        'WikiController@bible'
    );
    Route::name('wiki_bibles.all')->get(
        '/wiki/bibles',
        'WikiController@bibles'
    );
    // Validate
    Route::group(['middleware' => ['web']], function () {
        Route::name('validate.index')->get(
            '/validate',
            'ValidateController@index'
        );
        Route::name('validate.bibles')->get(
            '/validate/bibles',
            'ValidateController@bibles'
        );
        Route::name('validate.filesets')->get(
            '/validate/filesets',
            'ValidateController@filesets'
        );
        Route::name('validate.languages')->get(
            '/validate/languages',
            'ValidateController@languages'
        );
        Route::name('validate.organizations')->get(
            '/validate/organizations',
            'ValidateController@organizations'
        );
        Route::name('validations.placeholder_books')->get(
            '/valdiate/placeholder_books',
            'ValidateController@placeholder_books'
        );
        // Docs Routes
        Route::name('docs')->get('docs', 'User\DocsController@index');

        Route::name('docs.getting_started')->get(
            'guides/getting-started',
            'User\DocsController@start'
        );
        Route::name('docs_bible_equivalents')->get(
            'docs/bibles/equivalents',
            'User\DocsController@bibleEquivalents'
        );
        Route::name('docs_bible_books')->get(
            'docs/bibles/books',
            'User\DocsController@books'
        );
        Route::name('docs_bibles')->get(
            'docs/bibles',
            'User\DocsController@bibles'
        );
        Route::name('docs_language_create')->get(
            'docs/language/create',
            'User\DocsController@languages'
        );
        Route::name('docs_language_update')->get(
            'docs/language/update',
            'User\DocsController@languages'
        );
        Route::name('docs_languages')->get(
            'docs/languages',
            'User\DocsController@languages'
        );
        Route::name('docs_countries')->get(
            'docs/countries',
            'User\DocsController@countries'
        );
        Route::name('docs_alphabets')->get(
            'docs/alphabets',
            'User\DocsController@alphabets'
        );

        Route::name('apiDocs_bible_equivalents')->get(
            '/api/bible/bible-equivalents',
            'Bible\BibleEquivalentsController@index'
        );

        Route::name('projects.connect')->get(
            '/connect/{token}',
            'Organization\ProjectsController@connect'
        );

        // Socialite Register Routes
        Route::name('social.redirect')->get(
            '/login/redirect/{provider}',
            'User\SocialController@redirect'
        );
        Route::name('social.handle')->get(
            '/login/{provider}/callback',
            'User\SocialController@callback'
        );
        // }); end of localization
    
        // not part of localization.. part of auth
        Route::group(['middleware' => ['auth']], function () {

        // Bible Management
            Route::name('dashboard.bibles')->get(
                'dashboard/bibles',
                'User\Dashboard\BibleManagementController@index'
            );
            Route::name('dashboard.bibles.create')->get(
                'dashboard/bibles/create',
                'User\Dashboard\BibleManagementController@create'
            );
            Route::name('dashboard.bibles.store')->post(
                'dashboard/bibles',
                'User\Dashboard\BibleManagementController@store'
            );
            Route::name('dashboard.bibles.edit')->get(
                'dashboard/bibles/{bible_id}',
                'User\Dashboard\BibleManagementController@edit'
            );
            Route::name('dashboard.bibles.update')->put(
                'dashboard/bibles/{bible_id}',
                'User\Dashboard\BibleManagementController@update'
            );

            // Projects Management
            Route::name('dashboard.projects.index')->get(
                'api/projects',
                'User\Dashboard\ProjectsController@index'
            );
            Route::name('dashboard.projects.create')->get(
                'api/projects/create',
                'User\Dashboard\ProjectsController@create'
            );
            Route::name('dashboard.projects.store')->post(
                'api/projects',
                'User\Dashboard\ProjectsController@store'
            );
            Route::name('dashboard.projects.members')->get(
                'api/projects/{project_id}/members',
                'User\Dashboard\ProjectsController@members'
            );
            Route::name('dashboard.projects.edit')->get(
                'api/projects/{project_id}/edit',
                'User\Dashboard\ProjectsController@edit'
            );
            Route::name('dashboard.projects.update')->put(
                'api/projects/{project_id}/',
                'User\Dashboard\ProjectsController@update'
            );

            // Profiles
            Route::name('profile')->get(
                'profile',
                'User\Dashboard\ProfileController@profile'
            );
            Route::name('profile.update')->put(
                'profile/{user_id}',
                'User\Dashboard\ProfileController@updateProfile'
            );

            // Keys
            Route::resource('api/keys', 'User\Dashboard\KeysController');
            Route::name('dashboard.keys.create')->get(
                'api/keys/create',
                'User\Dashboard\KeysController@create'
            );
            Route::name('dashboard.keys.store')->post(
                'api/keys',
                'User\Dashboard\KeysController@store'
            );
            Route::name('dashboard.keys.clone')->post(
                'api/keys/{id}/clone',
                'User\Dashboard\KeysController@clone'
            );
            Route::name('dashboard.keys.edit')->get(
                'api/keys/{id}/edit',
                'User\Dashboard\KeysController@edit'
            );
            Route::name('dashboard.keys.update')->put(
                'api/keys/{id}',
                'User\Dashboard\KeysController@update'
            );
            Route::name('dashboard.keys.access')->get(
                'api/keys/{id}/accessGroups',
                'User\Dashboard\KeysController@accessGroups'
            );
            Route::name('dashboard.keys.delete')->get(
                'api/keys/{id}/delete',
                'User\Dashboard\KeysController@delete'
            );
            Route::name('dashboard.keys.destroy')->post(
                'api/keys/{id}/delete',
                'User\Dashboard\KeysController@destroy'
            );
            // API Key (old?)
            Route::name('api_key_email')->post(
                'keys/email',
                'User\Dashboard\KeysController@sendKeyEmail'
            );
            Route::name('api_key_generate')->get(
                'keys/generate/{email_token}',
                'User\Dashboard\KeysController@generateAPIKey'
            );
        });
    });
