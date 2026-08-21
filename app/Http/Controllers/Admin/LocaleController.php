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

        session()->put('locale', $language);
        app()->setLocale($language);

        return back();
    }
}
