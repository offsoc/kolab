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
*/

$action = function () {
    return view('root');
};

Route::get('/', $action);

// Deep-links
Route::get('login', $action);
Route::get('register', $action);
//Route::get('invoice/{invoice}', $action);
