<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Mail\TransactionalEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Volunteer;
use App\Models\VolunteerCause;
use App\Services\PublicSystemPageMetaService;
use App\Services\PublicFormFieldLayoutService;
use App\Services\SiteSettingService;
use App\Services\TransactionalEmailTemplateService;
use App\Services\TranslationCenterService;
use App\Support\TransactionalEmailTemplateCatalog;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VolunteerRegistrationController extends Controller
{
    public function __construct(
        private TransactionalEmailTemplateService $emailTemplates,
        private PublicSystemPageMetaService $systemMeta,
        private SiteSettingService $siteSettings,
        private PublicFormFieldLayoutService $formLayouts,
    ) {
    }

    /**
     * Show the volunteer registration form.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $translations = app(TranslationCenterService::class);
        $causes = VolunteerCause::select('id', 'name')->where('status', 1)->get()
            ->each(function (VolunteerCause $cause) use ($locale, $translations): void {
                $cause->setAttribute('name', $translations->localizedContentValue(
                    'volunteer_opportunity',
                    (string) $cause->id,
                    'name',
                    (string) $cause->name,
                    $locale
                ));
            });

        $pageMeta = $this->systemMeta->resolve(
            $request,
            'volunteer_page.eyebrow',
            'volunteer_page.introduction',
            [
                'title' => 'Volunteer with Ignite',
                'meta_title' => 'Volunteer with Ignite',
                'description' => 'Share your time and skills with Ignite Global Foundation and support community-led programs across Bangladesh.',
            ],
        );

        $response = [
            'status' => true,
            'title' => $pageMeta['title'],
            'meta_tag' => $pageMeta['meta_tag'],
            'data' => [
                "causes" => $causes,
            ],
        ];
        return Inertia::render('volunteer-registration')->with($response);
    }

    public function registration(Request $request)
    {
        $settings = $this->siteSettings->values(app()->getLocale(), true);
        $layout = (array) data_get($settings, 'volunteer_page.form_fields', []);
        $institution = $this->formLayouts->state($layout, 'institution');
        $phone = $this->formLayouts->state($layout, 'phone');
        $address = $this->formLayouts->state($layout, 'address');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'institution' => $institution['enabled']
                ? [$institution['required'] ? 'required' : 'nullable', 'string', 'max:255']
                : ['exclude'],
            'email' => 'required|email|max:50|unique:volunteers,email',
            'phone' => $phone['enabled']
                ? [$phone['required'] ? 'required' : 'nullable', 'string', 'max:20']
                : ['exclude'],
            'address' => $address['enabled']
                ? [$address['required'] ? 'required' : 'nullable', 'string', 'max:255']
                : ['exclude'],
            'cause_id' => ['required', 'integer', Rule::exists('volunteer_causes', 'id')->where('status', 1)],
        ]);

        try {
            $volunteer = Volunteer::create([
                'name' => $validated['name'],
                'institution' => $institution['enabled'] ? ($validated['institution'] ?? null) : null,
                'email' => $validated['email'],
                'phone' => $phone['enabled'] ? ($validated['phone'] ?? null) : null,
                'address' => $address['enabled'] ? ($validated['address'] ?? null) : null,
                'cause_id' => $validated['cause_id'],
                'status' => 1,
            ]);

            $this->sendEmail($volunteer->toArray());

            // The page owns its localized, admin-managed success message.
            return back();
        } catch (Throwable $e) {
            Log::error('Volunteer registration persistence failed.', [
                'exception_class' => $e::class,
            ]);
            $message = (string) data_get(
                $settings,
                'volunteer_page.error_message',
                'We could not send your registration. Please try again.'
            );

            throw ValidationException::withMessages(['registration' => $message]);
        }
    }

    public function sendEmail(array $data): void
    {
        try {
            $toEmail = $this->adminNotificationAddress();
            $interest = VolunteerCause::query()->whereKey($data['cause_id'] ?? null)->value('name');
            $rendered = $this->emailTemplates->render(
                TransactionalEmailTemplateCatalog::VOLUNTEER_ADMIN_NOTIFICATION,
                (string) config('transactional-mail.admin_locale', 'en'),
                [
                    'volunteer_name' => $data['name'] ?? '',
                    'institution' => $data['institution'] ?? '',
                    'volunteer_email' => $data['email'] ?? '',
                    'volunteer_phone' => $data['phone'] ?? '',
                    'volunteer_address' => $data['address'] ?? '',
                    'interest_name' => $interest ?: ('Opportunity #'.($data['cause_id'] ?? 'unknown')),
                    'registration_reference' => 'VOL-'.($data['id'] ?? 'NEW'),
                ]
            );

            Mail::to($toEmail)->send(new TransactionalEmail($rendered));

            Log::info('Volunteer notification dispatched.');
        } catch (Throwable $e) {
            Log::error('Volunteer notification failed.', [
                'exception_class' => $e::class,
            ]);
        }
    }

    private function adminNotificationAddress(): string
    {
        $address = trim((string) config('transactional-mail.admin_to'));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The transactional admin recipient is not configured.');
        }

        return $address;
    }
}
