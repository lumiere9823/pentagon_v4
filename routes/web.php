<?php

use App\Http\Controllers\FormSubmissionAdminController;
use App\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\PageContentController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\PagePublishingController;
use App\Http\Controllers\PageReleaseController;
use App\Http\Controllers\PageRevisionController;
use App\Http\Controllers\PageTemplateController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteHomepageController;
use App\Http\Controllers\WebsiteMembershipController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Websites management
    Route::put('websites/{website}/homepage', [WebsiteHomepageController::class, 'update'])->name('websites.homepage.update');
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

    // Page Content Persistence (M14)
    Route::put('websites/{website}/pages/{page}/content', [PageContentController::class, 'update'])->name('websites.pages.content.update');

    // Page Revisions & Rollback (M15)
    Route::get('websites/{website}/pages/{page}/revisions', [PageRevisionController::class, 'index'])->name('websites.pages.revisions.index');
    Route::get('websites/{website}/pages/{page}/revisions/{revision}', [PageRevisionController::class, 'show'])->name('websites.pages.revisions.show');
    Route::post('websites/{website}/pages/{page}/revisions/{revision}/rollback', [PageRevisionController::class, 'rollback'])->name('websites.pages.revisions.rollback');

    // Templates management
    Route::get('websites/{website}/templates', [PageTemplateController::class, 'index'])->name('websites.templates.index');
    Route::post('websites/{website}/templates', [PageTemplateController::class, 'store'])->name('websites.templates.store');
    Route::get('websites/{website}/templates/{template}', [PageTemplateController::class, 'show'])->name('websites.templates.show');
    Route::put('websites/{website}/templates/{template}', [PageTemplateController::class, 'update'])->name('websites.templates.update');
    Route::delete('websites/{website}/templates/{template}', [PageTemplateController::class, 'destroy'])->name('websites.templates.destroy');
    Route::post('websites/{website}/templates/{template}/instantiate', [PageTemplateController::class, 'instantiate'])->name('websites.templates.instantiate');

    // Publishing Engine & Release Management (M16)
    Route::post('websites/{website}/pages/{page}/publish', [PagePublishingController::class, 'publish'])->name('websites.pages.publish');
    Route::post('websites/{website}/pages/{page}/unpublish', [PagePublishingController::class, 'unpublish'])->name('websites.pages.unpublish');
    Route::get('websites/{website}/pages/{page}/releases', [PageReleaseController::class, 'index'])->name('websites.pages.releases.index');
    Route::post('websites/{website}/pages/{page}/releases/{release}/rollback', [PageReleaseController::class, 'rollback'])->name('websites.pages.releases.rollback');

    // Secure Preview (M17)
    Route::get('websites/{website}/pages/{page}/preview', [PagePreviewController::class, 'show'])->name('websites.pages.preview.show');
    Route::post('websites/{website}/pages/{page}/preview/share-link', [PagePreviewController::class, 'shareLink'])->name('websites.pages.preview.share-link');

    // Form Submissions Management (M18)
    Route::get('websites/{website}/submissions', [FormSubmissionAdminController::class, 'index'])->name('websites.submissions.index');
    Route::patch('websites/{website}/submissions/{formSubmission}', [FormSubmissionAdminController::class, 'update'])->name('websites.submissions.update');
    Route::delete('websites/{website}/submissions/{formSubmission}', [FormSubmissionAdminController::class, 'destroy'])->name('websites.submissions.destroy');
});

// Dynamic Public Form Submissions with Throttling & Honeypot (M18)
Route::post('sites/{website:slug}/forms/{form_name?}', [FormSubmissionController::class, 'submit'])
    ->middleware('throttle:5,1')
    ->name('public.sites.forms.submit');

// XML Sitemap & robots.txt Delivery (M19)
Route::get('sites/{website:slug}/sitemap.xml', [SitemapController::class, 'show'])->name('public.sites.sitemap');
Route::get('sites/{website:slug}/robots.txt', [RobotsController::class, 'show'])->name('public.sites.robots');

// Expiring cryptographically signed preview (M17)
Route::get('preview/pages/{page}', [PagePreviewController::class, 'showSigned'])->name('preview.signed');

// Public Static Page Delivery with ETag Caching (M17)
Route::get('sites/{website:slug}/{page:slug}', [PublicPageController::class, 'show'])->name('public.sites.pages.show');

require __DIR__.'/settings.php';
