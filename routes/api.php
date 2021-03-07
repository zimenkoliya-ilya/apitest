<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/find-by-id', 'Api\EventsController@findById');
Route::get('/find-by-user', 'Api\EventsController@findByUser');
Route::get('/find-by-case', 'Api\EventsController@findByCase');
Route::get('/find-by-company', 'Api\EventsController@findByCompanyID');
Route::get('/find-all-case', 'Api\EventsController@findAllByCase');


Route::get('/test-api', 'Api\EventsController@testAPI');
