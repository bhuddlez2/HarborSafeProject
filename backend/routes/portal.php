<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssessmentController;

Route::get('/portal/dashboard', [AssessmentController::class, 'index']);
Route::get('/portal/assessment', [AssessmentController::class, 'create']);
Route::post('/portal/assessment', [AssessmentController::class, 'store'])
    ->middleware('throttle:20,15');