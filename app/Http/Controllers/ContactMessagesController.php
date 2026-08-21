<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\SiteSettingService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactMessagesController extends Controller
{

    public function sendSms(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:5000',
        ]);

        try {
            ContactMessage::create([
                'first_name' => $request->first_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'message' => $request->message
            ]);

            // The page displays its localized, admin-managed success message.
            return back();
        } catch (Exception $e) {
            Log::error('Contact message persistence failed.', ['exception_class' => $e::class]);
            $message = (string) data_get(
                app(SiteSettingService::class)->values(app()->getLocale(), true),
                'contact_page.error_message',
                'We could not send your message. Please try again.'
            );

            throw ValidationException::withMessages(['submission' => $message]);
        }
    }
}
