<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use App\Mail\VolunteerRegistrationNotification;
use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;
use App\Models\Volunteer;
use App\Models\VolunteerCause;
use App\Services\SiteSettingService;
use App\Services\TranslationCenterService;
use App\Support\BangladeshLocationLabels;
use App\Support\VolunteerApplicationOptions;
use App\Support\VolunteerConsentEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
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
                'causes' => $causes,
                'options' => VolunteerApplicationOptions::all($locale),
                'locations' => $this->locationOptions($locale),
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
            'sex' => ['required', 'string', Rule::in(VolunteerApplicationOptions::values('sex'))],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where('status', 1),
            ],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->where('division_id', $request->integer('division_id'))),
            ],
            'upazila_id' => [
                'required',
                'integer',
                Rule::exists('upazilas', 'id')->where(fn ($query) => $query
                    ->where('status', 1)
                    ->where('district_id', $request->integer('district_id'))),
            ],
            'occupation' => ['required', 'string', Rule::in(VolunteerApplicationOptions::values('occupations'))],
            'occupation_other' => [
                'nullable',
                'string',
                'max:255',
                'required_if:occupation,other',
                'prohibited_unless:occupation,other',
            ],
            'education_level' => ['required', 'string', Rule::in(VolunteerApplicationOptions::values('education_levels'))],
            'blood_group' => ['required', 'string', Rule::in(VolunteerApplicationOptions::values('blood_groups'))],
            'emergency_response_training' => ['nullable', 'boolean'],
            'skill' => ['required', 'string', Rule::in(VolunteerApplicationOptions::values('skills'))],
            'skill_other' => [
                'nullable',
                'string',
                'max:255',
                'required_if:skill,other',
                'prohibited_unless:skill,other',
            ],
            'consent' => ['required', 'accepted'],
        ]);

        try {
            $locale = app()->getLocale();
            $volunteerSettings = (array) data_get(
                app(SiteSettingService::class)->values($locale, true),
                'volunteer_page',
                []
            );
            $consentEvidence = VolunteerConsentEvidence::fromSettings($volunteerSettings, $locale);
            $volunteer = Volunteer::create([
                'name' => $validated['name'],
                'institution' => $validated['institution'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'cause_id' => $validated['cause_id'],
                'sex' => $validated['sex'],
                'date_of_birth' => $validated['date_of_birth'],
                'division_id' => $validated['division_id'],
                'district_id' => $validated['district_id'],
                'upazila_id' => $validated['upazila_id'],
                'occupation' => $validated['occupation'],
                'occupation_other' => $validated['occupation_other'] ?? null,
                'education_level' => $validated['education_level'],
                'blood_group' => $validated['blood_group'],
                'emergency_response_training' => $validated['emergency_response_training'] ?? null,
                'skill' => $validated['skill'],
                'skill_other' => $validated['skill_other'] ?? null,
                'consent_version' => VolunteerApplicationOptions::CONSENT_VERSION,
                'consent_locale' => $consentEvidence['locale'],
                'consent_text_hash' => $consentEvidence['hash'],
                'consent_text_snapshot' => $consentEvidence['snapshot'],
                'consented_at' => now(),
                'status' => 1,
            ]);

            $this->sendEmail($volunteer);

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

    public function sendEmail(Volunteer $volunteer): void
    {
        try {
            $toEmail = Config::get('mail.from.address');
            $reference = sprintf('VOL-%06d', $volunteer->getKey());
            $reviewUrl = route('volunteer.index') . '#volunteer-' . $volunteer->getKey();

            Mail::to($toEmail)->send(new VolunteerRegistrationNotification($reference, $reviewUrl));

            Log::info('Volunteer notification dispatched.');
        } catch (Throwable $e) {
            Log::error('Volunteer notification failed.', [
                'exception_class' => $e::class,
            ]);
        }
    }

    /**
     * @return array{
     *     divisions: list<array{id:int,name:string,label:string}>,
     *     districts: list<array{id:int,name:string,label:string,parent_id:int}>,
     *     upazilas: list<array{id:int,name:string,label:string,parent_id:int}>
     * }
     */
    private function locationOptions(string $locale): array
    {
        $divisionRecords = Division::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
        $divisionNames = $divisionRecords->pluck('name', 'id');
        $divisions = $divisionRecords
            ->map(fn (Division $division): array => [
                'id' => (int) $division->id,
                'name' => (string) $division->name,
                'label' => BangladeshLocationLabels::division((string) $division->name, $locale),
            ])
            ->values()
            ->all();

        $districtRecords = District::query()
            ->where('status', 1)
            ->whereHas('division', fn ($query) => $query->where('status', 1))
            ->orderBy('name')
            ->get(['id', 'name', 'division_id']);
        $districtsById = $districtRecords->keyBy('id');
        $districts = $districtRecords
            ->map(function (District $district) use ($divisionNames, $locale): array {
                $divisionName = (string) $divisionNames->get($district->division_id, '');

                return [
                    'id' => (int) $district->id,
                    'name' => (string) $district->name,
                    'label' => BangladeshLocationLabels::district($divisionName, (string) $district->name, $locale),
                    'parent_id' => (int) $district->division_id,
                ];
            })
            ->values()
            ->all();

        $upazilas = Upazila::query()
            ->where('status', 1)
            ->whereHas('district', fn ($query) => $query
                ->where('status', 1)
                ->whereHas('division', fn ($division) => $division->where('status', 1)))
            ->orderBy('name')
            ->get(['id', 'name', 'district_id'])
            ->map(function (Upazila $upazila) use ($districtsById, $divisionNames, $locale): array {
                $district = $districtsById->get($upazila->district_id);
                $districtName = (string) ($district?->name ?? '');
                $divisionName = (string) $divisionNames->get($district?->division_id, '');

                return [
                    'id' => (int) $upazila->id,
                    'name' => (string) $upazila->name,
                    'label' => BangladeshLocationLabels::upazila(
                        $divisionName,
                        $districtName,
                        (string) $upazila->name,
                        $locale
                    ),
                    'parent_id' => (int) $upazila->district_id,
                ];
            })
            ->values()
            ->all();

        return compact('divisions', 'districts', 'upazilas');
    }
}
