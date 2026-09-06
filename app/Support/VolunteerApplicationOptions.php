<?php

namespace App\Support;

final class VolunteerApplicationOptions
{
    public const CONSENT_VERSION = 'volunteer-registration-v1';

    /**
     * Stable option values with visitor-facing labels for every public locale.
     *
     * @var array<string, list<array{value: string|bool, labels: array{en: string, bn: string}}>>
     */
    private const OPTIONS = [
        'sex' => [
            ['value' => 'female', 'labels' => ['en' => 'Female', 'bn' => 'নারী']],
            ['value' => 'male', 'labels' => ['en' => 'Male', 'bn' => 'পুরুষ']],
            ['value' => 'prefer_not_to_say', 'labels' => ['en' => 'Prefer Not To Say', 'bn' => 'বলতে অনিচ্ছুক']],
        ],
        'occupations' => [
            ['value' => 'student', 'labels' => ['en' => 'Student', 'bn' => 'শিক্ষার্থী']],
            ['value' => 'business', 'labels' => ['en' => 'Business', 'bn' => 'ব্যবসা']],
            ['value' => 'service', 'labels' => ['en' => 'Service', 'bn' => 'চাকরি']],
            ['value' => 'freelancer', 'labels' => ['en' => 'Freelancer', 'bn' => 'ফ্রিল্যান্সার']],
            ['value' => 'dropout', 'labels' => ['en' => 'Dropout', 'bn' => 'শিক্ষাজীবন অসম্পূর্ণ']],
            ['value' => 'housewife', 'labels' => ['en' => 'Housewife', 'bn' => 'গৃহিণী']],
            ['value' => 'unemployed', 'labels' => ['en' => 'Unemployed', 'bn' => 'বেকার']],
            ['value' => 'other', 'labels' => ['en' => 'Other', 'bn' => 'অন্যান্য']],
        ],
        'education_levels' => [
            ['value' => 'primary_school', 'labels' => ['en' => 'Primary School (Grade 1-5)', 'bn' => 'প্রাথমিক বিদ্যালয় (১ম–৫ম শ্রেণি)']],
            ['value' => 'high_school', 'labels' => ['en' => 'High School (Grade 6-10)', 'bn' => 'মাধ্যমিক বিদ্যালয় (৬ষ্ঠ–১০ম শ্রেণি)']],
            ['value' => 'secondary_equivalent', 'labels' => ['en' => 'Secondary School / O Levels /Dakhil Equivalent', 'bn' => 'মাধ্যমিক / ও লেভেল / দাখিল সমমান']],
            ['value' => 'higher_secondary_equivalent', 'labels' => ['en' => 'Higher Secondary / A Level /Alim /Equivalent', 'bn' => 'উচ্চ মাধ্যমিক / এ লেভেল / আলিম / সমমান']],
            ['value' => 'diploma_equivalent', 'labels' => ['en' => 'Diploma /Equivalent', 'bn' => 'ডিপ্লোমা / সমমান']],
            ['value' => 'bachelor_equivalent', 'labels' => ['en' => 'Bachelor /Hons /Equivalent', 'bn' => 'স্নাতক / অনার্স / সমমান']],
            ['value' => 'masters_equivalent', 'labels' => ['en' => 'Masters / Post Graduation /Equivalent', 'bn' => 'স্নাতকোত্তর / পোস্ট গ্র্যাজুয়েশন / সমমান']],
        ],
        'blood_groups' => [
            ['value' => 'A+', 'labels' => ['en' => 'A Positive (A+)', 'bn' => 'এ পজিটিভ (A+)']],
            ['value' => 'A-', 'labels' => ['en' => 'A Negative (A-)', 'bn' => 'এ নেগেটিভ (A-)']],
            ['value' => 'B+', 'labels' => ['en' => 'B Positive (B+)', 'bn' => 'বি পজিটিভ (B+)']],
            ['value' => 'B-', 'labels' => ['en' => 'B Negative (B-)', 'bn' => 'বি নেগেটিভ (B-)']],
            ['value' => 'AB+', 'labels' => ['en' => 'AB Positive (AB+)', 'bn' => 'এবি পজিটিভ (AB+)']],
            ['value' => 'AB-', 'labels' => ['en' => 'AB Negative (AB-)', 'bn' => 'এবি নেগেটিভ (AB-)']],
            ['value' => 'O+', 'labels' => ['en' => 'O Positive (O+)', 'bn' => 'ও পজিটিভ (O+)']],
            ['value' => 'O-', 'labels' => ['en' => 'O Negative (O-)', 'bn' => 'ও নেগেটিভ (O-)']],
        ],
        'emergency_response_training' => [
            ['value' => true, 'labels' => ['en' => 'Yes', 'bn' => 'হ্যাঁ']],
            ['value' => false, 'labels' => ['en' => 'No', 'bn' => 'না']],
        ],
        'skills' => [
            ['value' => 'photography', 'labels' => ['en' => 'Photography', 'bn' => 'ফটোগ্রাফি']],
            ['value' => 'painting', 'labels' => ['en' => 'Painting', 'bn' => 'চিত্রাঙ্কন']],
            ['value' => 'calligraphy', 'labels' => ['en' => 'Calligraphy', 'bn' => 'ক্যালিগ্রাফি']],
            ['value' => 'singing', 'labels' => ['en' => 'Singing', 'bn' => 'গান']],
            ['value' => 'debate', 'labels' => ['en' => 'Debate', 'bn' => 'বিতর্ক']],
            ['value' => 'creative_writing', 'labels' => ['en' => 'Creative Writing', 'bn' => 'সৃজনশীল লেখা']],
            ['value' => 'graphic_designing', 'labels' => ['en' => 'Graphic Designing', 'bn' => 'গ্রাফিক ডিজাইন']],
            ['value' => 'video_editing', 'labels' => ['en' => 'Video Editing', 'bn' => 'ভিডিও সম্পাদনা']],
            ['value' => 'acting', 'labels' => ['en' => 'Acting', 'bn' => 'অভিনয়']],
            ['value' => 'other', 'labels' => ['en' => 'Other', 'bn' => 'অন্যান্য']],
        ],
    ];

    /**
     * @return array<string, list<array{value: string|bool, label: string}>>
     */
    public static function all(?string $locale = null): array
    {
        $locale = self::labelLocale($locale ?? app()->getLocale());

        return collect(self::OPTIONS)
            ->map(fn (array $options): array => array_map(
                fn (array $option): array => [
                    'value' => $option['value'],
                    'label' => $option['labels'][$locale] ?? $option['labels']['en'],
                ],
                $options
            ))
            ->all();
    }

    /** @return list<string|bool> */
    public static function values(string $group): array
    {
        return array_column(self::OPTIONS[$group] ?? [], 'value');
    }

    public static function label(string $group, mixed $value, string $locale = 'en'): ?string
    {
        $locale = self::labelLocale($locale);

        foreach (self::OPTIONS[$group] ?? [] as $option) {
            if ($option['value'] === $value
                || (!is_bool($option['value']) && (string) $option['value'] === (string) $value)) {
                return $option['labels'][$locale] ?? $option['labels']['en'];
            }
        }

        return null;
    }

    private static function labelLocale(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'bn') ? 'bn' : 'en';
    }
}
