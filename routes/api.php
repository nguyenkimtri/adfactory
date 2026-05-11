<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VideoController;

Route::post('/video/generate', [VideoController::class, 'store']);
