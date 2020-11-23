<?php

Route::name('admin.login')->match(['get', 'post'], 'admin/login', 'User\UsersController@adminLogin');
Route::name('admin.logout')->get('/admin/logout', 'User\UsersController@adminLogout');
Route::name('admin.dashboard')->get('/admin/dashboard', 'Admin\DashboardController@home');
Route::name('admin.key.request')->match(['get', 'post'], '/admin/key/request', 'Admin\KeysController@request');
Route::name('admin.key.requested')->get('/admin/key/requested', 'Admin\KeysController@requested');
