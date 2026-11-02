<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailDraftController;
use App\Http\Controllers\OutreachController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReplyController;
use App\Http\Controllers\SkillController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/import', [ContactController::class, 'importForm'])->name('contacts.import.form');
    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');

    Route::post('/outreach/classify', [OutreachController::class, 'classify'])->name('outreach.classify');
    Route::post('/outreach/emails', [OutreachController::class, 'findEmails'])->name('outreach.emails');
    Route::post('/outreach/drafts', [OutreachController::class, 'generateDrafts'])->name('outreach.drafts');

    Route::get('/drafts', [EmailDraftController::class, 'index'])->name('drafts.index');
    Route::get('/drafts/{draft}', [EmailDraftController::class, 'edit'])->name('drafts.edit');
    Route::post('/contacts/{contact}/draft', [EmailDraftController::class, 'generate'])->name('contacts.draft');
    // POST too: the review form posts to "save" or, via formaction, to "approve & send".
    Route::match(['put', 'post'], '/drafts/{draft}', [EmailDraftController::class, 'update'])->name('drafts.update');
    Route::post('/drafts/{draft}/approve', [EmailDraftController::class, 'approve'])->name('drafts.approve');
    Route::post('/drafts/{draft}/reject', [EmailDraftController::class, 'reject'])->name('drafts.reject');

    Route::get('/replies', [ReplyController::class, 'index'])->name('replies.index');
    Route::post('/replies/fetch', [ReplyController::class, 'fetch'])->middleware('throttle:6,1')->name('replies.fetch');

    Route::resource('skills', SkillController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
