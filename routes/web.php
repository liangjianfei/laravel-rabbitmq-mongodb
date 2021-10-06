<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test', [\App\Http\Controllers\IndexController::class,'test']);
Route::get('/index', [\App\Http\Controllers\IndexController::class,'index']);
Route::get('/add', [\App\Http\Controllers\IndexController::class,'addMongo']);
Route::get('/send', [\App\Http\Controllers\IndexController::class,'sendMq']);
