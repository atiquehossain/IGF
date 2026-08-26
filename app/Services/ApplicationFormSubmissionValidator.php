<?php

namespace App\Services;

use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Support\ApplicationIdentity;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ApplicationFormSubmissionValidator
{
    /**
     * Validate a public payload against the exact published schema that was rendered.
     * Hidden answers are intentionally discarded so clients cannot smuggle values for
     * conditionally unavailable questions into staff exports or decisions.
     *
     * @param array<string, mixed> $input
     */
    public function validate(
        ApplicationFormVersion $version,
        array $input,
        string $locale = 'en',
        bool $requireCv = true,
    ): ValidatedApplicationSubmission {
        if (!in_array($version->state, [
            ApplicationFormVersion::STATE_PUBLISHED,
            ApplicationFormVersion::STATE_RETIRED,
        ], true)) {
            throw ValidationException::withMessages([
                'submission' => $this->message($locale, 'This form version is not available.', 'এই ফর্ম সংস্করণটি উপলভ্য নয়।'),
            ]);
        }

        $locale = in_array($locale, ['en', 'bn'], true) ? $locale : 'en';
        $version->loadMissing([
            'fields.translations',
            'fields.options',
            'fields.visibilityConditions.sourceField',
        ]);
        $responses = $input['responses'] ?? [];
        if (!is_array($responses) || count($responses) > ApplicationFormSchemaService::MAX_FIELDS) {
            throw ValidationException::withMessages([
                'responses' => $this->message($locale, 'The submitted answers are not valid.', 'জমা দেওয়া উত্তরগুলো বৈধ নয়।'),
            ]);
        }

        $knownResponseKeys = $version->fields
            ->whereNull('system_key')
            ->pluck('field_key')
            ->map(fn (mixed $key): string => (string) $key);
        $unknown = collect(array_keys($responses))->map(fn (mixed $key): string => (string) $key)->diff($knownResponseKeys);
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'responses' => $this->message($locale, 'The form contains an unknown or expired question.', 'ফর্মটিতে অজানা বা মেয়াদোত্তীর্ণ প্রশ্ন রয়েছে।'),
            ]);
        }

        $errors = [];
        $valuesByFieldId = [];
        $publicValues = [];
        $answers = [];
        $files = [];
        $identity = ['name' => null, 'email' => null, 'phone' => null];
        $cv = null;

        foreach ($version->fields as $field) {
            $publicKey = $this->publicKey($field);
            $errorKey = $field->system_key ? $publicKey : "responses.{$publicKey}";
            $raw = $field->system_key
                ? ($input[$publicKey] ?? null)
                : ($responses[$field->field_key] ?? null);

            if (!$this->isVisible($field, $valuesByFieldId)) {
                $valuesByFieldId[$field->id] = null;
                continue;
            }

            $label = $this->fieldLabel($field, $locale);
            try {
                $value = $this->normalizeValue($field, $raw, $label, $locale, $requireCv);
            } catch (InvalidArgumentException $exception) {
                $errors[$errorKey] = $exception->getMessage();
                $valuesByFieldId[$field->id] = null;
                continue;
            }

            $valuesByFieldId[$field->id] = $value;
            $publicValues[$publicKey] = $value;
            if ($field->system_key === ApplicationFormField::SYSTEM_FULL_NAME) {
                $identity['name'] = $value;
            } elseif ($field->system_key === ApplicationFormField::SYSTEM_EMAIL) {
                $identity['email'] = $value;
            } elseif ($field->system_key === ApplicationFormField::SYSTEM_PHONE) {
                $identity['phone'] = $value;
            } elseif ($field->system_key === ApplicationFormField::SYSTEM_CV) {
                $cv = $value instanceof UploadedFile ? $value : null;
                if ($cv) {
                    $files[(int) $field->id] = $cv;
                }
            } elseif ($field->type === ApplicationFormField::TYPE_FILE && $value instanceof UploadedFile) {
                $files[(int) $field->id] = $value;
            } elseif ($value !== null) {
                $answers[] = $this->answerAttributes($field, $value);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if (!is_string($identity['name']) || !is_string($identity['email'])) {
            throw ValidationException::withMessages([
                'submission' => $this->message($locale, 'The required identity fields are unavailable.', 'প্রয়োজনীয় পরিচয় ক্ষেত্রগুলো উপলভ্য নয়।'),
            ]);
        }
        if ($requireCv && !$cv instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'cv' => $this->message($locale, 'CV is required.', 'জীবনবৃত্তান্ত আবশ্যক।'),
            ]);
        }

        return new ValidatedApplicationSubmission(
            $identity['name'],
            $identity['email'],
            is_string($identity['phone']) && $identity['phone'] !== '' ? $identity['phone'] : null,
            $answers,
            $publicValues,
            $cv,
            $files,
        );
    }

    private function normalizeValue(
        ApplicationFormField $field,
        mixed $raw,
        string $label,
        string $locale,
        bool $requireCv,
    ): mixed {
        $required = (bool) $field->is_required
            && !($field->system_key === ApplicationFormField::SYSTEM_CV && !$requireCv);
        if ($this->blank($raw)) {
            if ($required) {
                throw new InvalidArgumentException($this->message(
                    $locale,
                    "{$label} is required.",
                    "{$label} আবশ্যক।",
                ));
            }

            return null;
        }

        $validation = is_array($field->validation) ? $field->validation : [];

        return match ($field->type) {
            ApplicationFormField::TYPE_SHORT_TEXT => $this->text($raw, $label, $locale, $validation, 1000, false),
            ApplicationFormField::TYPE_LONG_TEXT => $this->text($raw, $label, $locale, $validation, 20_000, true),
            ApplicationFormField::TYPE_EMAIL => $this->email($raw, $label, $locale),
            ApplicationFormField::TYPE_PHONE => $this->phone($raw, $label, $locale, $validation),
            ApplicationFormField::TYPE_NUMBER => $this->number($raw, $label, $locale, $validation),
            ApplicationFormField::TYPE_DATE => $this->date($raw, $label, $locale),
            ApplicationFormField::TYPE_DROPDOWN,
            ApplicationFormField::TYPE_RADIO => $this->singleChoice($field, $raw, $label, $locale),
            ApplicationFormField::TYPE_CHECKBOXES => $this->multipleChoice($field, $raw, $label, $locale, $validation),
            ApplicationFormField::TYPE_YES_NO => $this->yesNo($raw, $label, $locale),
            ApplicationFormField::TYPE_FILE => $this->file($field, $raw, $label, $locale),
            default => throw new InvalidArgumentException($this->message($locale, 'This question type is not supported.', 'এই প্রশ্নের ধরন সমর্থিত নয়।')),
        };
    }

    /** @param array<string, mixed> $validation */
    private function text(mixed $raw, string $label, string $locale, array $validation, int $hardMaximum, bool $multiline): string
    {
        if (!is_scalar($raw)) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        $value = trim((string) $raw);
        $forbidden = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        if (preg_match($forbidden, $value) === 1) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        $minimum = max(0, (int) ($validation['min_length'] ?? 0));
        $maximum = min($hardMaximum, max(0, (int) ($validation['max_length'] ?? $hardMaximum)));
        $length = mb_strlen($value);
        if ($length < $minimum || $length > $maximum) {
            throw new InvalidArgumentException($this->message(
                $locale,
                "{$label} must contain between {$minimum} and {$maximum} characters.",
                "{$label}-এ {$minimum} থেকে {$maximum} অক্ষর থাকতে হবে।",
            ));
        }

        return $value;
    }

    private function email(mixed $raw, string $label, string $locale): string
    {
        if (!is_string($raw)) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        try {
            return ApplicationIdentity::normalizeEmail($raw);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException($this->message($locale, 'Enter a valid email address.', 'একটি বৈধ ইমেইল ঠিকানা লিখুন।'));
        }
    }

    /** @param array<string, mixed> $validation */
    private function phone(mixed $raw, string $label, string $locale, array $validation): string
    {
        $value = $this->text($raw, $label, $locale, $validation, 40, false);
        if (preg_match('/\A[0-9+() .-]{5,40}\z/D', $value) !== 1) {
            throw new InvalidArgumentException($this->message($locale, 'Enter a valid phone number.', 'একটি বৈধ ফোন নম্বর লিখুন।'));
        }

        return $value;
    }

    /** @param array<string, mixed> $validation */
    private function number(mixed $raw, string $label, string $locale, array $validation): float|int|string
    {
        if ((!is_string($raw) && !is_int($raw) && !is_float($raw))
            || !is_numeric($raw)
            || !is_finite((float) $raw)
            || abs((float) $raw) > 1_000_000_000) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        $value = (float) $raw;
        if ((isset($validation['min']) && $value < (float) $validation['min'])
            || (isset($validation['max']) && $value > (float) $validation['max'])) {
            throw new InvalidArgumentException($this->message($locale, "{$label} is outside the allowed range.", "{$label} অনুমোদিত সীমার বাইরে।"));
        }

        return $value;
    }

    private function date(mixed $raw, string $label, string $locale): string
    {
        if (!is_string($raw) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $raw) !== 1) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }

        return $raw;
    }

    private function singleChoice(ApplicationFormField $field, mixed $raw, string $label, string $locale): string
    {
        if (!is_scalar($raw)) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        $value = (string) $raw;
        if (!$this->optionKeys($field)->containsStrict($value)) {
            throw new InvalidArgumentException($this->message($locale, "Choose a valid option for {$label}.", "{$label}-এর জন্য একটি বৈধ অপশন বেছে নিন।"));
        }

        return $value;
    }

    /** @param array<string, mixed> $validation
     *  @return list<string>
     */
    private function multipleChoice(ApplicationFormField $field, mixed $raw, string $label, string $locale, array $validation): array
    {
        if (!is_array($raw) || count($raw) > ApplicationFormSchemaService::MAX_OPTIONS) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        $values = array_values(array_unique(array_map(function (mixed $value) use ($label, $locale): string {
            if (!is_scalar($value)) {
                throw new InvalidArgumentException($this->invalid($locale, $label));
            }

            return (string) $value;
        }, $raw)));
        $allowed = $this->optionKeys($field);
        if (collect($values)->contains(fn (string $value): bool => !$allowed->containsStrict($value))) {
            throw new InvalidArgumentException($this->message($locale, "Choose valid options for {$label}.", "{$label}-এর জন্য বৈধ অপশন বেছে নিন।"));
        }
        $minimum = max(0, (int) ($validation['min'] ?? 0));
        $maximum = min($allowed->count(), max(0, (int) ($validation['max'] ?? $allowed->count())));
        if (count($values) < $minimum || count($values) > $maximum) {
            throw new InvalidArgumentException($this->message($locale, "Choose an allowed number of options for {$label}.", "{$label}-এর জন্য অনুমোদিত সংখ্যক অপশন বেছে নিন।"));
        }

        return $values;
    }

    private function yesNo(mixed $raw, string $label, string $locale): bool
    {
        return match ($raw) {
            true, 1, '1', 'yes', 'true' => true,
            false, 0, '0', 'no', 'false' => false,
            default => throw new InvalidArgumentException($this->invalid($locale, $label)),
        };
    }

    private function file(ApplicationFormField $field, mixed $raw, string $label, string $locale): UploadedFile
    {
        if (!$raw instanceof UploadedFile) {
            throw new InvalidArgumentException($this->invalid($locale, $label));
        }
        if (!$raw->isValid()
            || strtolower((string) $raw->getClientOriginalExtension()) !== 'pdf'
            || (int) $raw->getSize() > PrivateApplicationDocumentService::MAX_BYTES) {
            throw new InvalidArgumentException($this->message($locale, 'Upload a PDF no larger than 5 MB.', '৫ এমবি-এর বেশি নয় এমন একটি পিডিএফ আপলোড করুন।'));
        }

        return $raw;
    }

    private function isVisible(ApplicationFormField $field, array $valuesByFieldId): bool
    {
        if ($field->visibilityConditions->isEmpty()) {
            return true;
        }

        return $field->visibilityConditions
            ->groupBy('condition_group')
            ->contains(function (Collection $conditions) use ($valuesByFieldId): bool {
                $result = null;
                foreach ($conditions->sortBy('position') as $condition) {
                    $matched = $this->conditionMatches(
                        $valuesByFieldId[$condition->source_field_id] ?? null,
                        $condition->operator,
                        data_get($condition->comparison_value, 'value'),
                    );
                    $result = $result === null
                        ? $matched
                        : ($condition->boolean_connector === 'or' ? $result || $matched : $result && $matched);
                }

                return $result ?? true;
            });
    }

    private function conditionMatches(mixed $actual, string $operator, mixed $expected): bool
    {
        $blank = $this->blank($actual);
        $string = fn (mixed $value): string => match (true) {
            $value === true => 'yes',
            $value === false => 'no',
            is_scalar($value) => mb_strtolower(trim((string) $value)),
            default => '',
        };
        $equals = fn (): bool => is_array($actual)
            ? collect($actual)->map($string)->containsStrict($string($expected))
            : $string($actual) === $string($expected);
        $contains = fn (): bool => is_array($actual)
            ? collect($actual)->map($string)->containsStrict($string($expected))
            : str_contains($string($actual), $string($expected));

        return match ($operator) {
            'equals' => $equals(),
            'not_equals' => !$equals(),
            'contains' => $contains(),
            'not_contains' => !$contains(),
            'is_empty' => $blank,
            'is_not_empty' => !$blank,
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            default => false,
        };
    }

    /** @return array{application_form_field_id:int,value_text:?string,value_number:float|int|string|null,value_date:?string,value_boolean:?bool,value_json:?array} */
    private function answerAttributes(ApplicationFormField $field, mixed $value): array
    {
        return [
            'application_form_field_id' => (int) $field->id,
            'value_text' => in_array($field->type, [
                ApplicationFormField::TYPE_SHORT_TEXT,
                ApplicationFormField::TYPE_LONG_TEXT,
                ApplicationFormField::TYPE_EMAIL,
                ApplicationFormField::TYPE_PHONE,
                ApplicationFormField::TYPE_DROPDOWN,
                ApplicationFormField::TYPE_RADIO,
            ], true) ? (string) $value : null,
            'value_number' => $field->type === ApplicationFormField::TYPE_NUMBER ? $value : null,
            'value_date' => $field->type === ApplicationFormField::TYPE_DATE ? (string) $value : null,
            'value_boolean' => $field->type === ApplicationFormField::TYPE_YES_NO ? (bool) $value : null,
            'value_json' => $field->type === ApplicationFormField::TYPE_CHECKBOXES ? $value : null,
        ];
    }

    /** @return Collection<int, string> */
    private function optionKeys(ApplicationFormField $field): Collection
    {
        return $field->options->pluck('option_key')->map(fn (mixed $key): string => (string) $key)->values();
    }

    private function fieldLabel(ApplicationFormField $field, string $locale): string
    {
        return (string) ($field->translations->firstWhere('locale', $locale)?->label
            ?: $field->translations->firstWhere('locale', 'en')?->label
            ?: 'This field');
    }

    private function publicKey(ApplicationFormField $field): string
    {
        return match ($field->system_key) {
            ApplicationFormField::SYSTEM_FULL_NAME => 'applicant_name',
            ApplicationFormField::SYSTEM_EMAIL => 'email',
            ApplicationFormField::SYSTEM_PHONE => 'phone',
            ApplicationFormField::SYSTEM_CV => 'cv',
            default => $field->field_key,
        };
    }

    private function blank(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }

    private function invalid(string $locale, string $label): string
    {
        return $this->message($locale, "Enter a valid value for {$label}.", "{$label}-এর জন্য একটি বৈধ মান লিখুন।");
    }

    private function message(string $locale, string $english, string $bangla): string
    {
        return $locale === 'bn' ? $bangla : $english;
    }
}
