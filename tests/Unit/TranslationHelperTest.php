<?php

namespace Tests\Unit;

use App\Helper\Translation;
use Tests\TestCase;

class TranslationHelperTest extends TestCase
{
    public function test_enabled_admin_localization_lists_every_configured_editor_locale(): void
    {
        config()->set('app.localization', true);

        $languages = collect(Translation::languageList());

        $this->assertSame(['en', 'bn'], $languages->pluck('id')->all());
        $this->assertSame(['English', 'বাংলা'], $languages->pluck('name')->all());
        $this->assertSame('image/flags/bn.svg', $languages->firstWhere('id', 'bn')->assets);
        $this->assertFileExists(public_path('image/flags/bn.svg'));
    }

    public function test_disabled_admin_localization_keeps_the_english_only_fallback(): void
    {
        config()->set('app.localization', false);

        $languages = collect(Translation::languageList());

        $this->assertSame(['en'], $languages->pluck('id')->all());
    }
}
