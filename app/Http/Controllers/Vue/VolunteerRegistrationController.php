<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Volunteer;
use App\Models\VolunteerCause;
use App\Services\SiteSettingService;
use App\Services\TranslationCenterService;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class VolunteerRegistrationController extends Controller
{
    /**
     * Show the volunteer registration form.
     */
    public function index()
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

        $title = 'Volunteer with Ignite';
        $meta_tag = [
            'meta_keyword' => 'volunteer Bangladesh, nonprofit volunteering, Ignite Global Foundation',
            'meta_title' => 'Volunteer with Ignite | Ignite Global Foundation',
            'meta_description' => 'Share your time and skills with Ignite Global Foundation and support community-led programs across Bangladesh.',
        ];

        $response = [
            'status' => true,
            'title' => $title,
            'meta_tag' => $meta_tag,
            'data' => [
                "causes" => $causes,
            ],
        ];
        return Inertia::render('volunteer-registration')->with($response);
    }

    public function registration(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'institution' => 'required|string|max:255',
            'email' => 'required|email|max:50|unique:volunteers,email',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'cause_id' => ['required', 'integer', Rule::exists('volunteer_causes', 'id')->where('status', 1)],
        ]);

        try {
            $volunteer = Volunteer::create([
                'name' => $validated['name'],
                'institution' => $validated['institution'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
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
                app(SiteSettingService::class)->values(app()->getLocale(), true),
                'volunteer_page.error_message',
                'We could not send your registration. Please try again.'
            );

            throw ValidationException::withMessages(['registration' => $message]);
        }
    }

    public function sendEmail(array $data): void
    {
        try {
            $toEmail = Config::get('mail.from.address');
            $subject = 'New Volunteer Registration';

            // You can include volunteer info in the email if you want
            $message = <<<EOT
                A new volunteer has registered on Ignite Global Foundation.

                Volunteer Details:
                ------------------------------------------------------------
                Name: {$data['name']}
                Institution: {$data['institution']}
                Email: {$data['email']}
                Phone: {$data['phone']}
                Address: {$data['address']}
                Interested In: {$data['cause_id']}
                ------------------------------------------------------------

                This notification was sent automatically by the system.
                EOT;

            Mail::raw($message, function ($mail) use ($toEmail, $subject) {
                $mail->to($toEmail)
                    ->subject($subject);
            });

            Log::info('Volunteer notification dispatched.');
        } catch (Throwable $e) {
            Log::error('Volunteer notification failed.', [
                'exception_class' => $e::class,
            ]);
        }
    }
}
