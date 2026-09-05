<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\TransactionalEmailTemplate;
use App\Services\AdminAuditService;
use App\Services\TransactionalEmailDesignService;
use App\Services\TransactionalEmailTemplateService;
use App\Support\AdminUi;
use App\Support\TransactionalEmailTemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionalEmailTemplateController extends Controller
{
    public function __construct(
        private readonly TransactionalEmailTemplateService $templates,
        private readonly TransactionalEmailDesignService $emailDesign,
        private readonly AdminAuditService $audit,
    ) {
    }

    public function index(Request $request, Permission $permissions): View
    {
        $definitions = [];
        foreach (array_keys(TransactionalEmailTemplateCatalog::definitions()) as $key) {
            $definition = $this->localizedDefinition($key);
            $definition['variants'] = [];
            foreach (TransactionalEmailTemplateCatalog::LOCALES as $locale) {
                $definition['variants'][$locale] = $this->templates->editorContent($key, $locale);
            }
            $definitions[$key] = $definition;
        }

        $admin = $request->user('admin');

        return view('admin.transactional-email-templates.index', [
            'title' => AdminUi::text('email_templates.title'),
            'definitions' => $definitions,
            'locales' => TransactionalEmailTemplateCatalog::LOCALES,
            'canEditTemplates' => $permissions->allows($admin, 'transactional-mail.edit'),
            'canResetTemplates' => $permissions->allows($admin, 'transactional-mail.destroy'),
            'canCustomizeAppearance' => $permissions->allows($admin, 'site.settings.index'),
        ]);
    }

    public function show(
        Request $request,
        Permission $permissions,
        string $templateKey,
        string $locale
    ): View {
        $this->assertSupported($templateKey, $locale);
        $admin = $request->user('admin');

        return view('admin.transactional-email-templates.edit', [
            'title' => AdminUi::text('email_templates.edit_page_title'),
            'templateKey' => $templateKey,
            'locale' => $locale,
            'definition' => $this->localizedDefinition($templateKey),
            'content' => $this->templates->structuredEditorContent($templateKey, $locale),
            'usesButton' => TransactionalEmailTemplateCatalog::usesButton($templateKey),
            'emailDesign' => $this->emailDesign->forLocale($locale),
            'canEditTemplate' => $permissions->allows($admin, 'transactional-mail.edit'),
            'canResetTemplate' => $permissions->allows($admin, 'transactional-mail.destroy'),
            'canCustomizeAppearance' => $permissions->allows($admin, 'site.settings.index'),
        ]);
    }

    public function update(Request $request, string $templateKey, string $locale): RedirectResponse
    {
        $this->assertSupported($templateKey, $locale);
        if (!$request->exists('heading') && $request->exists('html_body')) {
            // Compatibility for already-deployed integrations. The browser
            // editor never exposes these raw fields, and the same strict
            // sanitizer/placeholder contract still applies.
            $existing = $this->templates->structuredEditorContent($templateKey, $locale);
            if (!$existing['is_custom'] || !$existing['is_legacy']) {
                throw ValidationException::withMessages([
                    'html_body' => AdminUi::text(
                        'email_templates.validation.raw_compatibility_only'
                    ),
                ]);
            }
            $validated = $request->validate(
                [
                    'subject' => ['required', 'string', 'max:200'],
                    'html_body' => ['required', 'string', 'max:30000'],
                    'text_body' => ['required', 'string', 'max:20000'],
                ],
                [
                    'subject.required' => AdminUi::text('email_templates.validation.subject_required'),
                    'subject.string' => AdminUi::text('email_templates.validation.subject_string'),
                    'subject.max' => AdminUi::text('email_templates.validation.subject_max'),
                    'html_body.required' => AdminUi::text('email_templates.validation.html_required'),
                    'html_body.string' => AdminUi::text('email_templates.validation.html_string'),
                    'html_body.max' => AdminUi::text('email_templates.validation.html_max'),
                    'text_body.required' => AdminUi::text('email_templates.validation.text_required'),
                    'text_body.string' => AdminUi::text('email_templates.validation.text_string'),
                    'text_body.max' => AdminUi::text('email_templates.validation.text_max'),
                ]
            );
            $safe = $this->templates->sanitizeForStorage(
                $templateKey,
                $locale,
                $validated['subject'],
                $validated['html_body'],
                $validated['text_body']
            );
        } else {
            $validated = $request->validate(
                $this->templates->structuredValidationRules($templateKey, $locale),
                $this->templates->structuredValidationMessages($templateKey, $locale)
            );
            $safe = $this->templates->sanitizeStructuredForStorage(
                $templateKey,
                $locale,
                $validated
            );
        }
        $admin = $request->user('admin');

        DB::transaction(function () use ($admin, $locale, $safe, $templateKey): void {
            $template = TransactionalEmailTemplate::query()->firstOrNew([
                'template_key' => $templateKey,
                'locale' => $locale,
            ]);
            if (!$template->exists) {
                $template->created_by_admin_id = $admin?->getKey();
            }
            $template->fill($safe);
            $template->updated_by_admin_id = $admin?->getKey();
            $template->save();

            $this->audit->record(
                $admin,
                'transactional_email_template.saved',
                $template,
                changes: [
                    'template_key' => $templateKey,
                    'locale' => $locale,
                    'content_sha256' => hash('sha256', implode("\n", $safe)),
                ]
            );
        });

        return redirect()
            ->route('transactional-mail.show', [$templateKey, $locale])
            ->with('message', AdminUi::text('email_templates.messages.saved'));
    }

    public function destroy(Request $request, string $templateKey, string $locale): RedirectResponse
    {
        $this->assertSupported($templateKey, $locale);
        $admin = $request->user('admin');

        DB::transaction(function () use ($admin, $locale, $templateKey): void {
            $template = TransactionalEmailTemplate::query()
                ->where('template_key', $templateKey)
                ->where('locale', $locale)
                ->first();
            if (!$template) {
                return;
            }

            $this->audit->record(
                $admin,
                'transactional_email_template.default_restored',
                $template,
                changes: ['template_key' => $templateKey, 'locale' => $locale]
            );
            $template->delete();
        });

        return redirect()
            ->route('transactional-mail.show', [$templateKey, $locale])
            ->with('message', AdminUi::text('email_templates.messages.restored'));
    }

    /** @return array<string, mixed> */
    private function localizedDefinition(string $templateKey): array
    {
        $definition = TransactionalEmailTemplateCatalog::definition($templateKey);
        $definition['label'] = AdminUi::text("email_templates.templates.{$templateKey}.label");
        $definition['description'] = AdminUi::text("email_templates.templates.{$templateKey}.description");
        foreach (array_keys($definition['placeholders'] ?? []) as $placeholder) {
            $definition['placeholders'][$placeholder] = AdminUi::text(
                "email_templates.placeholders.{$placeholder}"
            );
        }

        return $definition;
    }

    private function assertSupported(string $templateKey, string $locale): void
    {
        abort_unless(TransactionalEmailTemplateCatalog::supports($templateKey, $locale), 404);
    }
}
