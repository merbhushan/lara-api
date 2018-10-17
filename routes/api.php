<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('error/{error}', 'exceptoinController@index');

// InitialiseSession middleware is being added to store global information into a session of a user once token is being validate by passport
Route::middleware('auth:api', 'InitialiseSession')->get('/user', function (Request $request) {
    return $request->user();
});
