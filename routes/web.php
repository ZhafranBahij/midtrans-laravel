<?php

use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::resource('note', NoteController::class)->names('note');
