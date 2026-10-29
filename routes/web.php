<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ClientContactController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectActionsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->middleware('signed')->name('invitation.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'store'])->middleware(['signed', 'throttle:10,1'])->name('invitation.store');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');
    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::post('/clients/{client}/restore', [ClientController::class, 'restore'])->withTrashed()->name('clients.restore');
        Route::post('/clients/{client}/contacts/{contact}/restore', [ClientContactController::class, 'restore'])->withTrashed()->name('clients.contacts.restore');
        Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
        Route::put('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
        Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');
        Route::resource('clients', ClientController::class);
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->withTrashed()->name('projects.restore');
        Route::post('/projects/{project}/status', [ProjectActionsController::class, 'status'])->name('projects.status');
        Route::post('/projects/{project}/lead', [ProjectActionsController::class, 'lead'])->name('projects.lead');
        Route::post('/projects/{project}/members', [ProjectActionsController::class, 'addMember'])->name('projects.members.store');
        Route::delete('/projects/{project}/members/{member}', [ProjectActionsController::class, 'removeMember'])->name('projects.members.destroy');
        Route::post('/projects/{project}/notes', [ProjectActionsController::class, 'addNote'])->name('projects.notes.store');
        Route::delete('/projects/{project}/notes/{note}', [ProjectActionsController::class, 'removeNote'])->name('projects.notes.destroy');
        Route::resource('projects', ProjectController::class);

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::delete('/profile/sessions', [ProfileController::class, 'logoutOthers'])->name('profile.sessions.destroy');
        Route::post('/users/{user}/invitation', [UserController::class, 'resendInvitation'])->middleware(['permission:users.manage', 'throttle:6,1'])->name('users.invitation');
        Route::resource('users', UserController::class)->except(['show'])->middleware('permission:users.manage');
        Route::get('/activity', [ActivityController::class, 'index'])->middleware('permission:activity.view')->name('activity.index');
    });
});
Route::fallback(fn () => abort(404));
