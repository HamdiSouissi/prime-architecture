<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
// A web route ensures the session locale is available on ordinary 404 responses.
Route::fallback(fn () => abort(404));
