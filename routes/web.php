<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\FileController;
use App\Http\Middleware\EnsureKeyIsValid;
use Illuminate\Support\Facades\Route;

Route::prefix('export')->middleware(EnsureKeyIsValid::class)->group(function () {
    Route::get('{file}', [ExportController::class, 'show']);
});

Route::prefix('files')->middleware(EnsureKeyIsValid::class)->group(function () {
    Route::get('{file}', [FileController::class, 'download']);
});
