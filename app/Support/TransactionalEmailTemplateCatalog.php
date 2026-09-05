<?php

namespace App\Support;

final class TransactionalEmailTemplateCatalog
{
    public const NEWSLETTER_CONFIRMATION = 'newsletter_confirmation';
    public const SPONSORSHIP_CONFIRMATION = 'sponsorship_confirmation';
    public const SPONSORSHIP_ADMIN_NOTIFICATION = 'sponsorship_admin_notification';
    public const VOLUNTEER_ADMIN_NOTIFICATION = 'volunteer_admin_notification';

    public const LOCALES = ['en', 'bn'];

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            self::NEWSLETTER_CONFIRMATION => [
                'label' => 'Newsletter confirmation',
                'description' => 'Double opt-in email sent to a new newsletter subscriber.',
                'placeholders' => [
                    'site_name' => 'Configured website name',
                    'confirmation_url' => 'Signed, expiring confirmation link',
                    'expiry_hours' => 'Confirmation-link lifetime in hours',
                ],
                'url_placeholders' => ['confirmation_url'],
                'required_in_each_body' => ['confirmation_url'],
                'defaults' => [
                    'en' => [
                        'subject' => 'Confirm your {{site_name}} email subscription',
                        'html_body' => '<h1>Confirm your email subscription</h1><p>Someone asked to receive {{site_name}} updates at this address. Confirm only if that was you.</p><p><a href="{{confirmation_url}}">Confirm subscription</a></p><p>This link expires in {{expiry_hours}} hours. If you did not request these updates, ignore this email and no subscription will be activated.</p>',
                        'text_body' => "Confirm your email subscription\n\nSomeone asked to receive {{site_name}} updates at this address. Confirm only if that was you.\n\nConfirm subscription: {{confirmation_url}}\n\nThis link expires in {{expiry_hours}} hours. If you did not request these updates, ignore this email and no subscription will be activated.",
                    ],
                    'bn' => [
                        'subject' => '{{site_name}} ইমেইল সাবস্ক্রিপশন নিশ্চিত করুন',
                        'html_body' => '<h1>আপনার ইমেইল সাবস্ক্রিপশন নিশ্চিত করুন</h1><p>এই ঠিকানায় {{site_name}}-এর আপডেট পাওয়ার অনুরোধ করা হয়েছে। অনুরোধটি আপনি করে থাকলেই নিশ্চিত করুন।</p><p><a href="{{confirmation_url}}">সাবস্ক্রিপশন নিশ্চিত করুন</a></p><p>লিংকটির মেয়াদ {{expiry_hours}} ঘণ্টা। আপনি অনুরোধটি না করে থাকলে ইমেইলটি উপেক্ষা করুন; কোনো সাবস্ক্রিপশন চালু হবে না।</p>',
                        'text_body' => "আপনার ইমেইল সাবস্ক্রিপশন নিশ্চিত করুন\n\nএই ঠিকানায় {{site_name}}-এর আপডেট পাওয়ার অনুরোধ করা হয়েছে। অনুরোধটি আপনি করে থাকলেই নিশ্চিত করুন।\n\nসাবস্ক্রিপশন নিশ্চিত করুন: {{confirmation_url}}\n\nলিংকটির মেয়াদ {{expiry_hours}} ঘণ্টা। আপনি অনুরোধটি না করে থাকলে ইমেইলটি উপেক্ষা করুন; কোনো সাবস্ক্রিপশন চালু হবে না।",
                    ],
                ],
            ],
            self::SPONSORSHIP_CONFIRMATION => [
                'label' => 'Sponsorship confirmation',
                'description' => 'Acknowledgement sent to the person who submits a sponsorship request.',
                'placeholders' => [
                    'site_name' => 'Configured website name',
                    'site_url' => 'Configured public website address',
                    'contact_email' => 'Deployment-configured public contact address',
                    'sponsor_name' => 'Sponsor name from the submitted request',
                    'response_hours' => 'Expected response time in hours',
                    'request_reference' => 'Generated sponsorship request reference',
                ],
                'url_placeholders' => ['site_url'],
                'required_in_each_body' => ['sponsor_name', 'request_reference'],
                'defaults' => [
                    'en' => [
                        'subject' => 'Thank you for your sponsorship request',
                        'html_body' => '<h1>Thank you, {{sponsor_name}}</h1><p>We have received your sponsorship request ({{request_reference}}). Your support helps children in Bangladesh access education, care, and opportunity.</p><p>One of our team members will contact you within {{response_hours}} hours.</p><p>Warm regards,<br>The {{site_name}} team</p><p>Email: {{contact_email}}<br>Website: <a href="{{site_url}}">{{site_url}}</a></p>',
                        'text_body' => "Dear {{sponsor_name}},\n\nWe have received your sponsorship request ({{request_reference}}). Your support helps children in Bangladesh access education, care, and opportunity.\n\nOne of our team members will contact you within {{response_hours}} hours.\n\nWarm regards,\nThe {{site_name}} team\nEmail: {{contact_email}}\nWebsite: {{site_url}}",
                    ],
                    'bn' => [
                        'subject' => 'আপনার শিশু সহায়তার অনুরোধের জন্য ধন্যবাদ',
                        'html_body' => '<h1>ধন্যবাদ, {{sponsor_name}}</h1><p>আমরা আপনার শিশু সহায়তার অনুরোধটি ({{request_reference}}) পেয়েছি। আপনার সহায়তা বাংলাদেশের শিশুদের শিক্ষা, যত্ন ও সুযোগ পেতে সাহায্য করে।</p><p>আমাদের দলের একজন সদস্য {{response_hours}} ঘণ্টার মধ্যে আপনার সঙ্গে যোগাযোগ করবেন।</p><p>শুভেচ্ছান্তে,<br>{{site_name}} দল</p><p>ইমেইল: {{contact_email}}<br>ওয়েবসাইট: <a href="{{site_url}}">{{site_url}}</a></p>',
                        'text_body' => "প্রিয় {{sponsor_name}},\n\nআমরা আপনার শিশু সহায়তার অনুরোধটি ({{request_reference}}) পেয়েছি। আপনার সহায়তা বাংলাদেশের শিশুদের শিক্ষা, যত্ন ও সুযোগ পেতে সাহায্য করে।\n\nআমাদের দলের একজন সদস্য {{response_hours}} ঘণ্টার মধ্যে আপনার সঙ্গে যোগাযোগ করবেন।\n\nশুভেচ্ছান্তে,\n{{site_name}} দল\nইমেইল: {{contact_email}}\nওয়েবসাইট: {{site_url}}",
                    ],
                ],
            ],
            self::SPONSORSHIP_ADMIN_NOTIFICATION => [
                'label' => 'New sponsorship request — team alert',
                'description' => 'Internal notification sent to the deployment-configured team mailbox.',
                'placeholders' => [
                    'site_name' => 'Configured website name',
                    'sponsor_name' => 'Submitted sponsor name',
                    'sponsor_email' => 'Submitted sponsor email',
                    'sponsor_phone' => 'Submitted sponsor phone, if supplied',
                    'sponsor_address' => 'Submitted sponsor address, if supplied',
                    'children_count' => 'Requested number of children',
                    'contribution_interval' => 'Requested contribution interval',
                    'sponsorship_amount' => 'Server-calculated sponsorship amount',
                    'request_reference' => 'Generated sponsorship request reference',
                ],
                'url_placeholders' => [],
                'required_in_each_body' => ['sponsor_name', 'sponsor_email', 'request_reference'],
                'defaults' => [
                    'en' => [
                        'subject' => 'New sponsorship request — {{request_reference}}',
                        'html_body' => '<h1>New sponsorship request</h1><p><strong>Reference:</strong> {{request_reference}}</p><p><strong>Name:</strong> {{sponsor_name}}<br><strong>Email:</strong> {{sponsor_email}}<br><strong>Phone:</strong> {{sponsor_phone}}<br><strong>Address:</strong> {{sponsor_address}}</p><p><strong>Children:</strong> {{children_count}}<br><strong>Interval:</strong> {{contribution_interval}}<br><strong>Amount:</strong> {{sponsorship_amount}}</p><p>This alert was sent automatically by {{site_name}}.</p>',
                        'text_body' => "A new sponsorship request has been received.\n\nReference: {{request_reference}}\nName: {{sponsor_name}}\nEmail: {{sponsor_email}}\nPhone: {{sponsor_phone}}\nAddress: {{sponsor_address}}\nNumber of children: {{children_count}}\nContribution interval: {{contribution_interval}}\nSponsorship amount: {{sponsorship_amount}}\n\nThis alert was sent automatically by {{site_name}}.",
                    ],
                    'bn' => [
                        'subject' => 'নতুন শিশু সহায়তার অনুরোধ — {{request_reference}}',
                        'html_body' => '<h1>নতুন শিশু সহায়তার অনুরোধ</h1><p><strong>রেফারেন্স:</strong> {{request_reference}}</p><p><strong>নাম:</strong> {{sponsor_name}}<br><strong>ইমেইল:</strong> {{sponsor_email}}<br><strong>ফোন:</strong> {{sponsor_phone}}<br><strong>ঠিকানা:</strong> {{sponsor_address}}</p><p><strong>শিশুর সংখ্যা:</strong> {{children_count}}<br><strong>সহায়তার বিরতি:</strong> {{contribution_interval}}<br><strong>পরিমাণ:</strong> {{sponsorship_amount}}</p><p>এই বার্তাটি {{site_name}} থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে।</p>',
                        'text_body' => "একটি নতুন শিশু সহায়তার অনুরোধ পাওয়া গেছে।\n\nরেফারেন্স: {{request_reference}}\nনাম: {{sponsor_name}}\nইমেইল: {{sponsor_email}}\nফোন: {{sponsor_phone}}\nঠিকানা: {{sponsor_address}}\nশিশুর সংখ্যা: {{children_count}}\nসহায়তার বিরতি: {{contribution_interval}}\nপরিমাণ: {{sponsorship_amount}}\n\nএই বার্তাটি {{site_name}} থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে।",
                    ],
                ],
            ],
            self::VOLUNTEER_ADMIN_NOTIFICATION => [
                'label' => 'New volunteer registration — team alert',
                'description' => 'Internal notification sent to the deployment-configured team mailbox.',
                'placeholders' => [
                    'site_name' => 'Configured website name',
                    'volunteer_name' => 'Submitted volunteer name',
                    'volunteer_email' => 'Submitted volunteer email',
                    'volunteer_phone' => 'Submitted volunteer phone',
                    'volunteer_address' => 'Submitted volunteer address',
                    'institution' => 'Submitted institution',
                    'interest_name' => 'Selected volunteer opportunity',
                    'registration_reference' => 'Database registration reference',
                ],
                'url_placeholders' => [],
                'required_in_each_body' => ['volunteer_name', 'volunteer_email', 'registration_reference'],
                'defaults' => [
                    'en' => [
                        'subject' => 'New volunteer registration — {{registration_reference}}',
                        'html_body' => '<h1>New volunteer registration</h1><p><strong>Reference:</strong> {{registration_reference}}</p><p><strong>Name:</strong> {{volunteer_name}}<br><strong>Institution:</strong> {{institution}}<br><strong>Email:</strong> {{volunteer_email}}<br><strong>Phone:</strong> {{volunteer_phone}}<br><strong>Address:</strong> {{volunteer_address}}<br><strong>Interested in:</strong> {{interest_name}}</p><p>This alert was sent automatically by {{site_name}}.</p>',
                        'text_body' => "A new volunteer has registered.\n\nReference: {{registration_reference}}\nName: {{volunteer_name}}\nInstitution: {{institution}}\nEmail: {{volunteer_email}}\nPhone: {{volunteer_phone}}\nAddress: {{volunteer_address}}\nInterested in: {{interest_name}}\n\nThis alert was sent automatically by {{site_name}}.",
                    ],
                    'bn' => [
                        'subject' => 'নতুন স্বেচ্ছাসেবক নিবন্ধন — {{registration_reference}}',
                        'html_body' => '<h1>নতুন স্বেচ্ছাসেবক নিবন্ধন</h1><p><strong>রেফারেন্স:</strong> {{registration_reference}}</p><p><strong>নাম:</strong> {{volunteer_name}}<br><strong>প্রতিষ্ঠান:</strong> {{institution}}<br><strong>ইমেইল:</strong> {{volunteer_email}}<br><strong>ফোন:</strong> {{volunteer_phone}}<br><strong>ঠিকানা:</strong> {{volunteer_address}}<br><strong>আগ্রহের ক্ষেত্র:</strong> {{interest_name}}</p><p>এই বার্তাটি {{site_name}} থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে।</p>',
                        'text_body' => "একজন নতুন স্বেচ্ছাসেবক নিবন্ধন করেছেন।\n\nরেফারেন্স: {{registration_reference}}\nনাম: {{volunteer_name}}\nপ্রতিষ্ঠান: {{institution}}\nইমেইল: {{volunteer_email}}\nফোন: {{volunteer_phone}}\nঠিকানা: {{volunteer_address}}\nআগ্রহের ক্ষেত্র: {{interest_name}}\n\nএই বার্তাটি {{site_name}} থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে।",
                    ],
                ],
            ],
        ];
    }

    public static function supports(string $templateKey, string $locale): bool
    {
        return isset(self::definitions()[$templateKey]) && in_array($locale, self::LOCALES, true);
    }

    /** @return array<string, mixed> */
    public static function definition(string $templateKey): array
    {
        return self::definitions()[$templateKey] ?? [];
    }

    /** @return array{subject: string, html_body: string, text_body: string} */
    public static function defaults(string $templateKey, string $locale): array
    {
        return self::definitions()[$templateKey]['defaults'][$locale] ?? [];
    }

    /**
     * Structured copy shown in the guided editor. The generated HTML and text
     * continue to be stored in the existing columns so deployed databases do
     * not need a second schema or a destructive data conversion.
     *
     * @return array{
     *   subject: string,
     *   heading: string,
     *   introduction: string,
     *   body: string,
     *   button_label?: string,
     *   button_url?: string,
     *   closing: string
     * }
     */
    public static function structuredDefaults(string $templateKey, string $locale): array
    {
        $defaults = [
            self::NEWSLETTER_CONFIRMATION => [
                'en' => [
                    'subject' => 'Confirm your {{site_name}} email subscription',
                    'heading' => 'Confirm your email subscription',
                    'introduction' => 'Someone asked to receive {{site_name}} updates at this address. Confirm only if that was you.',
                    'body' => 'This confirmation link expires in {{expiry_hours}} hours.',
                    'button_label' => 'Confirm subscription',
                    'button_url' => '{{confirmation_url}}',
                    'closing' => 'If you did not request these updates, ignore this email and no subscription will be activated.',
                ],
                'bn' => [
                    'subject' => '{{site_name}} ইমেইল সাবস্ক্রিপশন নিশ্চিত করুন',
                    'heading' => 'আপনার ইমেইল সাবস্ক্রিপশন নিশ্চিত করুন',
                    'introduction' => 'এই ঠিকানায় {{site_name}}-এর আপডেট পাওয়ার অনুরোধ করা হয়েছে। অনুরোধটি আপনি করে থাকলেই নিশ্চিত করুন।',
                    'body' => 'নিশ্চিতকরণ লিংকটির মেয়াদ {{expiry_hours}} ঘণ্টা।',
                    'button_label' => 'সাবস্ক্রিপশন নিশ্চিত করুন',
                    'button_url' => '{{confirmation_url}}',
                    'closing' => 'আপনি অনুরোধটি না করে থাকলে ইমেইলটি উপেক্ষা করুন; কোনো সাবস্ক্রিপশন চালু হবে না।',
                ],
            ],
            self::SPONSORSHIP_CONFIRMATION => [
                'en' => [
                    'subject' => 'Thank you for your sponsorship request',
                    'heading' => 'Thank you, {{sponsor_name}}',
                    'introduction' => 'We have received your sponsorship request ({{request_reference}}). Your support helps children in Bangladesh access education, care, and opportunity.',
                    'body' => 'One of our team members will contact you within {{response_hours}} hours.',
                    'button_label' => 'Visit {{site_name}}',
                    'button_url' => '{{site_url}}',
                    'closing' => "Warm regards,\nThe {{site_name}} team\nEmail: {{contact_email}}",
                ],
                'bn' => [
                    'subject' => 'আপনার শিশু সহায়তার অনুরোধের জন্য ধন্যবাদ',
                    'heading' => 'ধন্যবাদ, {{sponsor_name}}',
                    'introduction' => 'আমরা আপনার শিশু সহায়তার অনুরোধটি ({{request_reference}}) পেয়েছি। আপনার সহায়তা বাংলাদেশের শিশুদের শিক্ষা, যত্ন ও সুযোগ পেতে সাহায্য করে।',
                    'body' => 'আমাদের দলের একজন সদস্য {{response_hours}} ঘণ্টার মধ্যে আপনার সঙ্গে যোগাযোগ করবেন।',
                    'button_label' => '{{site_name}} ওয়েবসাইট দেখুন',
                    'button_url' => '{{site_url}}',
                    'closing' => "শুভেচ্ছান্তে,\n{{site_name}} দল\nইমেইল: {{contact_email}}",
                ],
            ],
            self::SPONSORSHIP_ADMIN_NOTIFICATION => [
                'en' => [
                    'subject' => 'New sponsorship request — {{request_reference}}',
                    'heading' => 'New sponsorship request',
                    'introduction' => 'Reference: {{request_reference}}',
                    'body' => "Name: {{sponsor_name}}\nEmail: {{sponsor_email}}\nPhone: {{sponsor_phone}}\nAddress: {{sponsor_address}}\nChildren: {{children_count}}\nInterval: {{contribution_interval}}\nAmount: {{sponsorship_amount}}",
                    'closing' => 'This alert was sent automatically by {{site_name}}.',
                ],
                'bn' => [
                    'subject' => 'নতুন শিশু সহায়তার অনুরোধ — {{request_reference}}',
                    'heading' => 'নতুন শিশু সহায়তার অনুরোধ',
                    'introduction' => 'রেফারেন্স: {{request_reference}}',
                    'body' => "নাম: {{sponsor_name}}\nইমেইল: {{sponsor_email}}\nফোন: {{sponsor_phone}}\nঠিকানা: {{sponsor_address}}\nশিশুর সংখ্যা: {{children_count}}\nসহায়তার বিরতি: {{contribution_interval}}\nপরিমাণ: {{sponsorship_amount}}",
                    'closing' => 'এই বার্তাটি {{site_name}} থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে।',
                ],
            ],
            self::VOLUNTEER_ADMIN_NOTIFICATION => [
                'en' => [
                    'subject' => 'New volunteer registration — {{registration_reference}}',
                    'heading' => 'New volunteer registration',
                    'introduction' => 'Reference: {{registration_reference}}',
                    'body' => "Name: {{volunteer_name}}\nInstitution: {{institution}}\nEmail: {{volunteer_email}}\nPhone: {{volunteer_phone}}\nAddress: {{volunteer_address}}\nInterested in: {{interest_name}}",
                    'closing' => 'This alert was sent automatically by {{site_name}}.',
                ],
                'bn' => [
                    'subject' => 'নতুন স্বেচ্ছাসেবক নিবন্ধন — {{registration_reference}}',
                    'heading' => 'নতুন স্বেচ্ছাসেবক নিবন্ধন',
                    'introduction' => 'রেফারেন্স: {{registration_reference}}',
                    'body' => "নাম: {{volunteer_name}}\nপ্রতিষ্ঠান: {{institution}}\nইমেইল: {{volunteer_email}}\nফোন: {{volunteer_phone}}\nঠিকানা: {{volunteer_address}}\nআগ্রহের ক্ষেত্র: {{interest_name}}",
                    'closing' => 'এই বার্তাটি {{site_name}} থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে।',
                ],
            ],
        ];

        return $defaults[$templateKey][$locale] ?? [];
    }

    public static function usesButton(string $templateKey): bool
    {
        return in_array($templateKey, [
            self::NEWSLETTER_CONFIRMATION,
            self::SPONSORSHIP_CONFIRMATION,
        ], true);
    }
}
