<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LocalizationManager;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function language(LocalizationManager $localization, string $language = 'en'): RedirectResponse
    {
        $allowed = $localization->editorLocales()->pluck('id')->all();
        abort_unless(in_array($language, $allowed, true), 404);

        // Keep the administration language independent from the visitor-site
        // language. Opening a Bangla public preview must never switch the
        // client's dashboard away from its English default.
        session()->put('admin_locale', $language);
        app()->setLocale($language);

        return back();
    }
}
