<?php

namespace Tests\Unit;

use App\Support\AdminUi;
use PHPUnit\Framework\TestCase;

class AdminUiTest extends TestCase
{
    public function test_font_awesome_classes_are_allowlisted_and_malicious_icons_fall_back(): void
    {
        $this->assertTrue(AdminUi::isValidIconClass('fa fa-edit fa-lg'));
        $this->assertTrue(AdminUi::isValidIconClass('fa-solid fa-shield-halved'));
        $this->assertFalse(AdminUi::isValidIconClass('fa-edit"></i><img src=x onerror=alert(1)>'));
        $this->assertSame(
            'fa-cogs',
            AdminUi::iconClass('fa-edit"></i><img src=x onerror=alert(1)>')
        );
    }

    public function test_admin_labels_are_escaped_before_raw_menu_html_is_rendered(): void
    {
        $label = AdminUi::label('<img src=x onerror="alert(1)"> Settings & tools');

        $this->assertSame(
            '&lt;img src=x onerror=&quot;alert(1)&quot;&gt; Settings &amp; tools',
            $label
        );
        $this->assertStringNotContainsString('<img', $label);
    }
}
