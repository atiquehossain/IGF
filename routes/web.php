<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */

// Route::get('/', function () {
//     return view('welcome');
// });
// Auth::routes();
Auth::routes([
    'login' => false,
    'register' => false, // Registration Routes...
    'reset' => false, // Password Reset Routes...
    'verify' => false, // Email Verification Routes...
]);
//Auth::routes(['register' => false]);

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'permission'])->group(function () {
        Route::get('/', 'Admin\DashboardController@index')->name('dashboard.index');
        Route::get('language/{language?}', 'Admin\LocaleController@language')->name('admin.language');

        // Recruitment listing, form-builder, private applicant and reviewed-import workflows.
        Route::prefix('recruitment')->group(function () {
            Route::get('jobs', 'Admin\JobPostingController@index')->name('recruitment.jobs.index');
            Route::get('jobs/create', 'Admin\JobPostingController@create')->name('recruitment.jobs.create');
            Route::post('jobs', 'Admin\JobPostingController@store')->name('recruitment.jobs.store');
            Route::get('jobs/{job}', 'Admin\JobPostingController@show')->name('recruitment.jobs.show');
            Route::get('jobs/{job}/edit', 'Admin\JobPostingController@edit')->name('recruitment.jobs.edit');
            Route::put('jobs/{job}', 'Admin\JobPostingController@update')->name('recruitment.jobs.update');
            Route::patch('jobs/{job}/status', 'Admin\JobPostingController@status')->name('recruitment.jobs.status');
            Route::post('jobs/{job}/duplicate', 'Admin\JobPostingController@duplicate')->name('recruitment.jobs.duplicate');
            Route::delete('jobs/{job}', 'Admin\JobPostingController@destroy')->name('recruitment.jobs.destroy');

            Route::get('forms', 'Admin\ApplicationFormController@index')->defaults('purpose', 'job')->name('recruitment.forms.index');
            Route::get('forms/create', 'Admin\ApplicationFormController@create')->defaults('purpose', 'job')->name('recruitment.forms.create');
            Route::post('forms', 'Admin\ApplicationFormController@store')->defaults('purpose', 'job')->name('recruitment.forms.store');
            Route::get('forms/{form}/edit', 'Admin\ApplicationFormController@edit')->defaults('purpose', 'job')->name('recruitment.forms.edit');
            Route::put('forms/{form}', 'Admin\ApplicationFormController@update')->defaults('purpose', 'job')->name('recruitment.forms.update');
            Route::post('forms/{form}/publish', 'Admin\ApplicationFormController@publish')->defaults('purpose', 'job')->name('recruitment.forms.publish');
            Route::post('forms/{form}/duplicate', 'Admin\ApplicationFormController@duplicate')->defaults('purpose', 'job')->name('recruitment.forms.duplicate');
            Route::get('forms/{form}/preview', 'Admin\ApplicationFormController@preview')->defaults('purpose', 'job')->name('recruitment.forms.preview');

            Route::get('applications', 'Admin\JobApplicationController@index')->name('recruitment.applications.index');
            Route::post('applications/search', 'Admin\JobApplicationController@search')->name('recruitment.applications.search');
            Route::post('applications/search/clear', 'Admin\JobApplicationController@clearSearch')->name('recruitment.applications.search.clear');
            Route::post('applications/bulk', 'Admin\JobApplicationController@bulk')->name('recruitment.applications.bulk');
            Route::get('applications/export', 'Admin\JobApplicationController@export')->name('recruitment.applications.export');
            Route::get('applications/{application}', 'Admin\JobApplicationController@show')->name('recruitment.applications.show');
            Route::patch('applications/{application}/workflow', 'Admin\JobApplicationController@workflow')->name('recruitment.applications.workflow');
            Route::patch('applications/{application}/assignment', 'Admin\JobApplicationController@assign')->name('recruitment.applications.assign');
            Route::put('applications/{application}/score', 'Admin\JobApplicationController@score')->name('recruitment.applications.score');
            Route::post('applications/{application}/notes', 'Admin\JobApplicationController@addNote')->name('recruitment.applications.notes.store');
            Route::get('applications/{application}/documents/{document}', 'Admin\JobApplicationController@download')->name('recruitment.applications.download');
            Route::post('applications/{application}/anonymize', 'Admin\JobApplicationController@anonymize')->name('recruitment.applications.anonymize');
            Route::delete('applications/{application}/delete', 'Admin\JobApplicationController@delete')->name('recruitment.applications.delete');
            Route::delete('applications/{application}', 'Admin\JobApplicationController@destroy')->name('recruitment.applications.destroy');

            Route::get('imports', 'Admin\ApplicationImportController@index')->name('recruitment.imports.index');
            Route::get('imports/create', 'Admin\ApplicationImportController@create')->name('recruitment.imports.create');
            Route::post('imports', 'Admin\ApplicationImportController@store')->name('recruitment.imports.store');
            Route::match(['get', 'post'], 'imports/{batch}/preview', 'Admin\ApplicationImportController@preview')->name('recruitment.imports.preview');
            Route::post('imports/{batch}/confirm', 'Admin\ApplicationImportController@confirm')->name('recruitment.imports.confirm');
            Route::get('imports/{batch}/result', 'Admin\ApplicationImportController@result')->name('recruitment.imports.result');
            Route::get('imports/{batch}/errors', 'Admin\ApplicationImportController@downloadErrors')->name('recruitment.imports.errors.download');
        });

        // Workshops are always free. Management and registration permissions remain separate.
        Route::get('workshops', 'Admin\WorkshopController@index')->name('workshops.index');
        Route::get('workshops/create', 'Admin\WorkshopController@create')->name('workshops.create');
        Route::post('workshops', 'Admin\WorkshopController@store')->name('workshops.store');
        Route::get('workshops/{workshop}', 'Admin\WorkshopController@show')->name('workshops.show');
        Route::get('workshops/{workshop}/edit', 'Admin\WorkshopController@edit')->name('workshops.edit');
        Route::put('workshops/{workshop}', 'Admin\WorkshopController@update')->name('workshops.update');
        Route::patch('workshops/{workshop}/status', 'Admin\WorkshopController@status')->name('workshops.status');
        Route::post('workshops/{workshop}/duplicate', 'Admin\WorkshopController@duplicate')->name('workshops.duplicate');
        Route::delete('workshops/{workshop}', 'Admin\WorkshopController@destroy')->name('workshops.destroy');

        Route::prefix('workshop')->group(function () {
            Route::get('forms', 'Admin\ApplicationFormController@index')->defaults('purpose', 'workshop')->name('workshop.forms.index');
            Route::get('forms/create', 'Admin\ApplicationFormController@create')->defaults('purpose', 'workshop')->name('workshop.forms.create');
            Route::post('forms', 'Admin\ApplicationFormController@store')->defaults('purpose', 'workshop')->name('workshop.forms.store');
            Route::get('forms/{form}/edit', 'Admin\ApplicationFormController@edit')->defaults('purpose', 'workshop')->name('workshop.forms.edit');
            Route::put('forms/{form}', 'Admin\ApplicationFormController@update')->defaults('purpose', 'workshop')->name('workshop.forms.update');
            Route::post('forms/{form}/publish', 'Admin\ApplicationFormController@publish')->defaults('purpose', 'workshop')->name('workshop.forms.publish');
            Route::post('forms/{form}/duplicate', 'Admin\ApplicationFormController@duplicate')->defaults('purpose', 'workshop')->name('workshop.forms.duplicate');
            Route::get('forms/{form}/preview', 'Admin\ApplicationFormController@preview')->defaults('purpose', 'workshop')->name('workshop.forms.preview');

            Route::get('registrations', 'Admin\WorkshopRegistrationController@index')->name('workshop.registrations.index');
            Route::post('registrations/search', 'Admin\WorkshopRegistrationController@search')->name('workshop.registrations.search');
            Route::post('registrations/search/clear', 'Admin\WorkshopRegistrationController@clearSearch')->name('workshop.registrations.search.clear');
            Route::post('registrations/bulk', 'Admin\WorkshopRegistrationController@bulk')->name('workshop.registrations.bulk');
            Route::get('registrations/export', 'Admin\WorkshopRegistrationController@export')->name('workshop.registrations.export');
            Route::get('registrations/{registration}', 'Admin\WorkshopRegistrationController@show')->name('workshop.registrations.show');
            Route::patch('registrations/{registration}/workflow', 'Admin\WorkshopRegistrationController@workflow')->name('workshop.registrations.workflow');
            Route::patch('registrations/{registration}/assignment', 'Admin\WorkshopRegistrationController@assign')->name('workshop.registrations.assign');
            Route::post('registrations/{registration}/notes', 'Admin\WorkshopRegistrationController@addNote')->name('workshop.registrations.notes.store');
            Route::get('registrations/{registration}/documents/{document}', 'Admin\WorkshopRegistrationController@download')->name('workshop.registrations.download');
            Route::post('registrations/{registration}/anonymize', 'Admin\WorkshopRegistrationController@anonymize')->name('workshop.registrations.anonymize');
            Route::delete('registrations/{registration}/delete', 'Admin\WorkshopRegistrationController@delete')->name('workshop.registrations.delete');
            Route::delete('registrations/{registration}', 'Admin\WorkshopRegistrationController@destroy')->name('workshop.registrations.destroy');

            Route::get('imports', 'Admin\ApplicationImportController@index')->name('workshop.imports.index');
            Route::get('imports/create', 'Admin\ApplicationImportController@create')->name('workshop.imports.create');
            Route::post('imports', 'Admin\ApplicationImportController@store')->name('workshop.imports.store');
            Route::match(['get', 'post'], 'imports/{batch}/preview', 'Admin\ApplicationImportController@preview')->name('workshop.imports.preview');
            Route::post('imports/{batch}/confirm', 'Admin\ApplicationImportController@confirm')->name('workshop.imports.confirm');
            Route::get('imports/{batch}/result', 'Admin\ApplicationImportController@result')->name('workshop.imports.result');
            Route::get('imports/{batch}/errors', 'Admin\ApplicationImportController@downloadErrors')->name('workshop.imports.errors.download');
        });

        Route::get('album', 'Admin\AlbumController@index')->name('album.index');
        Route::get('album/create', 'Admin\AlbumController@create')->name('album.create');
        Route::post('album', 'Admin\AlbumController@store')->name('album.store');
        Route::get('album/{id}', 'Admin\AlbumController@show')->name('album.show');
        Route::get('album/{id}/edit', 'Admin\AlbumController@edit')->name('album.edit');
        Route::put('album', 'Admin\AlbumController@update')->name('album.update');
        Route::put('album/{id}', 'Admin\AlbumController@status')->name('album.status');
        Route::delete('album/{id}/', 'Admin\AlbumController@destroy')->name('album.destroy');

        Route::get('gallery', 'Admin\GalleryController@index')->name('gallery.index');
        Route::get('gallery/create', 'Admin\GalleryController@create')->name('gallery.create');
        Route::post('gallery', 'Admin\GalleryController@store')->name('gallery.store');
        Route::get('gallery/{id}', 'Admin\GalleryController@show')->name('gallery.show');
        Route::get('gallery/{id}/edit', 'Admin\GalleryController@edit')->name('gallery.edit');
        Route::put('gallery', 'Admin\GalleryController@update')->name('gallery.update');
        Route::put('gallery/{id}', 'Admin\GalleryController@status')->name('gallery.status');
        Route::delete('gallery/{id}/', 'Admin\GalleryController@destroy')->name('gallery.destroy');
        Route::get('gallery/image/{id?}/{size?}/{img?}/', 'Admin\GalleryController@image')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('gallery.image');

        Route::get('banner', 'Admin\BannerController@index')->name('banner.index');
        Route::get('banner/create', 'Admin\BannerController@create')->name('banner.create');
        Route::post('banner', 'Admin\BannerController@store')->name('banner.store');
        Route::get('banner/{id}', 'Admin\BannerController@show')->name('banner.show');
        Route::get('banner/{id}/edit', 'Admin\BannerController@edit')->name('banner.edit');
        Route::put('banner', 'Admin\BannerController@update')->name('banner.update');
        Route::put('banner/{id}', 'Admin\BannerController@status')->name('banner.status');
        Route::delete('banner/{id}/', 'Admin\BannerController@destroy')->name('banner.destroy');
        Route::get('banner/image/{id?}/{size?}/{img?}/', 'Admin\BannerController@image')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('banner.image');

        Route::get('publication', 'Admin\NoticeBoardController@index')->name('notice.board.index');
        Route::get('publication/create', 'Admin\NoticeBoardController@create')->name('notice.board.create');
        Route::post('publication', 'Admin\NoticeBoardController@store')->name('notice.board.store');
        Route::get('publication/{id}/edit', 'Admin\NoticeBoardController@edit')->name('notice.board.edit');
        Route::put('publication', 'Admin\NoticeBoardController@update')->name('notice.board.update');
        Route::put('publication/{id}', 'Admin\NoticeBoardController@status')->name('notice.board.status');
        Route::delete('publication/{id}/', 'Admin\NoticeBoardController@destroy')->name('notice.board.destroy');
        Route::get('publication/image/{id?}/{size?}/{img?}/', 'Admin\NoticeBoardController@image')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('notice.board.image');

        Route::get('annual-report', 'Admin\AnnualReportController@index')->name('annual.report.index');
        Route::get('annual-report/create', 'Admin\AnnualReportController@create')->name('annual.report.create');
        Route::post('annual-report', 'Admin\AnnualReportController@store')->name('annual.report.store');
        Route::get('annual-report/{id}/edit', 'Admin\AnnualReportController@edit')->name('annual.report.edit');
        Route::get('annual-report/{id}', 'Admin\AnnualReportController@show')->whereNumber('id')->name('annual.report.show');
        Route::put('annual-report', 'Admin\AnnualReportController@update')->name('annual.report.update');
        Route::put('annual-report/{id}', 'Admin\AnnualReportController@status')->name('annual.report.status');
        Route::delete('annual-report/{id}/', 'Admin\AnnualReportController@destroy')->name('annual.report.destroy');
        Route::get('annual-report/image/{id?}/{size?}/{img?}/', 'Admin\AnnualReportController@image')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('annual.report.image');

        Route::get('donations', 'Admin\DonationHistoryController@index')->name('donations.index');
        Route::post('donations/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'donations')->name('donations.search');
        Route::post('donations/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'donations')->name('donations.search.clear');
        Route::put('donations/{donation}/resolve-review', 'Admin\DonationHistoryController@resolveReview')
            ->name('donations.review.resolve');
        Route::post('donations/{donation}/allocations', 'Admin\DonationHistoryController@allocate')
            ->name('donations.allocate');

        Route::get('sponsorships', 'Admin\SponsorAChildController@index')->name('sponsorships.index');
        Route::post('sponsorships/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'sponsorships')->name('sponsorships.search');
        Route::post('sponsorships/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'sponsorships')->name('sponsorships.search.clear');
        Route::put('sponsorships/{sponsorship}/workflow', 'Admin\SponsorAChildController@updateWorkflow')->name('sponsorships.workflow');

        Route::get('volunteer', 'Admin\VolunteerController@index')->name('volunteer.index');
        Route::post('volunteer/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'volunteers')->name('volunteer.search');
        Route::post('volunteer/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'volunteers')->name('volunteer.search.clear');
        Route::get('volunteer/export', 'Admin\VolunteerController@exportExcel')->name('volunteer.export.excel');
        Route::put('volunteer/{volunteer}/workflow', 'Admin\VolunteerController@updateWorkflow')->name('volunteer.workflow');

        // Volunteer Cause Section
        Route::get('volunteer-cause', 'Admin\VolunteerCauseController@index')->name('volunteerCause.index');
        Route::get('volunteer-cause/create', 'Admin\VolunteerCauseController@create')->name('volunteerCause.create');
        Route::post('volunteer-cause', 'Admin\VolunteerCauseController@store')->name('volunteerCause.store');
        Route::get('volunteer-cause/{id}', 'Admin\VolunteerCauseController@show')->name('volunteerCause.show');
        Route::get('volunteer-cause/{id}/edit', 'Admin\VolunteerCauseController@edit')->name('volunteerCause.edit');
        Route::put('volunteer-cause', 'Admin\VolunteerCauseController@update')->name('volunteerCause.update');
        Route::put('volunteer-cause/{id}', 'Admin\VolunteerCauseController@status')->name('volunteerCause.status');
        Route::delete('volunteer-cause/{id}/', 'Admin\VolunteerCauseController@destroy')->name('volunteerCause.destroy');

        Route::get('member', 'Admin\LatestNewsController@index')->name('latest.news.index');
        Route::get('member/create', 'Admin\LatestNewsController@create')->name('latest.news.create');
        Route::post('member', 'Admin\LatestNewsController@store')->name('latest.news.store');
        Route::post('member/group', 'Admin\TeamGroupController@store')->name('latest.news.group.store');
        Route::put('member/group/{teamGroup}', 'Admin\TeamGroupController@update')->name('latest.news.group.update');
        Route::put('member/group/{teamGroup}/status', 'Admin\TeamGroupController@status')->name('latest.news.group.status');
        Route::delete('member/group/{teamGroup}', 'Admin\TeamGroupController@destroy')->name('latest.news.group.destroy');
        Route::get('member/{id}', 'Admin\LatestNewsController@show')->name('latest.news.show');
        Route::get('member/{id}/edit', 'Admin\LatestNewsController@edit')->name('latest.news.edit');
        Route::put('member', 'Admin\LatestNewsController@update')->name('latest.news.update');
        Route::put('member/{id}', 'Admin\LatestNewsController@status')->name('latest.news.status');
        Route::delete('member/{id}/', 'Admin\LatestNewsController@destroy')->name('latest.news.destroy');
        Route::get('member/image/{id?}/{size?}/{img?}/', 'Admin\LatestNewsController@image')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('latest.news.image');

        Route::get('division', 'Admin\DivisionController@index')->name('division.index');
        Route::get('division/create', 'Admin\DivisionController@create')->name('division.create');
        Route::post('division', 'Admin\DivisionController@store')->name('division.store');
        Route::get('division/{id}', 'Admin\DivisionController@show')->name('division.show');
        Route::get('division/{id}/edit', 'Admin\DivisionController@edit')->name('division.edit');
        Route::put('division', 'Admin\DivisionController@update')->name('division.update');
        Route::put('division/{id}', 'Admin\DivisionController@status')->name('division.status');
        Route::delete('division/{id}/', 'Admin\DivisionController@destroy')->name('division.destroy');

        Route::get('upazila', 'Admin\UpazilaController@index')->name('upazila.index');
        Route::get('upazila/create', 'Admin\UpazilaController@create')->name('upazila.create');
        Route::post('upazila', 'Admin\UpazilaController@store')->name('upazila.store');
        Route::get('upazila/{id}/edit', 'Admin\UpazilaController@edit')->name('upazila.edit');
        Route::put('upazila', 'Admin\UpazilaController@update')->name('upazila.update');
        Route::put('upazila/{id}', 'Admin\UpazilaController@status')->name('upazila.status');
        Route::delete('upazila/{id}/', 'Admin\UpazilaController@destroy')->name('upazila.destroy');

        Route::get('district', 'Admin\DistrictController@index')->name('district.index');
        Route::get('district/create', 'Admin\DistrictController@create')->name('district.create');
        Route::post('district', 'Admin\DistrictController@store')->name('district.store');
        Route::get('district/{id}/edit', 'Admin\DistrictController@edit')->name('district.edit');
        Route::put('district', 'Admin\DistrictController@update')->name('district.update');
        Route::put('district/{id}', 'Admin\DistrictController@status')->name('district.status');
        Route::delete('/district/{id}/', 'Admin\DistrictController@destroy')->name('district.destroy');

        Route::get('category', 'Admin\CategoryController@index')->name('category.index');
        Route::get('category/create', 'Admin\CategoryController@create')->name('category.create');
        Route::post('category', 'Admin\CategoryController@store')->name('category.store');
        Route::get('category/{id}/edit', 'Admin\CategoryController@edit')->name('category.edit');
        Route::put('category', 'Admin\CategoryController@update')->name('category.update');
        Route::put('category/{id}', 'Admin\CategoryController@status')->name('category.status');
        Route::delete('category/{id}/', 'Admin\CategoryController@destroy')->name('category.destroy');
        Route::get('category/image/{id?}/{size?}/{img?}/', 'Admin\CategoryController@image')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('category.image');

        Route::get('event-calendar', 'Admin\EventCalendarController@index')->name('event_calendar.index');
        Route::get('event-calendar/create', 'Admin\EventCalendarController@create')->name('event_calendar.create');
        Route::post('event-calendar', 'Admin\EventCalendarController@store')->name('event_calendar.store');
        Route::get('event-calendar/{id}/edit', 'Admin\EventCalendarController@edit')->name('event_calendar.edit');
        Route::put('event-calendar', 'Admin\EventCalendarController@update')->name('event_calendar.update');
        Route::put('event-calendar/{id}', 'Admin\EventCalendarController@status')->name('event_calendar.status');
        Route::delete('event-calendar/{id}/', 'Admin\EventCalendarController@destroy')->name('event_calendar.destroy');

        Route::get('edito-draft', 'Admin\EditorDraftController@index')->name('editorDraft.index');
        Route::get('edito-draft/create', 'Admin\EditorDraftController@create')->name('editorDraft.create');
        Route::post('edito-draft', 'Admin\EditorDraftController@store')->name('editorDraft.store');
        Route::get('edito-draft/{id}/edit', 'Admin\EditorDraftController@edit')->name('editorDraft.edit');
        Route::put('edito-draft', 'Admin\EditorDraftController@update')->name('editorDraft.update');
        Route::put('edito-draft/{id}', 'Admin\EditorDraftController@status')->name('editorDraft.status');
        Route::delete('edito-draft/{id}/', 'Admin\EditorDraftController@destroy')->name('editorDraft.destroy');
        Route::get('edito-draft-api', 'Admin\EditorDraftController@getEditor')->name('editorDraft.api');

        Route::get('page', 'Admin\PageController@index')->name('page.index');
        Route::get('page/create', 'Admin\PageController@create')->name('page.create');
        Route::post('page/bulk/copy', 'Admin\PageController@bulkCopy')->name('page.bulk.copy');
        Route::delete('page/bulk', 'Admin\PageController@bulkDestroy')->name('page.bulk.destroy');
        Route::get('page-trash', 'Admin\PageController@trash')->name('page.trash.index');
        Route::post('page-trash/{id}/restore', 'Admin\PageController@restore')->name('page.trash.restore');
        Route::delete('page-trash/{id}', 'Admin\PageController@forceDestroy')->name('page.trash.force-destroy');
        Route::get('content-trash', 'Admin\ContentTrashController@index')->name('content.trash.index');
        Route::post('content-trash/{type}/{id}/restore', 'Admin\ContentTrashController@restore')->name('content.trash.restore');
        Route::delete('content-trash/{type}/{id}', 'Admin\ContentTrashController@forceDestroy')->name('content.trash.force-destroy');
        Route::post('page', 'Admin\PageController@store')->name('page.store');
        Route::get('page/{id}/edit', 'Admin\PageController@edit')->name('page.edit');
        Route::get('page/{id}/view', 'Admin\PageController@view')->name('page.view');
        Route::post('page/{id}/comments/search', 'Admin\PageCommentSearchController@store')->name('page.comments.search');
        Route::post('page/{id}/comments/search/clear', 'Admin\PageCommentSearchController@clear')->name('page.comments.search.clear');
        Route::put('page/comment/status', 'Admin\PageController@statusComment')->name('page.status.comment');
        Route::put('page', 'Admin\PageController@update')->name('page.update');
        Route::put('page/{id}', 'Admin\PageController@status')->name('page.status');
        Route::put('page/{id}/comment', 'Admin\PageController@statusIsComment')->name('page.is-comments');
        Route::delete('page/{id}/', 'Admin\PageController@destroy')->name('page.destroy');
        Route::get('page/thumbnail/{id?}/{size?}/{img?}/', 'Admin\PageController@thumbnail')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('page.thumbnail');

        Route::get('site-settings', 'Admin\SiteSettingsController@index')->name('site.settings.index');
        Route::put('site-settings', 'Admin\SiteSettingsController@update')->name('site.settings.update');
        Route::delete('site-settings/{group}/{key}', 'Admin\SiteSettingsController@destroy')->name('site.settings.destroy');

        Route::get('translations', 'Admin\TranslationCenterController@index')->name('translations.index');
        Route::put('translations', 'Admin\TranslationCenterController@update')->name('translations.update');
        Route::put('translations/language', 'Admin\TranslationCenterController@toggle')->name('translations.toggle');

        Route::get('seo', 'Admin\SeoController@index')->name('seo.index');
        Route::put('seo', 'Admin\SeoController@update')->name('seo.update');
        Route::get('seo/content/{type}/{id}', 'Admin\SeoController@editContent')->name('seo.content.edit');
        Route::put('seo/content/{type}/{id}', 'Admin\SeoController@updateContent')->name('seo.content.update');
        Route::get('seo/bulk', 'Admin\SeoController@bulkIndex')->name('seo.bulk.index');
        Route::put('seo/bulk', 'Admin\SeoController@bulkUpdate')->name('seo.bulk.update');
        Route::get('seo/bulk/export', 'Admin\SeoController@bulkExport')->name('seo.bulk.export');
        Route::get('seo/internal-links', 'Admin\InternalLinkAssistantController@index')->name('seo.internal-links.index');
        Route::get('seo/media-assets', 'Admin\SeoController@mediaIndex')->name('seo.media.index');
        Route::get('seo/performance', 'Admin\SeoPerformanceController@index')->name('seo.performance.index');
        Route::post('seo/performance/refresh', 'Admin\SeoPerformanceController@refresh')->middleware('throttle:3,1')->name('seo.performance.refresh');
        Route::post('seo/review/request', 'Admin\SeoController@requestReview')->name('seo.review.request');
        Route::post('seo/review/resolve', 'Admin\SeoController@resolveReview')->name('seo.review.resolve');
        Route::post('seo/revisions/{revision}/restore', 'Admin\SeoController@restoreRevision')->name('seo.revisions.restore');
        Route::get('seo/redirects', 'Admin\SeoController@redirectsIndex')->name('seo.redirects.index');
        Route::post('seo/redirects', 'Admin\SeoController@storeRedirect')->name('seo.redirects.store');
        Route::delete('seo/redirects/{redirect}', 'Admin\SeoController@destroyRedirect')->name('seo.redirects.destroy');
        Route::get('seo/technical', 'Admin\TechnicalSeoController@index')->name('seo.technical.index');
        Route::post('seo/technical/scan', 'Admin\TechnicalSeoController@scan')->middleware('throttle:2,1')->name('seo.technical.scan');
        Route::post('seo/technical/issues/{issue}/ignore', 'Admin\TechnicalSeoController@ignore')->name('seo.technical.issues.ignore');
        Route::delete('seo/technical/ignore-rules/{rule}', 'Admin\TechnicalSeoController@unignore')->name('seo.technical.ignore-rules.destroy');
        Route::post('seo/technical/not-found/{hit}/redirect', 'Admin\TechnicalSeoController@createRedirect')->name('seo.technical.not-found.redirect');
        Route::post('seo/technical/not-found/{hit}/dismiss', 'Admin\TechnicalSeoController@dismissNotFound')->name('seo.technical.not-found.dismiss');

        // WordPress-style page builder, revisions, and SEO pack.
        Route::get('page-builder/{uuid}', 'Admin\PageBuilderController@edit')->name('page.builder.edit');
        Route::get('page-builder/{uuid}/preview', 'Vue\PageController@preview')->name('page.builder.preview');
        Route::put('page-builder/{uuid}', 'Admin\PageBuilderController@updatePage')->name('page.builder.update');
        Route::put('page-builder/{uuid}/simple-save', 'Admin\PageBuilderController@saveSimple')->name('page.builder.simple.save');
        Route::post('page-builder/{uuid}/media', 'Admin\PageBuilderController@storeMedia')->name('page.builder.media.store');
        Route::post('page-builder/{uuid}/blocks', 'Admin\PageBuilderController@storeBlock')->name('page.builder.block.store');
        Route::put('page-builder/{uuid}/blocks/order', 'Admin\PageBuilderController@reorder')->name('page.builder.block.reorder');
        Route::put('page-builder/{uuid}/blocks/{blockUuid}', 'Admin\PageBuilderController@updateBlock')->name('page.builder.block.update');
        Route::post('page-builder/{uuid}/blocks/{blockUuid}/duplicate', 'Admin\PageBuilderController@duplicateBlock')->name('page.builder.block.duplicate');
        Route::post('page-builder/{uuid}/blocks/{blockUuid}/promote', 'Admin\PageBuilderController@promoteBlock')->name('page.builder.block.promote');
        Route::post('page-builder/{uuid}/reusable-blocks', 'Admin\PageBuilderController@attachReusableBlock')->name('page.builder.reusable.attach');
        Route::post('page-builder/{uuid}/blocks/{blockUuid}/detach', 'Admin\PageBuilderController@detachReusableBlock')->name('page.builder.block.detach');
        Route::delete('page-builder/{uuid}/blocks/{blockUuid}', 'Admin\PageBuilderController@destroyBlock')->name('page.builder.block.destroy');
        Route::post('page-builder/{uuid}/revisions/{revisionUuid}/restore', 'Admin\PageBuilderController@restoreRevision')->name('page.builder.revision.restore');

        Route::get('reusable-blocks', 'Admin\ReusableBlockController@index')->name('reusable-blocks.index');
        Route::put('reusable-blocks/{reusableBlock}', 'Admin\ReusableBlockController@update')->name('reusable-blocks.update');
        Route::delete('reusable-blocks/{reusableBlock}', 'Admin\ReusableBlockController@destroy')->name('reusable-blocks.destroy');
        Route::post('reusable-blocks/{uuid}/restore', 'Admin\ReusableBlockController@restore')->name('reusable-blocks.restore');
        Route::delete('reusable-blocks/{uuid}/force', 'Admin\ReusableBlockController@forceDestroy')->name('reusable-blocks.force-destroy');

        Route::get('media-library', 'Admin\MediaAssetController@index')->name('media.index');
        Route::post('media-library', 'Admin\MediaAssetController@store')->name('media.store');
        Route::post('media-library/bulk', 'Admin\MediaAssetController@bulk')->name('media.bulk');
        Route::put('media-library/{mediaAsset}', 'Admin\MediaAssetController@update')->name('media.update');
        Route::delete('media-library/{mediaAsset}', 'Admin\MediaAssetController@destroy')->name('media.destroy');
        Route::post('media-library/{uuid}/restore', 'Admin\MediaAssetController@restore')->name('media.restore');
        Route::delete('media-library/{uuid}/force', 'Admin\MediaAssetController@forceDestroy')->name('media.force-destroy');

        // Donation Section
        Route::get('donation-type', 'Admin\DonationTypeController@index')->name('donationType.index');
        Route::get('donation-type/create', 'Admin\DonationTypeController@create')->name('donationType.create');
        Route::post('donation-type', 'Admin\DonationTypeController@store')->name('donationType.store');
        Route::post('donation-type/group', 'Admin\DonationCauseGroupController@store')->name('donationType.group.store');
        Route::put('donation-type/group/{donationCauseGroup}', 'Admin\DonationCauseGroupController@update')->name('donationType.group.update');
        Route::put('donation-type/group/{donationCauseGroup}/status', 'Admin\DonationCauseGroupController@status')->name('donationType.group.status');
        Route::delete('donation-type/group/{donationCauseGroup}', 'Admin\DonationCauseGroupController@destroy')->name('donationType.group.destroy');
        Route::get('donation-type/{id}', 'Admin\DonationTypeController@show')->name('donationType.show');
        Route::get('donation-type/{id}/edit', 'Admin\DonationTypeController@edit')->name('donationType.edit');
        Route::put('donation-type', 'Admin\DonationTypeController@update')->name('donationType.update');
        Route::put('donation-type/{id}', 'Admin\DonationTypeController@status')->name('donationType.status');
        Route::delete('donation-type/{id}/', 'Admin\DonationTypeController@destroy')->name('donationType.destroy');

        // Tag Section
        Route::get('tag', 'Admin\TagController@index')->name('tag.index');
        Route::get('tag/create', 'Admin\TagController@create')->name('tag.create');
        Route::post('tag', 'Admin\TagController@store')->name('tag.store');
        Route::get('tag/{id}', 'Admin\TagController@show')->name('tag.show');
        Route::get('tag/{id}/edit', 'Admin\TagController@edit')->name('tag.edit');
        Route::put('tag', 'Admin\TagController@update')->name('tag.update');
        Route::put('tag/{id}', 'Admin\TagController@status')->name('tag.status');
        Route::delete('tag/{id}/', 'Admin\TagController@destroy')->name('tag.destroy');

        // Testimonial Section
        Route::get('testimonial', 'Admin\TestimonialController@index')->name('testimonial.index');
        Route::get('testimonial/create', 'Admin\TestimonialController@create')->name('testimonial.create');
        Route::post('testimonial', 'Admin\TestimonialController@store')->name('testimonial.store');
        Route::get('testimonial/{id}/edit', 'Admin\TestimonialController@edit')->name('testimonial.edit');
        Route::put('testimonial', 'Admin\TestimonialController@update')->name('testimonial.update');
        Route::put('testimonial/{id}', 'Admin\TestimonialController@status')->name('testimonial.status');
        Route::delete('testimonial/{id}/', 'Admin\TestimonialController@destroy')->name('testimonial.destroy');
        Route::get('testimonial/photo/{id?}/{size?}/{img?}/', 'Admin\TestimonialController@photo')->where(['id' => '[A-Za-z0-9._-]+', 'size' => '[A-Za-z0-9._-]+', 'img' => '[A-Za-z0-9._-]+'])->name('testimonial.photo');

        // Subscriber Section
        Route::get('subscriber', 'Admin\SubscriberController@index')->name('subscriber.index');
        Route::post('subscriber/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'subscribers')->name('subscriber.filter');
        Route::post('subscriber/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'subscribers')->name('subscriber.search.clear');
        Route::delete('/subscriber/{id}/', 'Admin\SubscriberController@destroy')->name('subscriber.destroy');
        Route::get('subscriber/download-excel', 'Admin\SubscriberController@excel_download')->name('subscriber-excel-download.index');
        Route::post('subscriber/{subscriber:uuid}/send-email', 'Admin\SubscriberController@sendEmail')->name('subscriber.sendEmail');


        Route::get('comment', 'Admin\CommentController@index')->name('comment.index');
        Route::post('comment/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'comments')->name('comment.search');
        Route::post('comment/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'comments')->name('comment.search.clear');
        Route::delete('/comment/{id}/', 'Admin\CommentController@destroy')->name('comment.destroy');

        Route::get('youtube', 'Admin\YouTubeController@index')->name('youtube.index');
        Route::get('youtube/create', 'Admin\YouTubeController@create')->name('youtube.create');
        Route::post('youtube', 'Admin\YouTubeController@store')->name('youtube.store');
        Route::get('youtube/{id}/edit', 'Admin\YouTubeController@edit')->name('youtube.edit');
        Route::put('youtube', 'Admin\YouTubeController@update')->name('youtube.update');
        Route::put('youtube/{id}', 'Admin\YouTubeController@status')->name('youtube.status');
        Route::delete('youtube/{id}/', 'Admin\YouTubeController@destroy')->name('youtube.destroy');

        Route::get('user/', 'Admin\UserController@index')->name('user.index');
        Route::post('user/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'users')->name('user.search');
        Route::post('user/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'users')->name('user.search.clear');
        Route::post('user/api/search', 'Admin\UserController@seachUserApi')->name('admin.user.search');
        Route::get('user/{id?}', 'Admin\UserController@show')->name('user.show');

        // Report Section
        Route::get('report/youtube-meta/', 'Admin\ReportController@youTubeMeta')->name('report.youtubeMeta');
        Route::post('report/youtube-meta/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'youtube-report')->name('report.youtubeMeta.search');
        Route::post('report/youtube-meta/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'youtube-report')->name('report.youtubeMeta.search.clear');

        //PageMenus Section
        Route::get('page-menu', 'Admin\PageMenuController@index')->name('page.menu.index');
        Route::get('page-menu/create', 'Admin\PageMenuController@create')->name('page.menu.create');
        Route::get('page-menu-trash', 'Admin\PageMenuController@trash')->name('page.menu.trash');
        Route::post('page-menu-trash/{uuid}/restore', 'Admin\PageMenuController@restore')->name('page.menu.restore');
        Route::delete('page-menu-trash/{uuid}', 'Admin\PageMenuController@forceDestroy')->name('page.menu.force-destroy');
        Route::put('page-menu/order', 'Admin\PageMenuController@reorder')->name('page.menu.reorder');
        Route::post('page-menu', 'Admin\PageMenuController@store')->name('page.menu.store');
        Route::put('page-menu/{uuid}/item', 'Admin\PageMenuController@quickUpdate')->name('page.menu.item.update');
        Route::get('page-menu/{id}', 'Admin\PageMenuController@show')->name('page.menu.show');
        Route::get('page-menu/{id}/edit', 'Admin\PageMenuController@edit')->name('page.menu.edit');
        Route::put('page-menu', 'Admin\PageMenuController@update')->name('page.menu.update');
        Route::put('page-menu/{id}', 'Admin\PageMenuController@status')->name('page.menu.status');
        Route::delete('page-menu/{id}/', 'Admin\PageMenuController@destroy')->name('page.menu.destroy');
        Route::get('page-menu/{id?}/slug/{lang}', 'Admin\PageMenuController@showSlug')->name('page.menu.showSlug');
        Route::get('page-menu/{id?}/type/{lang}', 'Admin\PageMenuController@showParent')->name('page.menu.showParent');

        //AuthMenus Section
        Route::get('menu', 'Admin\AuthMenuController@index')->name('menu.index');
        Route::get('menu/create', 'Admin\AuthMenuController@create')->name('menu.create');
        Route::post('menu', 'Admin\AuthMenuController@store')->name('menu.store');
        Route::get('menu/{id}', 'Admin\AuthMenuController@show')->name('menu.show');
        Route::get('menu/{id}/edit', 'Admin\AuthMenuController@edit')->name('menu.edit');
        Route::put('menu', 'Admin\AuthMenuController@update')->name('menu.update');
        Route::put('menu/{id}', 'Admin\AuthMenuController@status')->name('menu.status');
        Route::delete('menu/{id}/', 'Admin\AuthMenuController@destroy')->name('menu.destroy');

        //AuthMenuActions Section
        Route::get('menu-action/create', 'Admin\MenuActionController@create')->name('menu.action.create');
        Route::get('menu-action/{id?}', 'Admin\MenuActionController@index')->name('menu.action.index');
        Route::post('menu-action', 'Admin\MenuActionController@store')->name('menu.action.store');
        Route::get('menu-action/{id}/show', 'Admin\MenuActionController@show')->name('menu.action.show');
        Route::get('menu-action/{id}/edit', 'Admin\MenuActionController@edit')->name('menu.action.edit');
        Route::put('menu-action', 'Admin\MenuActionController@update')->name('menu.action.update');
        Route::put('menu-action/{id}', 'Admin\MenuActionController@status')->name('menu.action.status');
        Route::delete('menu-action/{id}/', 'Admin\MenuActionController@destroy')->name('menu.action.destroy');

        //Roles Section
        Route::get('role', 'Admin\RoleController@index')->name('role.index');
        Route::get('role/create', 'Admin\RoleController@create')->name('role.create');
        Route::post('role', 'Admin\RoleController@store')->name('role.store');
        Route::get('role/{id}', 'Admin\RoleController@show')->name('role.show');
        Route::get('role/{id}/edit', 'Admin\RoleController@edit')->name('role.edit');
        Route::put('role', 'Admin\RoleController@update')->name('role.update');
        Route::put('role/{id}', 'Admin\RoleController@status')->name('role.status');
        Route::get('role/{id}/permission', 'Admin\RoleController@permission')->name('role.permission');
        Route::post('role/permission', 'Admin\RoleController@permissionStore')->name('role.permission.store');
        Route::delete('role/{id}/', 'Admin\RoleController@destroy')->name('role.destroy');

        //Admin Section
        Route::get('admin', 'Admin\AdminController@index')->name('admin.index');
        Route::post('admin/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'admins')->name('admin.search');
        Route::post('admin/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'admins')->name('admin.search.clear');
        Route::get('admin/create', 'Admin\AdminController@create')->name('admin.create');
        Route::post('admin', 'Admin\AdminController@store')->name('admin.store');
        Route::get('admin/{id}', 'Admin\AdminController@show')->name('admin.show');
        Route::get('admin/{id}/edit', 'Admin\AdminController@edit')->name('admin.edit');
        Route::put('admin', 'Admin\AdminController@update')->name('admin.update');
        Route::put('admin/{id}', 'Admin\AdminController@status')->name('admin.status');
        Route::delete('admin/{id}/', 'Admin\AdminController@destroy')->name('admin.destroy');
        Route::get('password', 'Admin\AdminController@passwordAuthEdit')->name('admin.password');
        Route::put('password', 'Admin\AdminController@passwordAuthChange')->name('admin.password.update');
        Route::get('admin/{id}/reset', 'Admin\AdminController@confirmResetPassword')->name('admin.reset');
        Route::post('admin/{id}/reset', 'Admin\AdminController@resetPassword')->name('admin.reset.perform');
        Route::get('/admin/image/{id?}', 'Admin\AdminController@image')->name('admin.image');


        //User Approval
        Route::get('user-approval', 'Admin\UserApprovalController@index')->name('user-approval.index');
        Route::post('user-approval/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'member-approvals')->name('user-approval.search');
        Route::post('user-approval/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'member-approvals')->name('user-approval.search.clear');
        Route::get('user-approval/{id}', 'Admin\UserApprovalController@show')->name('user-approval.show');
        Route::put('user-approval/{id}/approve', 'Admin\UserApprovalController@approve')->name('user-approval.update.approve');
        Route::put('user-approval/{id}/reject', 'Admin\UserApprovalController@reject')->name('user-approval.update.reject');

        //SplashScreen
        Route::get('splash-screen', 'Admin\SplashScreenController@index')->name('splash.screen.index');
        Route::post('splash-screen', 'Admin\SplashScreenController@store')->name('splash.screen.store');

        Route::get('contact-message', 'Admin\ContactMessageController@index')->name('contact-message.index');
        Route::post('contact-message/search', 'Admin\PrivateListingSearchController@store')->defaults('scope', 'contact-messages')->name('contact-message.search');
        Route::post('contact-message/search/clear', 'Admin\PrivateListingSearchController@clear')->defaults('scope', 'contact-messages')->name('contact-message.search.clear');
        Route::put('contact-message/{contactMessage}/workflow', 'Admin\ContactMessageController@updateWorkflow')->name('contact-message.workflow');
        Route::get('contact-message/{id?}', 'Admin\ContactMessageController@show')->name('contact-message.show');

        // Website chat inbox and curated question/answer management.
        Route::get('chat', 'Admin\ChatController@index')->name('chat.index');
        Route::post('chat/search', 'Admin\ChatController@storeSearch')->name('chat.search');
        Route::post('chat/search/clear', 'Admin\ChatController@clearSearch')->name('chat.search.clear');
        Route::get('chat/questions', 'Admin\ChatController@questions')->name('chat.faq.index');
        Route::put('chat/settings/{locale}', 'Admin\ChatController@updateSettings')->name('chat.settings.update');
        Route::post('chat/questions', 'Admin\ChatController@storeFaq')->name('chat.faq.store');
        Route::put('chat/questions/{faq}', 'Admin\ChatController@updateFaq')->name('chat.faq.update');
        Route::delete('chat/questions/{faq}', 'Admin\ChatController@destroyFaq')->name('chat.faq.destroy');
        Route::get('chat/{conversation}', 'Admin\ChatController@show')->name('chat.show');
        Route::post('chat/{conversation}/reply', 'Admin\ChatController@reply')->name('chat.reply');
        Route::put('chat/{conversation}/status', 'Admin\ChatController@updateStatus')->name('chat.status');

        Route::group(['prefix' => 'filemanager', 'middleware' => ['lfm.mutations']], function () {
            \UniSharp\LaravelFilemanager\Lfm::routes();
        });
    });

    Route::middleware(['XSS'])->group(function () {
        Route::get('login', 'Auth\AdminLoginController@showLogin')->name('admin.showLogin');
        Route::post('login', 'Auth\AdminLoginController@login')->middleware('throttle:5,1')->name('admin.login');
        Route::post('logout', 'Auth\AdminLoginController@adminLogout')->name('admin.logout');
    });
});

// Crawler endpoints are deliberately stateless. Keeping session/CSRF/Inertia
// middleware here would attach cookies to otherwise public, cacheable files and
// prevent a shared cache or CDN from treating them as one canonical response.
Route::withoutMiddleware([
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\EnsureActiveApprovedMember::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \App\Http\Middleware\HandleInertiaRequests::class,
    \App\Http\Middleware\SetLocale::class,
    \App\Http\Middleware\TrackSeoNotFound::class,
])->group(function () {
    Route::get('sitemap.xml', 'SeoPublicController@sitemap')->name('seo.sitemap');
    Route::get('sitemap-index.xml', 'SeoPublicController@sitemapIndex')->name('seo.sitemap.index');
    Route::get('sitemap-{locale}.xml', 'SeoPublicController@sitemapLocale')
        ->where('locale', '[a-z]{2}')
        ->name('seo.sitemap.locale');
    Route::get('robots.txt', 'SeoPublicController@robots')->name('seo.robots');
});

Route::middleware(['cors', 'locale', 'XSS', 'seo.redirect', 'seo.route'])->group(function () {
    Route::get('language/{language?}', 'Vue\HomeController@language')->name('frontend.language');
    Route::get('/', 'Vue\HomeController@index')->name('frontend.home');
    Route::get('page/{slug?}', 'Vue\PageController@page')->name('frontend.page');
    Route::get('category/{slug?}', 'Vue\CategoryController@category')->name('frontend.category');
    Route::get('gallery', 'Vue\GalleryController@gallery')->name('frontend.gallery');
    Route::get('about-us', 'Vue\AboutController@about')->name('frontend.about');
    Route::get('contact-us', 'HomeController@contact')->name('frontend.contactUs');
    Route::post('send-sms', 'ContactMessagesController@sendSms')->middleware('throttle:10,1')->name('frontend.send.sms');
    Route::get('subscribe/confirm/{subscriber}', 'HomeController@confirmSubscription')
        ->middleware(['signed', 'throttle:newsletter-confirm'])
        ->whereUuid('subscriber')
        ->name('frontend.subscribe.confirm');
    Route::post('subscribe/{lang?}', 'HomeController@subscribe')
        ->middleware('throttle:newsletter-subscribe')
        ->name('frontend.subscribe');
    Route::get('zakat', 'Vue\ZakatController@zakat')->name('frontend.zakat');

    // Sponsor a Child Section
    Route::get('sponsor-child', 'Vue\SponsorController@index')->name('frontend.sponsor_child');
    Route::post('sponsorship/store', 'Vue\SponsorController@store')->middleware('throttle:10,1')->name('frontend.sponsorship.store');


    Route::match(['get', 'post'], 'donation/payment/success', 'Vue\DonateController@success')
        ->name('frontend.donation.payment.success');
    Route::match(['get', 'post'], 'donation/payment/fail', 'Vue\DonateController@fail')
        ->name('frontend.donation.payment.fail');
    Route::match(['get', 'post'], 'donation/payment/cancel', 'Vue\DonateController@cancel')
        ->name('frontend.donation.payment.cancel');
    Route::post('donate/payment/ipn', 'Vue\DonateController@ipn')->name('frontend.donation.payment.ipn');


    // comment for this project not delete
    // Route::get('recent-post/{slug?}', 'HomeController@recentPost')->name('frontend.recentPost');
    // Route::get('join-us/{slug?}', 'HomeController@join')->name('frontend.join');
    // Route::get('members', 'HomeController@members')->name('frontend.members');
    // Route::get('publication', 'HomeController@notice')->name('frontend.notice');
    Route::get('events', 'Vue\NoticeBoardController@events')->name('frontend.events');
    Route::get('event/{slug?}', 'Vue\NoticeBoardController@event')->name('frontend.event');

    // Public job and free-workshop opportunities. Applicants remain anonymous
    // visitors; only the POST boundary is independently abuse-limited.
    Route::get('careers', 'Vue\OpportunityController@jobs')->name('frontend.jobs.index');
    Route::get('careers/{job}', 'Vue\OpportunityController@job')
        ->where('job', '[\pL\pN_-]+')
        ->name('frontend.jobs.show');
    Route::post('careers/{job}/apply', 'Vue\OpportunityController@apply')
        ->where('job', '[\pL\pN_-]+')
        ->middleware('throttle:public-opportunity-submission')
        ->name('frontend.jobs.apply');

    Route::get('workshops', 'Vue\OpportunityController@workshops')->name('frontend.workshops.index');
    Route::get('workshops/{workshop}', 'Vue\OpportunityController@workshop')
        ->where('workshop', '[\pL\pN_-]+')
        ->name('frontend.workshops.show');
    Route::post('workshops/{workshop}/register', 'Vue\OpportunityController@register')
        ->where('workshop', '[\pL\pN_-]+')
        ->middleware('throttle:public-opportunity-submission')
        ->name('frontend.workshops.register');

    // Project Section
    Route::get('projects/{slug?}', 'Vue\ProjectController@projects')->name('frontend.project');

    // Volunteer Registration Section
    Route::get('volunteer/register', 'Vue\VolunteerRegistrationController@index')->name('frontend.volunteer_registration.index');
    Route::post('volunteer/register', 'Vue\VolunteerRegistrationController@registration')->middleware('throttle:10,1')->name('frontend.volunteer_registration.store');

    // Donate Section
    Route::get('donate', 'Vue\DonateController@index')->name('frontend.donate.index');
    Route::get('donate/checkout-key', 'Vue\DonateController@checkoutKey')
        ->middleware('throttle:30,1')
        ->name('frontend.donate.checkout-key');
    Route::get('donate/{cause}', 'Vue\DonateController@cause')
        ->where('cause', '[A-Za-z0-9-]+')
        ->name('frontend.donate.cause');
    Route::post('donate', 'Vue\DonateController@donate')->middleware('throttle:10,1')->name('frontend.donate.store');

    // Annual Report Section
    Route::get('annual-report', 'Vue\AnnualReportController@index')->name('frontend.annual_report.index');
    Route::get('annual-report/download/{slug?}', 'Vue\AnnualReportController@download')->name('frontend.annual_report.download');
    Route::get('annual-report/{slug}', 'Vue\AnnualReportController@show')->name('frontend.annual_report.show');

    // Github login
    Route::get('login/github', 'Auth\SociaLiteLoginController@redirectToGithub')->name('login.github');
    Route::get('login/github/callback', 'Auth\SociaLiteLoginController@handleGithubCallback')->middleware('throttle:20,1');

    //Auth
    Route::get('login', 'Auth\AuthController@showLogin')->name('showLogin');
    Route::post('login', 'Auth\AuthController@login')->middleware('throttle:5,1')->name('login');

    Route::get('login-2fa', 'Auth\AuthController@showLogin2fa')->name('login2fa');
    Route::post('login-2fa', 'Auth\AuthController@login2fa')->middleware('throttle:5,1')->name('login2fa.perform');

    Route::get('login-2fa-verify', 'Auth\AuthController@showLogin2faVerify')->name('login2fa.verify');
    Route::post('login-2fa-verify', 'Auth\AuthController@verify2fa')->middleware('throttle:6,1')->name('login2fa.verify.perform');

    Route::get('register', 'Auth\AuthController@showRegister')->name('register.form');
    Route::post('register', 'Auth\AuthController@register')->middleware('throttle:5,1')->name('register');

    //Home page search
    Route::get('search', 'Vue\SearchController@index')->name('search');

    Route::middleware(['auth:web'])->group(function () {
        Route::post('change-password', 'Auth\AuthController@changePassword')->name('change.password');
    });
    Route::post('logout', 'Auth\AuthController@logout')->name('front.logout');

    Route::get('notice/download/{filename?}/', 'Admin\NoticeBoardController@fileDownloadPath')
        ->where('filename', '[A-Za-z0-9][A-Za-z0-9._-]*')
        ->name('notice.download');
    Route::get('notice/pdfViewer/{filename?}/', 'Admin\NoticeBoardController@pdfViewPath')
        ->where('filename', '[A-Za-z0-9][A-Za-z0-9._-]*')
        ->name('notice.pdfViewer');

    // Public chat uses the web session so guests can only reopen their own
    // conversation. Mutations are CSRF protected and independently throttled.
    Route::get('chat/bootstrap', 'ChatController@bootstrap')->middleware('throttle:chat-read')->name('chat.bootstrap');
    Route::post('chat/faq-click', 'ChatController@recordFaqClick')->middleware('throttle:chat-faq-click')->name('chat.faqs.click');
    Route::post('chat/conversations', 'ChatController@storeConversation')->middleware('throttle:chat-write')->name('chat.conversations.store');
    Route::get('chat/conversations/{conversation}', 'ChatController@show')->middleware('throttle:chat-read')->name('chat.conversations.show');
    Route::post('chat/conversations/{conversation}/messages', 'ChatController@storeMessage')->middleware('throttle:chat-write')->name('chat.messages.store');

    Route::fallback(function () {
        return response()->view('errors.404', [], 404);
    });
});
