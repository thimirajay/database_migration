<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseMigrationController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/fetch-columns', [DatabaseMigrationController::class, 'fetchColumns'])->name('fetch.columns');
Route::post('/fetch-data', [DatabaseMigrationController::class, 'fetchData'])->name('fetch.data');
Route::post('/migrate-data', [DatabaseMigrationController::class, 'migrateData'])->name('migrate.data');
