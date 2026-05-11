<?php

use Illuminate\Support\Facades\Route;
use App\Models\VideoJob;

Route::get('/', function () {
    // Phân trang 5 video mỗi trang
    $jobs = VideoJob::latest()->paginate(5);
    return view('dashboard', compact('jobs'));
});

Route::post('/generate', [App\Http\Controllers\Api\VideoController::class, 'store'])->name('generate');
