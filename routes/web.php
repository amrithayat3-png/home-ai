<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    // Every signed-in role: view and ask.
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::post('/ask', [AskController::class, 'ask'])->middleware('throttle:20,1')->name('ask');

    Route::get('/matters', [DashboardController::class, 'matters'])->name('matters');
    Route::get('/matters/{matter}', [DashboardController::class, 'showMatter'])->whereNumber('matter')->name('matters.show');

    Route::patch('/reminders/read-all', [ReminderController::class, 'markAllRead'])->name('reminders.read-all');
    Route::patch('/reminders/{reminder}/read', [ReminderController::class, 'markRead'])->whereNumber('reminder')->name('reminders.read');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->whereNumber('document')->name('documents.show');
    Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->whereNumber('document')->name('documents.file');

    // Administrators and officers: create and change work.
    Route::middleware('role:admin,officer')->group(function () {
        Route::get('/matters/create', [MatterController::class, 'create'])->name('matters.create');
        Route::post('/matters', [MatterController::class, 'store'])->name('matters.store');
        Route::patch('/matters/{matter}/close', [MatterController::class, 'close'])->whereNumber('matter')->name('matters.close');
        Route::patch('/matters/{matter}/reopen', [MatterController::class, 'reopen'])->whereNumber('matter')->name('matters.reopen');

        Route::get('/documents/upload', [DocumentController::class, 'create'])->name('documents.create');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}/matter/create', [MatterController::class, 'createFromDocument'])->whereNumber('document')->name('documents.matter.create');
        Route::patch('/documents/{document}/matter', [DocumentController::class, 'linkMatter'])->whereNumber('document')->name('documents.matter');
        Route::patch('/documents/{document}/category', [DocumentController::class, 'updateCategory'])->whereNumber('document')->name('documents.category');
    });

    // Administrators only: delete matters.
    Route::delete('/matters/{matter}', [MatterController::class, 'destroy'])->middleware('role:admin')->whereNumber('matter')->name('matters.destroy');

    // Administrators only: manage accounts.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::patch('/users/{user}/password', [UserController::class, 'password'])->whereNumber('user')->name('users.password');
    });

    // The starter kit's placeholder page. Signed-in users go to the HOME AI dashboard instead.
    Route::redirect('/dashboard', '/')->name('dashboard');
});

require __DIR__.'/settings.php';
