<?php

use Illuminate\Support\Facades\Route;
use App\Models\VideoJob;

Route::get('/', function () {
    $jobs = VideoJob::latest()->paginate(5);
    return view('dashboard', compact('jobs'));
});

Route::post('/generate', [App\Http\Controllers\Api\VideoController::class, 'store'])->name('generate');
