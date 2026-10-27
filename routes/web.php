<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::get('/contacts/import', [ContactController::class, 'importForm'])->name('contacts.import.form');
Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
