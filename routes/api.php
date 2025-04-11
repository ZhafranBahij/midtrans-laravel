<?php

use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login']);

Route::apiResource('/notes', NoteController::class);
Route::post('midtrans', [MidtransController::class, 'createTransactionSnapRedirect']);
Route::get('midtrans/callback', [MidtransController::class, 'callback']);
Route::post('midtrans/notification', [MidtransController::class, 'notification']);
