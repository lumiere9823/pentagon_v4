<?php

use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteMembershipController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Websites management
    Route::resource('websites', WebsiteController::class)->except(['edit']);

    // Website Memberships
    Route::post('websites/{website}/memberships', [WebsiteMembershipController::class, 'store'])->name('websites.memberships.store');
    Route::put('websites/{website}/memberships/{membership}', [WebsiteMembershipController::class, 'update'])->name('websites.memberships.update');
    Route::delete('websites/{website}/memberships/{membership}', [WebsiteMembershipController::class, 'destroy'])->name('websites.memberships.destroy');

    // Media Library
    Route::get('websites/{website}/media', [MediaAssetController::class, 'index'])->name('websites.media.index');
    Route::post('websites/{website}/media', [MediaAssetController::class, 'store'])->name('websites.media.store');
    Route::put('websites/{website}/media/{mediaAsset}', [MediaAssetController::class, 'update'])->name('websites.media.update');
    Route::delete('websites/{website}/media/{mediaAsset}', [MediaAssetController::class, 'destroy'])->name('websites.media.destroy');

    // Pages management
    Route::get('websites/{website}/pages/create', [PageController::class, 'create'])->name('websites.pages.create');
    Route::post('websites/{website}/pages', [PageController::class, 'store'])->name('websites.pages.store');
    Route::get('websites/{website}/pages/{page}/edit', [PageController::class, 'edit'])->name('websites.pages.edit');
    Route::get('websites/{website}/pages/{page}/builder', [PageController::class, 'builder'])->name('websites.pages.builder');
    Route::get('websites/{website}/pages/{page}/editor', [PageController::class, 'builder'])->name('websites.pages.editor');
    Route::put('websites/{website}/pages/{page}', [PageController::class, 'update'])->name('websites.pages.update');
    Route::delete('websites/{website}/pages/{page}', [PageController::class, 'destroy'])->name('websites.pages.destroy');
});

require __DIR__.'/settings.php';
