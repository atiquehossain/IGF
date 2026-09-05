<?php

namespace App\Http\Controllers\Admin;

use App\Helper\Translation;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Services\ContentSanitizer;
use App\Services\DonationPaymentMethodService;
use App\Services\PublicFormFieldLayoutService;
use App\Services\SiteSettingVersionService;
use App\Services\SiteSettingService;
use App\Support\AdminUi;
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
        private DonationPaymentMethodService $paymentMethods,
        private SiteSettingVersionService $versions,
        private PublicFormFieldLayoutService $formLayouts,
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
            'globalSettingsVersion' => $this->versions->currentForLocale($locale),
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

        $rules = [
            'locale' => ['required', 'string', 'max:10'],
            'global_settings_version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
        foreach ($schema as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                if (($field['type'] ?? null) === 'faq_list') {
                    $rules["settings.{$groupKey}.{$key}"] = ['nullable', 'array', 'max:50'];
                    $rules["settings.{$groupKey}.{$key}.*.question"] = ['required', 'string', 'max:500'];
                    $rules["settings.{$groupKey}.{$key}.*.answer"] = ['required', 'string', 'max:5000'];
                    $rules["settings.{$groupKey}.{$key}.*.is_active"] = ['sometimes', 'boolean'];

                    continue;
                }

                if (($field['type'] ?? null) === 'form_field_layout') {
                    $allowedKeys = array_keys(is_array($field['allowed_fields'] ?? null) ? $field['allowed_fields'] : []);
                    $rules["settings.{$groupKey}.{$key}"] = ['required', 'array', 'size:' . count($allowedKeys)];
                    $rules["settings.{$groupKey}.{$key}.*.key"] = ['required', 'string', Rule::in($allowedKeys), 'distinct'];
                    $rules["settings.{$groupKey}.{$key}.*.enabled"] = ['required', 'boolean'];
                    $rules["settings.{$groupKey}.{$key}.*.required"] = ['required', 'boolean'];

                    continue;
                }

                $rules["settings.{$groupKey}.{$key}"] = $this->rulesFor($field);
            }
        }

        $currentSettings = $this->settings->values($locale);
        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $currentSettings): void {
            $this->validateThemeContrast(
                $validator,
                (array) data_get($request->all(), 'settings.theme', [])
            );

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

            // Provider credentials are an operations concern and checkout
            // already fails closed when they are unavailable. Do not prevent
            // a non-technical editor from saving unrelated branding, contact,
            // content, or layout changes merely because deployment has not
            // connected the payment account yet. Re-check readiness only when
            // an editor actually changes one of the public payment offers.
            $paymentOfferChanged = collect($this->paymentMethods->publicKeys())
                ->contains(function (string $key) use ($enabled, $currentSettings): bool {
                    $current = filter_var(
                        data_get($currentSettings, "donation_page.enable_{$key}", false),
                        FILTER_VALIDATE_BOOLEAN
                    );

                    return $enabled('enable_' . $key) !== $current;
                });

            if (!$paymentOfferChanged) {
                return;
            }

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

        try {
            $nextVersion = DB::transaction(function () use ($schema, $validated, $locale): string {
                $currentVersion = $this->versions->currentForLocale($locale, true);
                if (!hash_equals($currentVersion, $validated['global_settings_version'])) {
                    throw ValidationException::withMessages([
                        'global_settings_version' => 'Website-wide settings changed after this form was opened. Reload the customizer, review the latest values, and save again.',
                    ]);
                }

                foreach ($schema as $groupKey => $group) {
                    foreach ($group['fields'] as $key => $field) {
                        $value = data_get($validated, "settings.{$groupKey}.{$key}");
                        $value = $this->normalizeValue($value, $field);
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
                            'type' => in_array($field['type'], ['faq_list', 'form_field_layout'], true)
                                ? 'json'
                                : (in_array($field['type'], ['boolean', 'integer', 'float'], true) ? $field['type'] : 'text'),
                            'is_public' => (bool) ($field['public'] ?? false),
                            'created_by' => $setting->exists ? $setting->created_by : auth('admin')->id(),
                            'updated_by' => auth('admin')->id(),
                        ])->save();
                    }
                }

                return $this->versions->currentForLocale($locale, true);
            });
        } catch (ValidationException $exception) {
            $parameters = $locale === app()->getLocale() ? [] : ['locale' => $locale];

            throw $exception->redirectTo(route('site.settings.index', $parameters));
        }

        $message = AdminUi::text('customizer.saved');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'global_settings_version' => $nextVersion,
            ]);
        }

        return redirect()->route('site.settings.index', ['locale' => $locale])
            ->with(['message' => $message, 'alert-type' => 'success']);
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

        $rules = match ($type) {
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

        $requiredPlaceholders = array_values(array_filter(
            (array) ($field['required_placeholders'] ?? []),
            fn ($placeholder): bool => is_string($placeholder) && $placeholder !== ''
        ));
        if ($requiredPlaceholders === []) {
            return $rules;
        }

        $rules = array_values(array_filter($rules, fn ($rule): bool => $rule !== 'nullable'));
        array_unshift($rules, 'required');
        $rules[] = function (string $attribute, mixed $value, $fail) use ($requiredPlaceholders): void {
            if (!is_string($value)) {
                return;
            }

            foreach ($requiredPlaceholders as $placeholder) {
                if (!str_contains($value, $placeholder)) {
                    $fail("The {$attribute} field must keep the {$placeholder} placeholder.");
                }
            }
        };

        return $rules;
    }

    private function normalizeValue(mixed $value, array $field): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'faq_list' => $this->normalizeFaqList($value),
            'form_field_layout' => $this->formLayouts->normalize($field, $value),
            'date' => is_string($value) ? Carbon::createFromFormat('Y-m-d', $value)->toDateString() : $value,
            'url', 'url_or_path' => $this->sanitizer->sanitizeUrl($value),
            default => is_string($value) ? trim(strip_tags($value)) : $value,
        };
    }

    private function validateThemeContrast($validator, array $theme): void
    {
        $ink = (string) ($theme['ink_color'] ?? '');
        $surface = (string) ($theme['surface_color'] ?? '');
        $ratio = $this->contrastRatio($ink, $surface);

        if ($ratio !== null && $ratio < 4.5) {
            $message = 'Choose text and surface colors with at least 4.5:1 contrast so public content remains readable.';
            $validator->errors()->add('settings.theme.ink_color', $message);
            $validator->errors()->add('settings.theme.surface_color', $message);
        }
    }

    private function contrastRatio(string $foreground, string $background): ?float
    {
        $foregroundLuminance = $this->relativeLuminance($foreground);
        $backgroundLuminance = $this->relativeLuminance($background);
        if ($foregroundLuminance === null || $backgroundLuminance === null) {
            return null;
        }

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): ?float
    {
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $matches) !== 1) {
            return null;
        }

        $channels = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($matches[1], 2)
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
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
        if (in_array($type, ['faq_list', 'form_field_layout'], true)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
    }
}
