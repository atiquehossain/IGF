<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeedbackRenderingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_admin_credentials_use_plain_text_error_feedback(): void
    {
        $message = 'The supplied credentials are invalid.';

        $this->post(route('admin.login'), [
            'username' => 'missing-admin',
            'password' => 'not-the-password',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHas('message', $message);

        $this->get(route('admin.showLogin'))
            ->assertOk()
            ->assertSee('<div class="alert alert-danger" role="alert">' . $message . '</div>', false)
            ->assertDontSee('&lt;span', false)
            ->assertDontSee("<span class='text-danger'>", false);
    }

    public function test_shared_admin_toasts_serialize_and_escape_feedback(): void
    {
        $footer = file_get_contents(resource_path('views/admin/layouts/footer.blade.php'));
        $scripts = file_get_contents(resource_path('views/admin/layouts/scripts.blade.php'));

        $this->assertStringContainsString("@json(Session::get('alert-type', 'info'))", $footer);
        $this->assertStringContainsString("@json(Session::get('message'))", $footer);
        $this->assertStringContainsString('toastrMsg(adminFlashType, adminFlashMessage);', $footer);
        $this->assertStringNotContainsString('toastr.info("{{', $footer);
        $this->assertStringNotContainsString('toastr.error("{{', $footer);

        $this->assertStringContainsString('"escapeHtml": true', $scripts);
        $this->assertStringContainsString("['info', 'warning', 'success', 'error'].includes(type)", $scripts);
        $this->assertStringContainsString("toastr[toastType](String(msg ?? ''));", $scripts);
    }

    public function test_shared_admin_flash_serialization_keeps_hostile_markup_inside_the_string(): void
    {
        $payload = '</script><img src=x onerror=alert(1)>';
        session()->put([
            'message' => $payload,
            'alert-type' => 'error',
        ]);

        $html = view('admin.layouts.footer')->render();

        $this->assertStringNotContainsString($payload, $html);
        $this->assertStringContainsString('\\u003C\\/script\\u003E\\u003Cimg', $html);
        $this->assertStringContainsString('toastrMsg(adminFlashType, adminFlashMessage);', $html);
    }

    public function test_subscriber_email_feedback_never_displays_a_raw_response_body(): void
    {
        $view = file_get_contents(resource_path('views/admin/subscriber/index.blade.php'));

        $this->assertStringContainsString("typeof xhr.responseJSON?.message === 'string'", $view);
        $this->assertStringContainsString("let errorMessage = 'Failed to send email.';", $view);
        $this->assertStringNotContainsString('xhr.responseText', $view);
    }

    public function test_seo_pages_use_the_global_toast_once_and_failed_scans_are_alerts(): void
    {
        foreach (['index', 'content', 'bulk', 'redirects', 'performance', 'technical'] as $viewName) {
            $view = file_get_contents(resource_path("views/admin/seo/{$viewName}.blade.php"));
            $this->assertStringNotContainsString("session('message')", $view, "SEO feedback is duplicated in {$viewName}.");
        }

        $technical = file_get_contents(resource_path('views/admin/seo/technical.blade.php'));
        $this->assertStringContainsString(
            'role="{{ $latestRun->status === \'failed\' ? \'alert\' : \'status\' }}"',
            $technical
        );
    }
}
