# Recruitment and workshop dashboard routes

This is a developer-facing inventory of the dashboard routes integrated in
`routes/web.php`. The declarations in `routes/web.php` are authoritative; this
document records their ordering, binding, and private-search contract.

All routes remain inside the existing admin `web`, `auth:admin`, and
`permission` middleware group. Static paths such as `export`, `search`, and
`bulk` are defined before the UUID-bound record path.

```php
use App\Http\Controllers\Admin\JobApplicationController;
use App\Http\Controllers\Admin\WorkshopRegistrationController;

Route::prefix('recruitment/applications')
    ->name('recruitment.applications.')
    ->controller(JobApplicationController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/search', 'search')->name('search');
        Route::post('/search/clear', 'clearSearch')->name('search.clear');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::get('/export', 'export')->name('export');
        Route::get('/{application}', 'show')->name('show');
        Route::patch('/{application}/workflow', 'workflow')->name('workflow');
        Route::patch('/{application}/assignment', 'assign')->name('assign');
        Route::put('/{application}/score', 'score')->name('score');
        Route::post('/{application}/notes', 'addNote')->name('notes.store');
        Route::get('/{application}/documents/{document}', 'download')->name('download');
        Route::post('/{application}/anonymize', 'anonymize')->name('anonymize');
        Route::delete('/{application}/delete', 'delete')->name('delete');
        Route::delete('/{application}', 'destroy')->name('destroy');
    });

Route::prefix('workshop/registrations')
    ->name('workshop.registrations.')
    ->controller(WorkshopRegistrationController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/search', 'search')->name('search');
        Route::post('/search/clear', 'clearSearch')->name('search.clear');
        Route::post('/bulk', 'bulk')->name('bulk');
        Route::get('/export', 'export')->name('export');
        Route::get('/{registration}', 'show')->name('show');
        Route::patch('/{registration}/workflow', 'workflow')->name('workflow');
        Route::patch('/{registration}/assignment', 'assign')->name('assign');
        Route::post('/{registration}/notes', 'addNote')->name('notes.store');
        Route::get('/{registration}/documents/{document}', 'download')->name('download');
        Route::post('/{registration}/anonymize', 'anonymize')->name('anonymize');
        Route::delete('/{registration}/delete', 'delete')->name('delete');
        Route::delete('/{registration}', 'destroy')->name('destroy');
    });
```

`{application}`, `{registration}`, and `{document}` use implicit UUID binding.
The `*.delete` routes provide the explicit owner-facing typed-confirmation flow;
the `*.destroy` routes use the same controller-level owner and confirmation
checks for compatibility with the registry's resource vocabulary.

Safe index/export query parameters are `listing` (listing UUID), `status`,
`assigned_to`, `from`, `to`, `sort`, `direction`, `per_page`, and `columns[]`.
Applicant search text must only be posted to the search action and resolved from
the authenticated administrator's server-side session. It must never become an
index/export query parameter.
