<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\PublicFormFieldLayoutService;
use App\Services\SiteSettingService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactMessagesController extends Controller
{
    public function __construct(
        private SiteSettingService $settings,
        private PublicFormFieldLayoutService $formLayouts,
    ) {
    }

    public function sendSms(Request $request)
    {
        $settings = $this->settings->values(app()->getLocale(), true);
        $phone = $this->formLayouts->state(
            (array) data_get($settings, 'contact_page.form_fields', []),
            'phone'
        );
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => $phone['enabled']
                ? [$phone['required'] ? 'required' : 'nullable', 'string', 'max:20']
                : ['exclude'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            ContactMessage::create([
                'first_name' => $validated['first_name'],
                'email' => $validated['email'],
                'phone' => $phone['enabled'] ? ($validated['phone'] ?? null) : null,
                'message' => $validated['message'],
            ]);

            // The page displays its localized, admin-managed success message.
            return back();
        } catch (Exception $e) {
            Log::error('Contact message persistence failed.', ['exception_class' => $e::class]);
            $message = (string) data_get(
                $settings,
                'contact_page.error_message',
                'We could not send your message. Please try again.'
            );

            throw ValidationException::withMessages(['submission' => $message]);
        }
    }
}
