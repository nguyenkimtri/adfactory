<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VideoController;

Route::post('/video/generate', [VideoController::class, 'store']);
Route::get('/jobs/status', [VideoController::class, 'status']);
Route::delete('/jobs/{id}', [VideoController::class, 'destroy']);
