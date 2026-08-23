<?php

namespace App\Http\Controllers\Admin;

use App\Helper\Translation;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Services\ContentSanitizer;
use App\Services\DonationPaymentMethodService;
use App\Services\SiteSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteSettingsController extends Controller
{
    public function __construct(
        private SiteSettingService $settings,
        private ContentSanitizer $sanitizer,
        private DonationPaymentMethodService $paymentMethods
    ) {
    }

    public function index(Request $request)
    {
        $locales = collect(Translation::languageList());
        $locale = (string) $request->query('locale', app()->getLocale());

        abort_unless($locales->pluck('id')->contains($locale), 404);

        return view('admin.site-settings.index', [
            'title' => 'Website Customizer',
            'schema' => config('site-settings.groups', []),
            'values' => $this->settings->values($locale),
            'locales' => $locales,
            'locale' => $locale,
            'paymentProviderStatuses' => $this->paymentMethods->operationalStatuses(),
            'mediaAssets' => MediaAsset::query()
                ->where('mime_type', 'like', 'image/%')
                ->latest()
                ->limit(120)
                ->get(['uuid', 'disk', 'path', 'original_name', 'mime_type', 'alt_text']),
        ]);
    }

    public function update(Request $request)
    {
        $schema = config('site-settings.groups', []);
        $allowedLocales = collect(Translation::languageList())->pluck('id')->all();
        $locale = (string) $request->input('locale', app()->getLocale());

        abort_unless(in_array($locale, $allowedLocales, true), 422);

        $rules = ['locale' => ['required', 'string', 'max:10']];
        foreach ($schema as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                if (($field['type'] ?? null) === 'faq_list') {
                    $rules["settings.{$groupKey}.{$key}"] = ['nullable', 'array', 'max:50'];
                    $rules["settings.{$groupKey}.{$key}.*.question"] = ['required', 'string', 'max:500'];
                    $rules["settings.{$groupKey}.{$key}.*.answer"] = ['required', 'string', 'max:5000'];
                    $rules["settings.{$groupKey}.{$key}.*.is_active"] = ['sometimes', 'boolean'];

                    continue;
                }

                $rules["settings.{$groupKey}.{$key}"] = $this->rulesFor($field);
            }
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request): void {
            $goldPrice = data_get($request->all(), 'settings.zakat_calculator.gold_price_per_gram');
            $silverPrice = data_get($request->all(), 'settings.zakat_calculator.silver_price_per_gram');
            $priceDate = data_get($request->all(), 'settings.zakat_calculator.nisab_price_updated_at');

            if (!is_numeric($goldPrice) || (float) $goldPrice <= 0) {
                $validator->errors()->add(
                    'settings.zakat_calculator.gold_price_per_gram',
                    'Enter a positive gold reference price per gram approved for your Zakat method.'
                );
            }

            if (!is_numeric($silverPrice) || (float) $silverPrice <= 0) {
                $validator->errors()->add(
                    'settings.zakat_calculator.silver_price_per_gram',
                    'Enter a positive silver reference price per gram approved for your Zakat method.'
                );
            }

            if (is_string($priceDate) && $priceDate !== '') {
                try {
                    if (Carbon::createFromFormat('Y-m-d', $priceDate)->startOfDay()->isFuture()) {
                        $validator->errors()->add(
                            'settings.zakat_calculator.nisab_price_updated_at',
                            'The price-check date cannot be in the future.'
                        );
                    }
                } catch (\Throwable) {
                    // The field-level date rule provides the validation message.
                }
            }

            $enabled = fn (string $key): bool => filter_var(
                data_get($request->all(), "settings.donation_page.{$key}", false),
                FILTER_VALIDATE_BOOLEAN
            );

            $hasUsableMethod = collect($this->paymentMethods->publicKeys())
                ->contains(fn (string $key): bool => $enabled('enable_' . $key)
                    && $this->paymentMethods->isOperationallyReady($key));

            if (!$hasUsableMethod) {
                $validator->errors()->add(
                    'settings.donation_page.enable_bkash',
                    'Keep at least one payment method enabled that is marked Ready in Payment provider status.'
                );
            }
        });

        try {
            $validated = $validator->validate();
        } catch (ValidationException $exception) {
            $parameters = $locale === app()->getLocale() ? [] : ['locale' => $locale];

            throw $exception->redirectTo(route('site.settings.index', $parameters));
        }

        DB::transaction(function () use ($schema, $validated, $locale) {
            foreach ($schema as $groupKey => $group) {
                foreach ($group['fields'] as $key => $field) {
                    $value = data_get($validated, "settings.{$groupKey}.{$key}");
                    $value = $this->normalizeValue($value, $field['type']);
                    $settingLocale = ($field['localized'] ?? false) ? $locale : '*';

                    $setting = SiteSetting::withTrashed()->firstOrNew([
                        'group' => $groupKey,
                        'key' => $key,
                        'locale' => $settingLocale,
                    ]);

                    if ($setting->trashed()) {
                        $setting->restore();
                    }

                    $setting->fill([
                        'value' => $this->serializedValue($value, $field['type']),
                        'type' => $field['type'] === 'faq_list'
                            ? 'json'
                            : (in_array($field['type'], ['boolean', 'integer', 'float'], true) ? $field['type'] : 'text'),
                        'is_public' => (bool) ($field['public'] ?? false),
                        'created_by' => $setting->exists ? $setting->created_by : auth('admin')->id(),
                        'updated_by' => auth('admin')->id(),
                    ])->save();
                }
            }
        });

        return redirect()->route('site.settings.index', ['locale' => $locale])
            ->with(['message' => 'Website changes saved. Refresh the preview to see them.', 'alert-type' => 'success']);
    }

    public function destroy(string $group, string $key, Request $request)
    {
        $field = config("site-settings.groups.{$group}.fields.{$key}");
        abort_unless(is_array($field), 404);

        $locale = ($field['localized'] ?? false)
            ? (string) $request->input('locale', app()->getLocale())
            : '*';

        SiteSetting::where('group', $group)
            ->where('key', $key)
            ->where('locale', $locale)
            ->delete();

        return back()->with(['message' => 'Setting reset to its default.', 'alert-type' => 'success']);
    }

    private function rulesFor(array $field): array
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'boolean' => ['required', 'boolean'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'url_or_path' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && $this->sanitizer->sanitizeUrl($value) === '') {
                    $fail('The ' . $attribute . ' field must be a safe URL or site path.');
                }
            }],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'integer' => ['required', 'integer', 'min:' . (int) ($field['min'] ?? 1), 'max:' . (int) ($field['max'] ?? 10000000)],
            'float' => ['required', 'numeric', 'min:' . (float) ($field['min'] ?? 0.01), 'max:' . (float) ($field['max'] ?? 10000000)],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'select' => ['required', 'string', Rule::in(array_keys($field['options'] ?? []))],
            'textarea' => ['nullable', 'string', 'max:5000'],
            default => ['nullable', 'string', 'max:255'],
        };
    }

    private function normalizeValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'faq_list' => $this->normalizeFaqList($value),
            'date' => is_string($value) ? Carbon::createFromFormat('Y-m-d', $value)->toDateString() : $value,
            'url', 'url_or_path' => $this->sanitizer->sanitizeUrl($value),
            default => is_string($value) ? trim(strip_tags($value)) : $value,
        };
    }

    private function normalizeFaqList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_array($item))
            ->take(50)
            ->map(fn (array $item): array => [
                'question' => trim(strip_tags((string) ($item['question'] ?? ''))),
                'answer' => trim(strip_tags((string) ($item['answer'] ?? ''))),
                'is_active' => filter_var($item['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
    }

    private function serializedValue(mixed $value, string $type): string
    {
        if ($type === 'faq_list') {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
    }
}
