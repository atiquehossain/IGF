<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Donation;
use App\Models\DonationType;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\SslCommerzTransaction;
use App\Models\Volunteer;
use App\Models\VolunteerCause;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new RuntimeException('LocalDevelopmentSeeder may only run in local or isolated test environments.');
        }

        $password = (string) env('LOCAL_ADMIN_PASSWORD');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set a temporary LOCAL_ADMIN_PASSWORD of at least 12 characters when running this seeder.');
        }

        $this->insertJson('auth_menus', 'auth_menus.seed-data.json');
        $this->insertJson('menu_actions', 'menu_actions.seed-data.json');
        $this->insertJson('roles', 'roles.seed-data.json');
        $this->call(AdminPermissionRegistrySeeder::class);

        Admin::query()->updateOrCreate(
            ['username' => (string) env('LOCAL_ADMIN_USERNAME', 'local-admin')],
            [
                'name' => 'Local Administrator',
                'email' => 'local-admin@example.test',
                'role' => 1,
                'status' => 1,
                'password' => Hash::make($password),
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]
        );

        if (filter_var(env('LOCAL_SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->seedDemoContent();
        }
    }

    private function insertJson(string $table, string $filename): void
    {
        $records = json_decode(
            file_get_contents(database_path('seeders/seed-data/' . $filename)),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        DB::table($table)->upsert($records, ['id']);
    }

    private function seedDemoContent(): void
    {
        $this->seedHomePage();

        foreach ([
            ['uuid' => '55555555-5555-4555-8555-000000000001', 'slug' => 'where-it-is-needed-most', 'name' => 'Where it is needed most', 'description' => 'Flexible support for active community priorities.', 'destination_type' => 'unrestricted', 'destination_name' => null],
            ['uuid' => '55555555-5555-4555-8555-000000000002', 'slug' => 'education', 'name' => 'Education', 'description' => 'Learning access, materials, and school support.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Education Fund'],
            ['uuid' => '84ae0875-0656-494a-b3a2-9c9477397465', 'slug' => 'zakat', 'name' => 'Donate Your Zakat', 'description' => 'Direct eligible Zakat to approved programs in line with the foundation’s Zakat policy.', 'purpose_key' => 'zakat', 'destination_type' => 'restricted_fund', 'destination_name' => 'Zakat Fund'],
        ] as $donationType) {
            DonationType::query()->updateOrCreate(
                ['uuid' => $donationType['uuid']],
                array_merge($donationType, ['status' => 1])
            );
        }

        foreach ([
            ['uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000001', 'slug' => 'sadaqah', 'name' => 'Donate Your Sadaqah', 'description' => 'Give voluntary charity toward approved community needs.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Sadaqah Fund'],
            ['uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000002', 'slug' => 'food-support', 'name' => 'Food Support', 'description' => 'Provide essential food packages to families facing hardship or emergencies.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '62000000-0000-4000-8000-000000000011'],
            ['uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000003', 'slug' => 'emergency-relief', 'name' => 'Emergency Relief', 'description' => 'Provide urgent essentials to communities affected by disasters and emergencies.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Emergency Relief Fund'],
            ['uuid' => '6e3c1d7a-8b01-4f01-8a01-000000000004', 'slug' => 'orphan-support', 'name' => 'Orphan Shelter & Support', 'description' => 'Support safe shelter, education, nutrition and wellbeing for children without parental care.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Orphan Shelter & Support Fund'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000001', 'slug' => 'school-stationery', 'name' => 'School Stationery', 'description' => 'Provide notebooks, pens and essential learning materials.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '22222222-2222-4222-8222-000000000020'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000002', 'slug' => 'school-uniforms', 'name' => 'School Uniforms', 'description' => 'Provide a complete uniform so a learner can attend school with confidence.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '22222222-2222-4222-8222-000000000020'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000003', 'slug' => 'school-meals', 'name' => 'School Meals', 'description' => 'Provide nutritious school-day meals that help children learn and thrive.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '22222222-2222-4222-8222-000000000020'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000004', 'slug' => 'adopt-a-school', 'name' => 'Adopt a School', 'description' => 'Strengthen a school with learning materials, essential facilities and classroom support.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Adopt a School Fund'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000005', 'slug' => 'ramadan-iftar', 'name' => 'Ramadan Iftar', 'description' => 'Provide Iftar meals to families and communities during Ramadan.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '62000000-0000-4000-8000-000000000011'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000006', 'slug' => 'qurbani', 'name' => 'Qurbani', 'description' => 'Support Qurbani and meat distribution for eligible families.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Qurbani Fund'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000007', 'slug' => 'pure-water-and-sanitation', 'name' => 'Pure Water & Sanitation', 'description' => 'Support safe water, sanitation facilities and hygiene education.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '22222222-2222-4222-8222-000000000022'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000008', 'slug' => 'women-empowerment', 'name' => 'Women Empowerment', 'description' => 'Help women build skills, livelihoods and long-term financial resilience.', 'destination_type' => 'restricted_fund', 'destination_name' => 'Women Empowerment Fund'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000009', 'slug' => 'youth-development', 'name' => 'Youth Development', 'description' => 'Equip young people with education, leadership and employability skills.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '62000000-0000-4000-8000-000000000002'],
            ['uuid' => '7a4c2d8e-8b01-4f01-8a01-000000000010', 'slug' => 'street-children-education', 'name' => 'Street Children Education', 'description' => 'Provide safe, accessible learning and support for street-connected children.', 'destination_type' => 'page', 'destination_name' => null, 'destination_page_uuid' => '22222222-2222-4222-8222-000000000020'],
        ] as $draftCause) {
            DonationType::query()->updateOrCreate(
                ['uuid' => $draftCause['uuid']],
                array_merge($draftCause, ['status' => 0])
            );
        }

        $category = Category::query()->updateOrCreate(
            ['slug' => 'stories', 'language' => 'en'],
            [
                'name' => 'Stories',
                'uuid' => '11111111-1111-4111-8111-111111111111',
                'description' => 'Impact stories from the field.',
                'status' => 1,
            ]
        );

        $pages = [
            ['name' => 'Empowering Women Artisans in Rural Sylhet', 'slug' => 'empowering-women-artisans', 'publication_status' => 'published', 'status' => 1, 'visibility' => 'public', 'description' => '<p>Women artisans in rural Sylhet are strengthening their skills, connecting with new markets, and building more reliable livelihoods through community-led enterprise support.</p>', 'meta_description' => 'Women artisans in rural Sylhet build skills, market connections, and resilient livelihoods.'],
            ['name' => 'Clean Water Initiative Expansion 2025', 'slug' => 'clean-water-initiative', 'publication_status' => 'draft', 'status' => 0, 'visibility' => 'public'],
            ['name' => 'Community Health Outreach', 'slug' => 'community-health-outreach', 'publication_status' => 'pending_review', 'status' => 0, 'visibility' => 'public'],
        ];

        foreach ($pages as $index => $attributes) {
            Page::query()->updateOrCreate(
                ['slug' => $attributes['slug'], 'language' => 'en'],
                array_merge($attributes, [
                    'uuid' => sprintf('22222222-2222-4222-8222-%012d', $index + 1),
                    'category_id' => $category->id,
                    'sub_title' => 'Impact begins with opportunity and community-led change.',
                    'description' => $attributes['description'] ?? '<p>Editorial content is being prepared for review.</p>',
                    'published_at' => today()->subDays($index + 1),
                    'last_published_at' => $attributes['status'] ? now()->subDays($index + 1) : null,
                    'meta_title' => $attributes['name'] . ' | Ignite Global Foundation',
                    'meta_description' => $attributes['meta_description'] ?? 'Editorial content is being prepared for review.',
                    'order_by' => $index + 1,
                ])
            );
        }

        $aboutCategory = Category::query()->updateOrCreate(
            ['slug' => 'about', 'language' => 'en'],
            ['name' => 'About', 'uuid' => '11111111-1111-4111-8111-000000000010', 'description' => 'Organization and governance pages.', 'status' => 1]
        );
        Page::query()->updateOrCreate(
            ['slug' => 'about-us', 'language' => 'en'],
            [
                'uuid' => '22222222-2222-4222-8222-000000000010', 'category_id' => $aboutCategory->id,
                'name' => 'About Ignite Global Foundation', 'sub_title' => 'Community leadership is at the heart of lasting change.',
                'description' => '<p>Ignite Global Foundation works alongside communities in Bangladesh through education, health, livelihoods, clean water, and humanitarian action.</p><h2>Our approach</h2><p>We listen first, build with local leaders, and measure progress transparently so that programs remain useful long after a project begins.</p>',
                'status' => 1, 'publication_status' => 'published', 'visibility' => 'public', 'published_at' => today(),
                'meta_title' => 'About Us | Ignite Global Foundation',
                'meta_description' => 'Learn how Ignite Global Foundation works alongside communities in Bangladesh and how the organization is governed.',
                'order_by' => 1,
            ]
        );
        Page::query()->updateOrCreate(
            ['slug' => "founder's-letter", 'language' => 'en'],
            [
                'uuid' => '22222222-2222-4222-8222-000000000011', 'category_id' => $aboutCategory->id,
                'name' => "Founder's letter", 'sub_title' => 'A note on shared responsibility.',
                'description' => '<p>Real progress grows from dignity, trust, and the determination of people closest to each challenge. Our responsibility is to stand beside those leaders with practical support and lasting partnership.</p>',
                'status' => 1, 'publication_status' => 'published', 'visibility' => 'public', 'published_at' => today(), 'order_by' => 2,
            ]
        );
        $givingCategory = Category::query()->updateOrCreate(
            ['slug' => 'giving', 'language' => 'en'],
            ['name' => 'Giving', 'uuid' => '11111111-1111-4111-8111-000000000011', 'description' => 'Ways to give.', 'status' => 1]
        );
        Page::query()->updateOrCreate(
            ['slug' => 'zakat', 'language' => 'en'],
            [
                'uuid' => '22222222-2222-4222-8222-000000000012', 'category_id' => $givingCategory->id,
                'name' => 'Give your Zakat', 'sub_title' => 'Direct eligible giving toward education, food, and livelihoods.',
                'description' => '<h2>Turn your Zakat into practical opportunity.</h2><p>Use the calculator below for an estimate, then choose Zakat on the secure donation page. Ignite applies designated funds only to eligible programs.</p>',
                'status' => 1, 'publication_status' => 'published', 'visibility' => 'public', 'published_at' => today(),
                'meta_title' => 'Zakat Giving | Ignite Global Foundation',
                'meta_description' => 'Calculate your Zakat and support eligible education, food, and livelihood programs through Ignite Global Foundation.',
                'order_by' => 1,
            ]
        );

        $programCategory = Category::query()->updateOrCreate(
            ['slug' => 'our-causes', 'language' => 'en'],
            [
                'name' => 'Our programs', 'uuid' => '11111111-1111-4111-8111-000000000012',
                'description' => 'Connected programs designed with communities to expand opportunity and resilience.',
                'meta_title' => 'Our Programs | Ignite Global Foundation',
                'meta_description' => 'Explore Ignite Global Foundation programs in education, health, livelihoods, and clean water.',
                'status' => 1,
            ]
        );
        foreach ([
            ['slug' => 'education', 'name' => 'Education', 'sub_title' => 'Learning access, school support, and youth development.'],
            ['slug' => 'healthcare', 'name' => 'Healthcare', 'sub_title' => 'Community health outreach and essential care.'],
            ['slug' => 'clean-water', 'name' => 'Clean water', 'sub_title' => 'Safe water access and community-owned infrastructure.'],
            ['slug' => 'livelihoods', 'name' => 'Livelihoods', 'sub_title' => 'Skills, enterprise, and pathways to reliable income.'],
        ] as $index => $program) {
            Page::query()->updateOrCreate(
                ['slug' => $program['slug'], 'language' => 'en'],
                [
                    'uuid' => sprintf('22222222-2222-4222-8222-%012d', 20 + $index),
                    'category_id' => $programCategory->id, 'name' => $program['name'], 'sub_title' => $program['sub_title'],
                    'description' => '<p>' . $program['sub_title'] . ' Ignite works with local leaders to design practical support around community priorities and long-term resilience.</p>',
                    'status' => 1, 'publication_status' => 'published', 'visibility' => 'public', 'published_at' => today(),
                    'meta_title' => $program['name'] . ' | Ignite Global Foundation',
                    'meta_description' => $program['sub_title'], 'order_by' => $index + 1,
                ]
            );
        }

        foreach ([
            ['slug' => 'privacy-policy', 'name' => 'Privacy policy', 'description' => '<h2>Information we collect</h2><p>Ignite Global Foundation collects the information you choose to provide when you contact us, donate, register to volunteer, subscribe for updates, or request sponsorship information.</p><h2>How information is used</h2><p>We use this information to respond to you, administer the service you requested, maintain required financial and safeguarding records, and improve our programs. Card details are handled by the payment provider and are not stored on this website.</p><h2>Your choices</h2><p>You may ask to access, correct, or delete eligible personal information by contacting info@ignite.org.bd. We restrict access to authorized personnel and retain information only for operational, legal, and safeguarding needs.</p>'],
            ['slug' => 'terms-conditions', 'name' => 'Terms of service', 'description' => '<h2>Using this website</h2><p>You may use this website for lawful, personal purposes. Do not attempt to disrupt the service, access another person\'s account, upload harmful material, or misuse Ignite Global Foundation content.</p><h2>Information and donations</h2><p>Program information is provided for general awareness and may change as community needs evolve. Donations are processed by an independent payment provider. Contact info@ignite.org.bd promptly if you believe a transaction was made in error.</p><h2>Content</h2><p>Unless otherwise stated, website text, photographs, and visual materials belong to Ignite Global Foundation or are used with permission. Please request written permission before reproducing them.</p>'],
            ['slug' => 'safeguarding', 'name' => 'Safeguarding', 'description' => '<h2>Our commitment</h2><p>Ignite Global Foundation is committed to protecting children, adults at risk, community members, volunteers, and staff from abuse, exploitation, harassment, and neglect.</p><h2>How we work</h2><p>People representing Ignite are expected to follow safeguarding standards, treat every person with dignity, minimize risk, and report concerns promptly. Concerns are handled confidentially and shared only with people responsible for responding safely.</p><h2>Report a concern</h2><p>To raise a safeguarding concern, contact info@ignite.org.bd or +880 1972016221. If someone is in immediate danger, contact the appropriate local emergency or protection service first. Retaliation against a person who reports a concern in good faith is not accepted.</p>'],
        ] as $index => $legalPage) {
            Page::query()->updateOrCreate(
                ['slug' => $legalPage['slug'], 'language' => 'en'],
                [
                    'uuid' => sprintf('22222222-2222-4222-8222-%012d', 30 + $index),
                    'category_id' => $aboutCategory->id, 'name' => $legalPage['name'], 'sub_title' => '', 'description' => $legalPage['description'],
                    'status' => 1, 'publication_status' => 'published', 'visibility' => 'public', 'published_at' => today(),
                    'meta_title' => $legalPage['name'] . ' | Ignite Global Foundation',
                    'meta_description' => 'Ignite Global Foundation ' . strtolower($legalPage['name']) . '.', 'order_by' => 20 + $index,
                ]
            );
        }

        $editorPage = Page::query()
            ->where('slug', 'empowering-women-artisans')
            ->where('language', 'en')
            ->firstOrFail();

        $demoBlocks = [
            [
                'uuid' => '33333333-3333-4333-8333-000000000001',
                'type' => 'hero',
                'label' => 'Hero Banner',
                'content' => [
                    'eyebrow' => 'Urgent initiative',
                    'heading' => 'Empowering the Future, Block by Block.',
                    'body' => 'Join our latest campaign to build sustainable opportunities in rural communities.',
                    'primary_label' => 'Donate now',
                    'primary_url' => '/donate',
                    'secondary_label' => '',
                    'secondary_url' => '',
                    'image' => '/image/banner/slider-1.png',
                    'overlay_opacity' => 64,
                    'autoplay' => true,
                    'interval' => 6000,
                    'pause_on_hover' => true,
                    'slides' => [[
                        'eyebrow' => 'Urgent initiative',
                        'heading' => 'Empowering the Future, Block by Block.',
                        'body' => 'Join our latest campaign to build sustainable opportunities in rural communities.',
                        'primary_label' => 'Donate now',
                        'primary_url' => '/donate',
                        'secondary_label' => '',
                        'secondary_url' => '',
                        'report_label' => '',
                        'report_url' => '',
                        'image' => '/image/banner/slider-1.png',
                        'overlay_opacity' => 64,
                    ]],
                ],
                'sort_order' => 1,
            ],
            [
                'uuid' => '33333333-3333-4333-8333-000000000002',
                'type' => 'stats',
                'label' => 'Impact Grid',
                'content' => [
                    'heading' => 'Impact made visible',
                    'items' => [
                        ['value' => '2.4M', 'label' => 'Lives impacted'],
                        ['value' => '150+', 'label' => 'Active projects'],
                        ['value' => '98%', 'label' => 'Funds to field'],
                    ],
                ],
                'sort_order' => 2,
            ],
        ];

        foreach ($demoBlocks as $attributes) {
            $block = PageBlock::withTrashed()->updateOrCreate(
                ['uuid' => $attributes['uuid']],
                array_merge($attributes, [
                    'page_id' => $editorPage->id,
                    'settings' => [],
                    'is_enabled' => true,
                    'show_on_desktop' => true,
                    'show_on_mobile' => true,
                ])
            );
            if ($block->trashed()) {
                $block->restore();
            }
        }

        Page::query()->updateOrCreate(
            ['slug' => 'empowering-women-artisans', 'language' => 'bn'],
            [
                'uuid' => '22222222-2222-4222-8222-000000000001',
                'name' => 'গ্রামীণ সিলেটের নারী কারিগরদের ক্ষমতায়ন',
                'category_id' => $category->id,
                'sub_title' => 'সুযোগ ও সমাজ-নেতৃত্বাধীন পরিবর্তন।',
                'description' => '<p>স্থানীয় ভিজ্যুয়াল গুণমান যাচাইয়ের নমুনা কনটেন্ট।</p>',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'published_at' => today()->subDay(),
                'last_published_at' => now()->subDay(),
                'order_by' => 1,
            ]
        );

        foreach (range(0, 6) as $index) {
            $createdAt = now()->startOfMonth()->subMonths(6 - $index)->addDays(5);
            $donation = Donation::query()->updateOrCreate(
                ['transaction_id' => 'LOCAL-DEMO-' . ($index + 1)],
                [
                    'donor_name' => 'Local Demo Donor',
                    'amount' => 12000 + ($index * 6500),
                    'payment_status' => 'Success',
                    'status' => 1,
                ]
            );
            $donation->timestamps = false;
            $donation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        Donation::query()->updateOrCreate(
            ['transaction_id' => 'LOCAL-DEMO-TODAY'],
            ['donor_name' => 'Local Demo Donor', 'amount' => 4250, 'payment_status' => 'Success', 'status' => 1]
        );
        SslCommerzTransaction::query()->updateOrCreate(
            ['tran_id' => 'LOCAL-PENDING-1'],
            ['status' => 'PENDING', 'amount' => 2500, 'currency' => 'BDT']
        );
        $cause = VolunteerCause::query()->updateOrCreate(
            ['name' => 'Weekend community outreach'],
            ['description' => 'Local quality assurance cause.', 'status' => 1]
        );
        Volunteer::query()->updateOrCreate(
            ['email' => 'local-volunteer@example.test'],
            ['name' => 'Local Volunteer', 'phone' => '00000000000', 'cause_id' => $cause->id, 'status' => 1]
        );

        $this->call(IgniteParityContentSeeder::class);
    }

    private function seedHomePage(): void
    {
        $category = Category::query()->updateOrCreate(
            ['slug' => 'home', 'language' => 'en'],
            [
                'name' => 'Homepage',
                'uuid' => '11111111-1111-4111-8111-000000000001',
                'description' => 'Editable homepage sections. Deprecated category descriptions remain supported for migration only.',
                'status' => 1,
            ]
        );

        $page = Page::query()->updateOrCreate(
            ['slug' => 'home', 'language' => 'en'],
            [
                'uuid' => '22222222-2222-4222-8222-000000000100',
                'category_id' => $category->id,
                'name' => 'Ignite Global Foundation',
                'sub_title' => 'Community-led change through education, health, livelihoods, and humanitarian action.',
                'description' => '',
                'status' => 1,
                'publication_status' => 'published',
                'visibility' => 'public',
                'published_at' => today(),
                'last_published_at' => now(),
                'meta_title' => 'Ignite Global Foundation | Building Lasting Change',
                'meta_description' => 'Ignite Global Foundation works with communities in Bangladesh to expand opportunity through sustainable, locally led programs.',
                'order_by' => 0,
            ]
        );

        $blocks = [
            [
                'uuid' => '44444444-4444-4444-8444-000000000001',
                'type' => 'hero',
                'label' => 'Homepage Hero',
                'content' => [
                    'eyebrow' => 'Building opportunity together',
                    'heading' => 'Igniting hope. Building lasting change.',
                    'body' => 'We work alongside marginalized communities in Bangladesh through sustainable education, healthcare, and livelihood programs.',
                    'primary_label' => 'Donate now',
                    'primary_url' => '/donate',
                    'secondary_label' => 'Explore our work',
                    'secondary_url' => '/category/our-causes',
                    'report_label' => 'Read our latest annual report',
                    'report_url' => '/annual-report',
                    'image' => '/image/banner/slider-1.png',
                    'overlay_opacity' => 64,
                    'autoplay' => true,
                    'interval' => 6000,
                    'pause_on_hover' => true,
                    'slides' => [
                        [
                            'eyebrow' => 'Building opportunity together',
                            'heading' => 'Igniting hope. Building lasting change.',
                            'body' => 'We work alongside marginalized communities in Bangladesh through sustainable education, healthcare, and livelihood programs.',
                            'primary_label' => 'Donate now',
                            'primary_url' => '/donate',
                            'secondary_label' => 'Explore our work',
                            'secondary_url' => '/category/our-causes',
                            'report_label' => 'Read our latest annual report',
                            'report_url' => '/annual-report',
                            'image' => '/image/banner/slider-1.png',
                            'overlay_opacity' => 64,
                        ],
                        [
                            'eyebrow' => 'Education that opens doors',
                            'heading' => 'Every child deserves the chance to learn.',
                            'body' => 'Together, we help children access safe learning spaces, essential materials, and community support that lasts.',
                            'primary_label' => 'Support education',
                            'primary_url' => '/donate',
                            'secondary_label' => 'See our programs',
                            'secondary_url' => '/category/our-causes',
                            'report_label' => '',
                            'report_url' => '',
                            'image' => '/image/banner/slider-2.png',
                            'overlay_opacity' => 58,
                        ],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000002',
                'type' => 'stats',
                'label' => 'Verified Impact Metrics',
                'content' => [
                    'heading' => '',
                    'items' => [
                        ['value' => '2.4M', 'label' => 'Lives reached', 'icon' => 'people'],
                        ['value' => '32', 'label' => 'Districts served', 'icon' => 'map'],
                        ['value' => '150+', 'label' => 'Community projects', 'icon' => 'heart'],
                        ['value' => '18K', 'label' => 'Learners supported', 'icon' => 'school'],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000003',
                'type' => 'media_text',
                'label' => 'Who We Are',
                'content' => [
                    'eyebrow' => 'Who we are',
                    'heading' => 'Change lasts when communities lead it.',
                    'body' => '<p>Every community holds the seeds of its own prosperity. Ignite Global Foundation works hand-in-hand with local leaders to strengthen access to resources, training, and infrastructure.</p><p>Our connected approach to education, healthcare, livelihoods, and relief is designed around long-term resilience.</p>',
                    'image' => '/image/welcome/welcome-bg.webp',
                    'image_alt' => 'Students participating in an Ignite-supported community education program',
                    'image_position' => 'left',
                    'link_label' => 'About us',
                    'link_url' => '/about-us',
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000004',
                'type' => 'cards',
                'label' => 'Our Programs',
                'content' => [
                    'variant' => 'programs',
                    'eyebrow' => 'What we do',
                    'heading' => 'Our programs',
                    'body' => 'A holistic approach to community development, shaped with the people each program serves.',
                    'items' => [
                        ['icon' => 'school', 'heading' => 'Education', 'body' => 'Inclusive learning environments that help the next generation thrive.', 'url' => '/category/our-causes'],
                        ['icon' => 'health', 'heading' => 'Healthcare', 'body' => 'Essential services and practical health education for remote communities.', 'url' => '/category/our-causes'],
                        ['icon' => 'water', 'heading' => 'Clean Water', 'body' => 'Safe drinking water and sanitation infrastructure built to last.', 'url' => '/category/our-causes'],
                        ['icon' => 'leaf', 'heading' => 'Livelihoods', 'body' => 'Skills, agriculture, and small-enterprise support that expand opportunity.', 'url' => '/category/our-causes'],
                        ['icon' => 'relief', 'heading' => 'Emergency Relief', 'body' => 'Rapid response and community-led recovery during urgent crises.', 'url' => '/category/our-causes'],
                        ['icon' => 'child', 'heading' => 'Child Sponsorship', 'body' => 'Direct support for education, wellbeing, and a safer childhood.', 'url' => '/sponsor-child'],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000005',
                'type' => 'cta',
                'label' => 'Featured Campaign',
                'content' => [
                    'variant' => 'campaign',
                    'eyebrow' => 'Featured campaign',
                    'heading' => 'Emergency Community Support',
                    'body' => 'Your contribution supports urgent relief today and helps communities prepare for long-term recovery.',
                    'raised' => '650000',
                    'target' => '1000000',
                    'currency' => '৳',
                    'amounts' => ['500', '1000', '2000'],
                    'primary_label' => 'Donate now',
                    'primary_url' => '/donate',
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000006',
                'type' => 'cards',
                'label' => 'Featured Projects',
                'content' => [
                    'variant' => 'projects',
                    'eyebrow' => 'Field work',
                    'heading' => 'Featured projects',
                    'body' => 'Current initiatives designed with measurable, community-owned outcomes.',
                    'items' => [
                        ['status' => 'Active', 'location' => 'Sylhet', 'heading' => 'Women-led artisan livelihoods', 'body' => 'Skills and market access for rural entrepreneurs.', 'image' => '/image/our-cause/causes-3.png', 'image_alt' => 'Women participating in a livelihood program', 'url' => '/page/empowering-women-artisans'],
                        ['status' => 'Expanding', 'location' => 'Dhaka', 'heading' => 'Learning spaces for every child', 'body' => 'Inclusive education in communities with limited access.', 'image' => '/image/welcome/welcome-bg.webp', 'image_alt' => 'Children in a community education program', 'url' => '/category/our-causes'],
                        ['status' => 'Ongoing', 'location' => 'Bangladesh', 'heading' => 'Community health outreach', 'body' => 'Practical care and health information closer to home.', 'image' => '/image/news.png', 'image_alt' => 'Community outreach activity', 'url' => '/category/our-causes'],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000007',
                'type' => 'media_text',
                'label' => 'Community Story',
                'content' => [
                    'variant' => 'story',
                    'eyebrow' => 'A story from the community',
                    'heading' => 'Opportunity grows when people can shape the solution.',
                    'body' => '<p>Across Bangladesh, local leaders are combining practical knowledge, shared responsibility, and community networks to improve access to education, health, and reliable livelihoods.</p>',
                    'image' => '/image/our-cause/causes-3.png',
                    'image_alt' => 'Community members taking part in a local program',
                    'image_position' => 'right',
                    'link_label' => 'Read community stories',
                    'link_url' => '/events',
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000008',
                'type' => 'cards',
                'label' => 'Events and News',
                'content' => [
                    'variant' => 'updates',
                    'eyebrow' => 'Stay involved',
                    'heading' => 'Events & latest news',
                    'items' => [
                        ['eyebrow' => 'Upcoming event', 'heading' => 'Community volunteer orientation', 'body' => 'Meet the team and learn how to support upcoming field activities.', 'url' => '/events'],
                        ['eyebrow' => 'Upcoming event', 'heading' => 'Youth learning workshop', 'body' => 'A practical session for students, educators, and local volunteers.', 'url' => '/events'],
                        ['eyebrow' => 'Latest news', 'heading' => 'Clean water initiative reaches its next community', 'body' => 'An update from our locally led water and sanitation work.', 'image' => '/image/banner/slider-2.png', 'image_alt' => 'Community water initiative', 'url' => '/events'],
                        ['eyebrow' => 'Latest news', 'heading' => 'New livelihood cohort begins training', 'body' => 'Participants start the next phase of skills and market-readiness support.', 'image' => '/image/news.png', 'image_alt' => 'Livelihood program update', 'url' => '/events'],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000009',
                'type' => 'rich_text',
                'label' => 'Accountability',
                'content' => [
                    'eyebrow' => 'Open by design',
                    'heading' => 'Our commitment to accountability',
                    'body' => '<p>We are committed to responsible stewardship, transparent reporting, safeguarding, and clear ways for communities and supporters to raise concerns.</p><p><a href="/annual-report">View annual reports</a> · <a href="/contact-us">Contact our team</a></p>',
                    'items' => [
                        ['icon' => 'report', 'heading' => 'Annual reports', 'url' => '/annual-report'],
                        ['icon' => 'financials', 'heading' => 'Financials', 'url' => '/annual-report'],
                        ['icon' => 'security', 'heading' => 'Safeguarding', 'url' => '/page/safeguarding'],
                        ['icon' => 'policy', 'heading' => 'Policies', 'url' => '/page/privacy-policy'],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000010',
                'type' => 'cards',
                'label' => 'Partners',
                'content' => [
                    'variant' => 'partners',
                    'eyebrow' => 'Supported by',
                    'heading' => 'Partnership makes progress possible',
                    'items' => [
                        ['heading' => 'Community partners', 'image' => '/image/award/award-1.png', 'image_alt' => 'Community partner mark'],
                        ['heading' => 'Program partners', 'image' => '/image/award/award-2.png', 'image_alt' => 'Program partner mark'],
                        ['heading' => 'Learning partners', 'image' => '/image/award/award-3.png', 'image_alt' => 'Learning partner mark'],
                    ],
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000011',
                'type' => 'cta',
                'label' => 'Volunteer Call to Action',
                'content' => [
                    'variant' => 'volunteer',
                    'eyebrow' => 'Take part',
                    'heading' => 'Join our mission',
                    'body' => 'Volunteer your time, support a program, or help share community-led stories.',
                    'primary_label' => 'Become a volunteer',
                    'primary_url' => '/volunteer/register',
                    'secondary_label' => 'Partner with us',
                    'secondary_url' => '/contact-us',
                ],
            ],
            [
                'uuid' => '44444444-4444-4444-8444-000000000012',
                'type' => 'newsletter',
                'label' => 'Newsletter',
                'content' => [
                    'heading' => 'Stay informed',
                    'body' => 'Receive field updates, upcoming events, and thoughtful ways to help.',
                    'button_label' => 'Subscribe',
                ],
            ],
        ];

        foreach ($blocks as $index => $attributes) {
            $block = PageBlock::withTrashed()->updateOrCreate(
                ['uuid' => $attributes['uuid']],
                array_merge($attributes, [
                    'page_id' => $page->id,
                    'settings' => [],
                    'sort_order' => $index + 1,
                    'is_enabled' => true,
                    'show_on_desktop' => true,
                    'show_on_mobile' => true,
                ])
            );
            if ($block->trashed()) {
                $block->restore();
            }
        }
    }
}
