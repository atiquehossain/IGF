<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\AnnualReport;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageMenu;
use App\Models\PageTagModule;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IgniteParityContentSeeder extends Seeder
{
    private const MEDIA = '/storage/media/ignite-live/';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('IgniteParityContentSeeder is limited to local and test environments.');
        }

        $this->seedBundledAssets();
        $this->completeImportedMediaMetadata();
        $this->seedPagesAndProjects();
        $this->seedEvents();
        $this->seedTestimonials();
        $this->seedReports();
        $this->seedGallery();
        $this->seedTeam();
        $this->seedNavigation();
        $this->seedHomepage();
    }

    private function seedPagesAndProjects(): void
    {
        $programs = $this->category(
            'our-causes',
            'Our programs',
            'Education, youth leadership, livelihoods, healthcare, and disaster resilience programs designed with communities.',
            '61000000-0000-4000-8000-000000000001'
        );
        $about = $this->category(
            'about',
            'About',
            'Organization, governance, careers, and public policies.',
            '61000000-0000-4000-8000-000000000002'
        );
        $awards = $this->category(
            'awards-&-recognition',
            'Awards & Recognition',
            'Recognition of Ignite Global Foundation leadership, service, education, and community impact.',
            '61000000-0000-4000-8000-000000000003'
        );
        $careers = $this->category(
            'career',
            'Career',
            'Employment, internships, and volunteer opportunities with Ignite Global Foundation.',
            '61000000-0000-4000-8000-000000000004'
        );
        $projects = $this->category(
            'projects',
            'Projects',
            'Current and completed community-led projects across Bangladesh.',
            '61000000-0000-4000-8000-000000000005'
        );
        $school = $this->category(
            'visit-ignite-school',
            'Visit Ignite School',
            'Free, inclusive education from playgroup through Class Five at Ignite School in Bawnia, Dhaka.',
            '61000000-0000-4000-8000-000000000006'
        );

        $education = $this->page($programs, [
            'uuid' => '62000000-0000-4000-8000-000000000001',
            'slug' => 'education',
            'name' => 'Inclusive Education',
            'sub_title' => 'Equal access to learning so every child can participate, grow, and thrive.',
            'thumbnail' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg',
            'description' => '<h2>Education that includes every learner</h2><p>Ignite Global Foundation supports inclusive learning spaces, educational materials, nutritious meals, healthcare support, and life-skills development for children from marginalized communities, including children with additional needs.</p><h2>What the program provides</h2><p>Children receive learning resources, school support, opportunities for creativity and play, and a safe environment designed around dignity and belonging.</p>',
            'order_by' => 30,
        ]);
        $youth = $this->page($programs, [
            'uuid' => '62000000-0000-4000-8000-000000000002',
            'slug' => 'youth-development',
            'name' => 'Youth Development',
            'sub_title' => 'Leadership, volunteerism, and practical skills for young changemakers.',
            'thumbnail' => self::MEDIA . '3fsusaq15mjnh0z2uaaehbuytxo0npv1rt39ll9o-27ecd8ac3985.jpg',
            'description' => '<h2>Young people leading change</h2><p>Ignite equips young people with leadership, communication, critical-thinking, project-management, advocacy, and digital skills. Volunteers work alongside communities on education, environmental sustainability, disaster resilience, and social inclusion.</p><h2>Ways to participate</h2><p>Young people can contribute as campus ambassadors, national volunteers, or international volunteers and receive mentorship while supporting practical community initiatives.</p><p><a href="/volunteer/register">Register to volunteer</a></p>',
            'order_by' => 20,
        ]);
        $disaster = $this->page($programs, [
            'uuid' => '62000000-0000-4000-8000-000000000003',
            'slug' => 'disaster-response-and-resilience',
            'name' => 'Disaster Response and Resilience',
            'sub_title' => 'Preparedness, urgent relief, recovery, and long-term resilience led with communities.',
            'thumbnail' => self::MEDIA . 'thfdurayx9wml9cgtcxn0fsrfotkts3wjr5z7rha-ed3e83810510.jpg',
            'description' => '<h2>Standing with communities before and after crisis</h2><p>The Disaster Response and Resilience program combines preparedness, rapid response, safe water and sanitation, health support, housing recovery, and community-led risk reduction.</p><h2>Rebuilding stronger</h2><p>Ignite works with local volunteers and partners so that emergency assistance protects dignity while recovery investments help families prepare for future hazards.</p><p><a href="/donate">Support disaster response</a></p>',
            'order_by' => 10,
        ]);

        $schoolCampus = $this->page($school, [
            'uuid' => '62000000-0000-4000-8000-000000000016',
            'slug' => 'ignite-school-bawnia-campus',
            'name' => 'Ignite School, Bawnia Campus',
            'sub_title' => 'Education for all—free, inclusive learning that helps children build brighter futures.',
            'thumbnail' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg',
            'description' => '<h2>Partner with Ignite School to shape bright futures</h2><p>Ignite School began in 2016 with 35 children and now supports nearly 120 learners at its Bawnia campus, including children with additional needs.</p><p>Students receive free education from Playgroup through Class Five together with learning materials, uniforms, nutritious meals, healthcare support, creative activities, and practical life skills.</p>',
            'order_by' => 10,
        ]);
        $school->forceFill([
            'display_mode' => 'landing_page',
            'landing_page_uuid' => $schoolCampus->uuid,
        ])->save();
        $schoolCampus->forceFill(['visibility' => 'unlisted'])->save();

        $this->seedProgramBlocks($education, $youth, $disaster);
        $this->seedSchoolBlocks($schoolCampus);
        $this->seedAboutContent($about);

        $this->page($about, [
            'uuid' => '62000000-0000-4000-8000-000000000004',
            'slug' => 'refund-policy',
            'name' => 'Refunds policy',
            'sub_title' => 'How to request a payment refund or chargeback.',
            'description' => '<h2>Refund requests</h2><p>For refunds, contact <a href="mailto:info@ignite.org.bd">info@ignite.org.bd</a> within 7 days of making the payment. Once a refund is initiated, it will be adjusted to the customer’s account within 7 working days.</p><h2>Chargebacks</h2><p>For a chargeback, contact the issuer bank of the card used to complete the payment. Once the bank issues the chargeback request, the requested amount will be disbursed within 14 working days.</p>',
            'order_by' => 50,
        ]);
        $this->page($careers, [
            'uuid' => '62000000-0000-4000-8000-000000000005',
            'slug' => 'career-opportunities',
            'name' => 'Work and volunteer with Ignite',
            'sub_title' => 'Use your skills and experience to help communities build lasting opportunity.',
            'thumbnail' => self::MEDIA . 'volunteer-vest-5eac1274808e.png',
            'description' => '<h2>Join the team</h2><p>Employment, internship, consultancy, and volunteer opportunities are published here when they become available.</p><p>For volunteer roles, complete the registration form so the team can match your interests and availability with an appropriate program.</p><p><a href="/volunteer/register">Register to volunteer</a> or <a href="/contact-us">contact our team</a>.</p>',
            'order_by' => 10,
        ]);

        foreach ([
            [
                'uuid' => '62000000-0000-4000-8000-000000000006',
                'slug' => 'the-diana-award',
                'name' => 'The Diana Award',
                'sub_title' => 'Recognition for outstanding work in youth development and education.',
                'thumbnail' => self::MEDIA . '350-x-200-the-diana-award-7f5b12c77802.jpg',
                'description' => '<h2>The Diana Award</h2><p>Ignite Global Foundation received The Diana Award in 2020 in recognition of its contribution to youth development and education. The recognition celebrates young people and organizations creating positive social impact.</p>',
                'order_by' => 30,
            ],
            [
                'uuid' => '62000000-0000-4000-8000-000000000007',
                'slug' => 'youth-development-award',
                'name' => 'UN Best Volunteer Award',
                'sub_title' => 'Recognition for volunteer-led contributions to community development.',
                'thumbnail' => self::MEDIA . 'un-award-350-x-200-4818229de147.jpg',
                'description' => '<h2>UN Best Volunteer Award</h2><p>Ignite volunteers were recognized for advancing inclusive community development through education, youth leadership, livelihoods, health, hygiene, and humanitarian action.</p>',
                'order_by' => 20,
            ],
            [
                'uuid' => '62000000-0000-4000-8000-000000000008',
                'slug' => 'best-volunteer-award',
                'name' => 'ILA Global 30 Under 30',
                'sub_title' => 'International recognition for leadership in education, literacy, and community empowerment.',
                'thumbnail' => self::MEDIA . 'ila350-x-200-a509373e4740.jpg',
                'description' => '<h2>ILA Global 30 Under 30</h2><p>This international recognition celebrates young leaders whose work expands learning opportunities, promotes inclusive education, and inspires grassroots change.</p>',
                'order_by' => 10,
            ],
        ] as $award) {
            $this->page($awards, $award);
        }

        $current = $this->tag('current-project', 'Current Projects', '63000000-0000-4000-8000-000000000001');
        $completed = $this->tag('completed-project', 'Completed Projects', '63000000-0000-4000-8000-000000000002');

        PageTagModule::query()->whereIn('tag_id', [$current->id, $completed->id])->delete();

        $projectRecords = [
            [
                'tag' => $current, 'uuid' => '62000000-0000-4000-8000-000000000009',
                'slug' => 'project-ankur', 'name' => 'Project ANKUR',
                'sub_title' => 'Climate-resilient ginger farming that helps vulnerable rural families grow dependable income.',
                'thumbnail' => self::MEDIA . 'rsz-edited-size-629-e2949cd0a7-404-px-embark-e11-40844d8249fb.jpg',
                'description' => '<h2>Resilient agriculture, rooted locally</h2><p>Project ANKUR began in Ishapasha, Tangail with eight families receiving hands-on training, inputs, and 15 grow bags each for climate-resilient ginger production.</p><h2>From skills to market</h2><p>The project combines practical capacity building, inclusive participation, production support, and market connections so small farmers can turn a manageable crop into a lasting source of income.</p>',
                'order_by' => 70,
            ],
            [
                'tag' => $current, 'uuid' => '62000000-0000-4000-8000-000000000010',
                'slug' => 'project-honey', 'name' => 'Project Honey',
                'sub_title' => 'Sustainable beekeeping for rural households in Sirajganj.',
                'thumbnail' => self::MEDIA . '3fsusaq15mjnh0z2uaaehbuytxo0npv1rt39ll9o-27ecd8ac3985.jpg',
                'description' => '<h2>Small hives, growing opportunity</h2><p>Fifteen rural households received training and beekeeping support, growing from three to four hives each and managing 60 hives together.</p><p>Early harvests produced 104 kilograms of honey, followed by 120 kilograms, creating new earning opportunities for women, small farmers, and young people while supporting local biodiversity.</p>',
                'order_by' => 60,
            ],
            [
                'tag' => $current, 'uuid' => '62000000-0000-4000-8000-000000000011',
                'slug' => 'project-onno', 'name' => 'Project ONNO',
                'sub_title' => 'Food security and emergency meals for families facing hardship.',
                'thumbnail' => self::MEDIA . 'rsz-edited-size-630-e2949cd0a7-398-px-e13-1-f7f7e3a09ec6.jpg',
                'description' => '<h2>Food support when it matters most</h2><p>Project ONNO has reached more than 44,000 families through emergency food packs, cooked meals, Ramadan support, and school nutrition. During the COVID-19 emergency, approximately 2,500 food packs helped families through sudden income loss.</p><p>The program connects immediate relief with the dignity, health, and stability families need to recover.</p>',
                'order_by' => 50,
            ],
            [
                'tag' => $current, 'uuid' => '62000000-0000-4000-8000-000000000012',
                'slug' => 'project-niramoy', 'name' => 'Project Niramoy',
                'sub_title' => 'Preventive care, health education, medicine, and referrals closer to underserved communities.',
                'thumbnail' => self::MEDIA . 'rsz-1niramoy-9351aa6a8d80.jpg',
                'description' => '<h2>Community care and prevention</h2><p>A team of 12 volunteer doctors supports health screenings, checkups, medicine, and practical health education. Weekly care at Ignite School helps children receive timely attention while outreach takes services to communities with limited access.</p><p>More than 4,500 people have been reached through the program.</p>',
                'order_by' => 40,
            ],
            [
                'tag' => $current, 'uuid' => '62000000-0000-4000-8000-000000000013',
                'slug' => 'project-shabolombi', 'name' => 'Project Shabolombi',
                'sub_title' => 'Training and productive assets that help low-income families build sustainable livelihoods.',
                'thumbnail' => self::MEDIA . 'rsz-11a41f4223-aab8-4d1c-9ff4-ed2872e8639e-c01192b9d089.jpg',
                'description' => '<h2>A path from vulnerability to self-reliance</h2><p>Project Shabolombi works with families earning below BDT 7,000 per month. Participants receive livelihood training, productive assets, and eligible Zakat assistance shaped around a realistic household business plan.</p><p>So far, 158 families have started income-generating activities through the project.</p>',
                'order_by' => 30,
            ],
            [
                'tag' => $completed, 'uuid' => '62000000-0000-4000-8000-000000000014',
                'slug' => 'project-tripty', 'name' => 'Project Tripty',
                'sub_title' => 'Deep tube wells and water purification support for rural and disaster-prone communities.',
                'thumbnail' => self::MEDIA . 'rsz-img-20220519-wa0018-63c5d60ed3f2.jpg',
                'description' => '<h2>Safe water closer to home</h2><p>Project Tripty installed deep tube wells and supported practical water purification in places where safe drinking water was difficult to reach. The completed project improved access for more than 150,000 people across rural and disaster-prone communities.</p>',
                'order_by' => 20,
            ],
            [
                'tag' => $completed, 'uuid' => '62000000-0000-4000-8000-000000000015',
                'slug' => 'project-prerona', 'name' => 'Project Prerona',
                'sub_title' => 'Menstrual health, dignity, and reusable products for marginalized women and girls.',
                'thumbnail' => self::MEDIA . 'maternity-1f204f451714.png',
                'description' => '<h2>Health information with practical support</h2><p>Project Prerona combined menstrual-health education with distribution of 750 reusable sanitary-pad packs to 750 marginalized women and girls in Baunia and Uttara. The project promoted dignity, safer health practices, and progress toward gender equality.</p>',
                'order_by' => 10,
            ],
        ];

        foreach ($projectRecords as $record) {
            $tag = $record['tag'];
            unset($record['tag']);
            $page = $this->page($projects, $record);
            PageTagModule::query()->updateOrCreate(
                ['uuid' => '64000000-0000-4000-8000-' . str_pad((string) $page->id, 12, '0', STR_PAD_LEFT)],
                ['page_id' => $page->id, 'tag_id' => $tag->id]
            );
        }
    }

    private function seedEvents(): void
    {
        $events = [
            [
                'title' => 'Together for Their Tomorrow',
                'slug' => 'together-for-their-tomorrow',
                'sub_title' => 'A charity gathering bringing supporters together to raise funds for education, nutrition, and essential care.',
                'description' => '<h2>Together for Their Tomorrow</h2><p>This community fundraising event brings supporters and partners together around practical ways to expand education, nutrition, and essential care for children.</p>',
                'image_path' => self::MEDIA . 'rsz-together-for-their-tomorrow-01a8ed105cdf.jpg',
                'published_at' => '2025-08-02 00:00:00',
                'order_by' => 30,
            ],
            [
                'title' => 'KIDOVATION',
                'slug' => 'kidovation',
                'sub_title' => 'Young innovators explore creativity, curiosity, science, and real-world problem solving.',
                'description' => '<h2>KIDOVATION</h2><p>KIDOVATION gives primary and secondary students a welcoming place to imagine, experiment, present ideas, and celebrate the power of science and technology.</p>',
                'image_path' => self::MEDIA . 'rsz-kiddovation-516bb86d7edd.jpg',
                'published_at' => '2025-09-11 00:00:00',
                'order_by' => 20,
            ],
            [
                'title' => 'Volunteer Orientation Program',
                'slug' => 'volunteer-orientation-program',
                'sub_title' => 'New volunteers learn about Ignite’s mission, responsibilities, teamwork, and pathways for growth.',
                'description' => '<h2>Volunteer Orientation Program</h2><p>The orientation equips new volunteers with the knowledge, practical expectations, communication habits, and confidence needed to contribute meaningfully.</p>',
                'image_path' => self::MEDIA . 'rsz-volunteer-orientation-5ed54757bfa9.jpg',
                'published_at' => '2025-07-23 00:00:00',
                'order_by' => 10,
            ],
            [
                'title' => 'Igniting Confidence Across Communities',
                'slug' => 'igniting-confidence-across-communities',
                'sub_title' => 'A look at how education, health, livelihoods, and youth leadership grow stronger together.',
                'description' => '<h2>Confidence built through service</h2><p>Since 2016, Ignite has connected inclusive education with healthcare, blood-donation support, livelihoods, youth workshops, hygiene, sanitation, and social-awareness initiatives.</p><p>The story highlights how volunteers and community partners turn practical local action into confidence, opportunity, and lasting public service.</p>',
                'image_path' => self::MEDIA . 'xqukynqncgqe0nshddw9sbh42iehevuzjgfqg51t-cfd14955b153.jpg',
                'published_at' => '2024-12-15 00:00:00',
                'order_by' => 9,
            ],
            [
                'title' => 'Igniting Futures Through Education',
                'slug' => 'igniting-futures-through-education',
                'sub_title' => 'How a winter-clothing effort grew into a school and a wider volunteer movement.',
                'description' => '<h2>From one response to a long-term commitment</h2><p>A winter support effort inspired a small school that opened in March 2016 with 20 learners. The work grew around a belief that poverty should never decide whether a child can learn.</p><p>Volunteers, campus representatives, blood-donation campaigns, district networks, and plans for accessible online learning now carry that commitment forward.</p>',
                'image_path' => self::MEDIA . 's9wdu5mgoa4p1kmqwndje6ofige5grcdm89fpx9w-ced9bae95502.jpg',
                'published_at' => '2024-11-20 00:00:00',
                'order_by' => 8,
            ],
            [
                'title' => 'Light in Their Hands',
                'slug' => 'light-in-their-hands',
                'sub_title' => 'Children, volunteers, and supporters creating a more inclusive path to education.',
                'description' => '<h2>Learning made possible together</h2><p>What began with children asking for a chance to study grew into an inclusive school supporting around 120 learners. A network of more than 23,000 volunteers and 85 university ambassadors has helped extend education, blood donation, food support, empowerment, and housing assistance.</p><p>Their shared work shows what becomes possible when young people place service in the hands of a community.</p>',
                'image_path' => self::MEDIA . 'y3oylo6tegbtxwlpcbsromnlhmn1gdlwtehqu9sl-30d4e0add7c6.jpg',
                'published_at' => '2024-10-10 00:00:00',
                'order_by' => 7,
            ],
        ];

        foreach ($events as $event) {
            $model = NoticeBoard::withTrashed()->firstOrNew(['slug' => $event['slug'], 'language' => 'en']);
            $model->fill(array_merge($event, ['notice_type' => 'notice-board', 'status' => 1]));
            $model->save();
            $this->restore($model);
        }
    }

    private function seedAboutContent(Category $about): void
    {
        $page = $this->page($about, [
            'uuid' => '22222222-2222-4222-8222-000000000010',
            'slug' => 'about-us',
            'name' => 'About Ignite Global Foundation',
            'sub_title' => 'A volunteer-led movement that began with children asking for the chance to learn.',
            'thumbnail' => self::MEDIA . 'founder-ea5ae7f8a69f.png',
            'description' => '<p>Ignite Global Foundation began on 2 February 2016 when a small group of young volunteers started listening to children who wanted an education. What began with 32 learners in an open park grew into an inclusive school and a nationwide community of service.</p>',
            'order_by' => 100,
        ]);

        $this->syncBlocks($page, [
            ['uuid' => '69000000-0000-4000-8000-000000000001', 'type' => 'timeline', 'label' => 'Our Story', 'content' => [
                'eyebrow' => 'Our story', 'heading' => 'From one shared promise to a national movement',
                'body' => 'Ignite grew by listening first, taking practical action, and inviting more people to serve.',
                'items' => [
                    ['heading' => '2 February 2016', 'body' => '<p>Young volunteers began teaching 32 children in a park after hearing how strongly they wanted the chance to learn.</p>'],
                    ['heading' => '2017', 'body' => '<p>The learning initiative became Ignite School, creating a stable and inclusive place for education, nutrition, health support, and childhood development.</p>'],
                    ['heading' => 'Today', 'body' => '<p>Education now connects with health, food security, livelihoods, safe water, disaster response, and a volunteer network reaching communities across Bangladesh.</p>'],
                ],
            ]],
            ['uuid' => '69000000-0000-4000-8000-000000000002', 'type' => 'media_text', 'label' => "Founder's Message", 'content' => [
                'eyebrow' => 'A message from our founder', 'heading' => 'Dignity, trust, and responsibility guide every step.',
                'body' => '<p>Ignite began because children showed us exactly what opportunity meant to them. Our role has always been to stand beside people, respect their leadership, and turn shared concern into practical, accountable action.</p><p>Every volunteer, donor, partner, and community member carries a part of that responsibility. Together, we can make sure hope is followed by the support people need to shape their own futures.</p>',
                'image' => self::MEDIA . 'founder-ea5ae7f8a69f.png', 'image_alt' => 'Muhammad Jahirul Islam, founder and chairman of Ignite Global Foundation',
                'image_position' => 'left', 'link_label' => 'Read the founder’s letter', 'link_url' => "/page/founder's-letter",
            ]],
            ['uuid' => '69000000-0000-4000-8000-000000000003', 'type' => 'stats', 'label' => 'Organization at a Glance', 'content' => [
                'eyebrow' => 'Built through service', 'heading' => 'Ignite at a glance', 'items' => [
                    ['value' => '2016', 'label' => 'Year founded', 'icon' => 'heart'],
                    ['value' => '23,000+', 'label' => 'Volunteers', 'icon' => 'people'],
                    ['value' => '7', 'label' => 'Board members', 'icon' => 'security'],
                    ['value' => '3461', 'label' => 'NGO registration', 'icon' => 'report'],
                ],
            ]],
            ['uuid' => '69000000-0000-4000-8000-000000000004', 'type' => 'team', 'label' => 'Board of Directors', 'content' => [
                'eyebrow' => 'Governance', 'heading' => 'Board of directors',
                'body' => 'The board provides mission stewardship, oversight, and accountability.', 'limit' => 12,
            ]],
            ['uuid' => '69000000-0000-4000-8000-000000000005', 'type' => 'partners', 'label' => 'Partner Organizations', 'content' => [
                'eyebrow' => '', 'heading' => 'Partner Organizations', 'body' => '',
                'items' => [
                    ['heading' => 'Bangladesh Brand Forum', 'body' => '', 'image' => self::MEDIA . 'partners/01-bangladesh-brand-forum.png', 'image_alt' => 'Bangladesh Brand Forum', 'url' => ''],
                    ['heading' => 'TechHub Bangladesh', 'body' => '', 'image' => self::MEDIA . 'partners/02-techhub-bangladesh.jpeg', 'image_alt' => 'TechHub Bangladesh', 'url' => ''],
                    ['heading' => 'Daraz Bangladesh', 'body' => '', 'image' => self::MEDIA . 'partners/03-daraz.png', 'image_alt' => 'Daraz Bangladesh', 'url' => ''],
                    ['heading' => 'ICT Division', 'body' => '', 'image' => self::MEDIA . 'partners/04-ict-division.png', 'image_alt' => 'ICT Division, Government of Bangladesh', 'url' => ''],
                    ['heading' => 'It’s Humanity Foundation', 'body' => '', 'image' => self::MEDIA . 'partners/05-its-humanity-foundation.png', 'image_alt' => 'It’s Humanity Foundation', 'url' => ''],
                    ['heading' => 'Rtv', 'body' => '', 'image' => self::MEDIA . 'partners/06-rtv.jpg', 'image_alt' => 'Rtv', 'url' => ''],
                    ['heading' => 'Prothom Alo', 'body' => '', 'image' => self::MEDIA . 'partners/07-prothom-alo.png', 'image_alt' => 'Prothom Alo', 'url' => ''],
                    ['heading' => 'ATN News', 'body' => '', 'image' => self::MEDIA . 'partners/08-atn-news.png', 'image_alt' => 'ATN News', 'url' => ''],
                    ['heading' => 'The Daily Star', 'body' => '', 'image' => self::MEDIA . 'partners/09-the-daily-star.png', 'image_alt' => 'The Daily Star', 'url' => ''],
                    ['heading' => 'Incepta Pharmaceuticals', 'body' => '', 'image' => self::MEDIA . 'partners/10-incepta.png', 'image_alt' => 'Incepta Pharmaceuticals', 'url' => ''],
                    ['heading' => 'JCI Bangladesh', 'body' => '', 'image' => self::MEDIA . 'partners/11-jci-bangladesh.jpeg', 'image_alt' => 'JCI Bangladesh', 'url' => ''],
                    ['heading' => 'JCI Dhaka West', 'body' => '', 'image' => self::MEDIA . 'partners/12-jci-dhaka-west.png', 'image_alt' => 'JCI Dhaka West', 'url' => ''],
                    ['heading' => 'Matribhumi Group', 'body' => '', 'image' => self::MEDIA . 'partners/13-matribhumi-group.jpeg', 'image_alt' => 'Matribhumi Group', 'url' => ''],
                    ['heading' => 'PriyoShop', 'body' => '', 'image' => self::MEDIA . 'partners/14-priyoshop.png', 'image_alt' => 'PriyoShop', 'url' => ''],
                    ['heading' => 'Technohaven', 'body' => '', 'image' => self::MEDIA . 'partners/15-technohaven.png', 'image_alt' => 'Technohaven Company Limited', 'url' => ''],
                    ['heading' => 'The Business Standard', 'body' => '', 'image' => self::MEDIA . 'partners/16-the-business-standard.png', 'image_alt' => 'The Business Standard', 'url' => ''],
                    ['heading' => 'Maasranga Television', 'body' => '', 'image' => self::MEDIA . 'partners/17-maasranga-tv.png', 'image_alt' => 'Maasranga Television', 'url' => ''],
                    ['heading' => 'Loop', 'body' => '', 'image' => self::MEDIA . 'partners/18-loop.png', 'image_alt' => 'Loop', 'url' => ''],
                    ['heading' => 'Rotary Club of Banani Model Town', 'body' => '', 'image' => self::MEDIA . 'partners/19-rotary-club-banani-model-town.jpeg', 'image_alt' => 'Rotary Club of Banani Model Town', 'url' => ''],
                    ['heading' => 'What’s On Guide', 'body' => '', 'image' => self::MEDIA . 'partners/20-whatson-guide.svg', 'image_alt' => 'What’s On Guide', 'url' => ''],
                ],
            ]],
            ['uuid' => '69000000-0000-4000-8000-000000000006', 'type' => 'rich_text', 'label' => 'Legal Status', 'content' => [
                'eyebrow' => 'Legal and accountable', 'heading' => 'Registered to serve responsibly',
                'body' => '<p>Ignite Global Foundation is registered under Bangladesh’s Foreign Donations (Voluntary Activities) Regulation Act, 2016. NGO Affairs Bureau registration number: <strong>3461</strong>.</p><p>Our public policies, annual reports, safeguarding commitments, and contact channels are available throughout this website.</p>',
            ]],
        ]);
    }

    private function seedProgramBlocks(Page $education, Page $youth, Page $disaster): void
    {
        $this->syncBlocks($education, [
            ['uuid' => '69100000-0000-4000-8000-000000000001', 'type' => 'media_text', 'label' => 'Inclusive Education Overview', 'content' => [
                'eyebrow' => 'Education for every learner', 'heading' => 'A safe place to learn, belong, and grow',
                'body' => '<p>Ignite School supports around 120 children, including learners with additional needs, through free education, nutritious meals, health support, and practical life skills.</p><p>The program works to remove financial, social, and accessibility barriers so every child can participate with dignity.</p>',
                'image' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg', 'image_alt' => 'Children learning together at Ignite School', 'image_position' => 'left',
            ]],
            ['uuid' => '69100000-0000-4000-8000-000000000002', 'type' => 'stats', 'label' => 'Education Impact', 'content' => [
                'eyebrow' => 'Learning impact', 'heading' => 'Support that reaches the whole child', 'items' => [
                    ['value' => '120', 'label' => 'Children supported', 'icon' => 'child'],
                    ['value' => '74+', 'label' => 'Learners enrolled', 'icon' => 'school'],
                    ['value' => '49+', 'label' => 'Graduates', 'icon' => 'report'],
                    ['value' => '9+', 'label' => 'Children with additional needs', 'icon' => 'heart'],
                ],
            ]],
            ['uuid' => '69100000-0000-4000-8000-000000000003', 'type' => 'cards', 'label' => 'Education Services', 'content' => [
                'eyebrow' => 'What children receive', 'heading' => 'More than lessons', 'body' => 'Each part of the program helps a child learn consistently and participate fully.',
                'items' => [
                    ['heading' => 'Educational materials', 'body' => 'Books, supplies, school bags, and resources needed for daily learning.', 'image' => self::MEDIA . 'lecture-01f5d264a6d4.png', 'url' => '/sponsor-child'],
                    ['heading' => 'Health and nutrition', 'body' => 'Nutritious food, routine health attention, and practical wellbeing support.', 'image' => self::MEDIA . 'fruit-75cb59ffcdbf.png', 'url' => '/sponsor-child'],
                    ['heading' => 'Uniforms and essentials', 'body' => 'Clothing and school essentials that help every learner take part with dignity.', 'image' => self::MEDIA . 'bag-8876f072e015.png', 'url' => '/sponsor-child'],
                    ['heading' => 'Extracurricular activities', 'body' => 'Creative play, leadership, sports, and confidence-building beyond the classroom.', 'image' => self::MEDIA . 'cap-587195905e8a.png', 'url' => '/sponsor-child'],
                ],
            ]],
            ['uuid' => '69100000-0000-4000-8000-000000000004', 'type' => 'cta', 'label' => 'Support Ignite School', 'content' => [
                'eyebrow' => 'Stand beside a learner', 'heading' => 'Help make inclusive education dependable',
                'body' => 'Sponsor a child or contribute to the education program so learning can continue without interruption.',
                'primary_label' => 'Sponsor a child', 'primary_url' => '/sponsor-child', 'secondary_label' => 'Donate to education', 'secondary_url' => '/donate',
            ]],
        ]);

        $this->syncBlocks($youth, [
            ['uuid' => '69200000-0000-4000-8000-000000000001', 'type' => 'media_text', 'label' => 'Youth Development Overview', 'content' => [
                'eyebrow' => 'Youth leading change', 'heading' => 'Skills become service when young people have room to lead',
                'body' => '<p>Ignite supports young people in six divisions with leadership, communication, critical thinking, advocacy, digital skills, project management, and guided community service.</p><p>Participants work across education, the environment, disaster resilience, and social inclusion while receiving mentoring and practical experience.</p>',
                'image' => self::MEDIA . 'ignite-volunteer-ba9879f082a3.png', 'image_alt' => 'Ignite youth volunteers serving their community', 'image_position' => 'right',
            ]],
            ['uuid' => '69200000-0000-4000-8000-000000000002', 'type' => 'stats', 'label' => 'Youth Network', 'content' => [
                'eyebrow' => 'A growing network', 'heading' => 'Young people connected by purpose', 'items' => [
                    ['value' => '105+', 'label' => 'Campus ambassadors', 'icon' => 'school'],
                    ['value' => '23,000+', 'label' => 'National volunteers', 'icon' => 'people'],
                    ['value' => '200+', 'label' => 'International volunteers', 'icon' => 'map'],
                    ['value' => '6', 'label' => 'Divisions active', 'icon' => 'heart'],
                ],
            ]],
            ['uuid' => '69200000-0000-4000-8000-000000000003', 'type' => 'cards', 'label' => 'Ways to Volunteer', 'content' => [
                'eyebrow' => 'Choose your pathway', 'heading' => 'Find the right way to contribute', 'items' => [
                    ['heading' => 'Campus ambassador', 'body' => 'Represent Ignite at your institution and organize purposeful student engagement.', 'image' => self::MEDIA . 'campus-ambassador-dc89c80bb3fa.png', 'url' => '/volunteer/register'],
                    ['heading' => 'National volunteer', 'body' => 'Support programs, campaigns, events, and emergency responses across Bangladesh.', 'image' => self::MEDIA . 'national-volunteer-f1504a89e719.png', 'url' => '/volunteer/register'],
                    ['heading' => 'International volunteer', 'body' => 'Contribute professional skills, ideas, mentoring, or remote support from abroad.', 'image' => self::MEDIA . 'international-volunteer-a8b6b5899e1d.png', 'url' => '/volunteer/register'],
                ],
            ]],
            ['uuid' => '69200000-0000-4000-8000-000000000004', 'type' => 'cta', 'label' => 'Volunteer Registration', 'content' => [
                'eyebrow' => 'Your next step', 'heading' => 'Turn your skills into meaningful action', 'body' => 'Tell us what you care about and where you would like to contribute.',
                'primary_label' => 'Register to volunteer', 'primary_url' => '/volunteer/register',
            ]],
        ]);

        $this->syncBlocks($disaster, [
            ['uuid' => '69300000-0000-4000-8000-000000000001', 'type' => 'media_text', 'label' => 'Disaster Resilience Overview', 'content' => [
                'eyebrow' => 'Before, during, and after crisis', 'heading' => 'Response that protects dignity and strengthens recovery',
                'body' => '<p>Ignite combines preparedness, rapid relief, safe water and sanitation, health support, housing recovery, and community-led risk reduction.</p><p>Local volunteers and partners help assistance arrive quickly while recovery decisions remain grounded in what affected families need most.</p>',
                'image' => self::MEDIA . 'thfdurayx9wml9cgtcxn0fsrfotkts3wjr5z7rha-ed3e83810510.jpg', 'image_alt' => 'Local volunteers supporting a flood response', 'image_position' => 'left',
            ]],
            ['uuid' => '69300000-0000-4000-8000-000000000002', 'type' => 'timeline', 'label' => 'How We Respond', 'content' => [
                'eyebrow' => 'A connected response', 'heading' => 'From immediate safety to lasting resilience', 'items' => [
                    ['heading' => 'Prepare', 'body' => '<p>Work with communities on awareness, volunteer readiness, risk mapping, and practical preparedness.</p>'],
                    ['heading' => 'Respond', 'body' => '<p>Mobilize emergency food, shelter, safe water, health support, and essential supplies.</p>'],
                    ['heading' => 'Recover', 'body' => '<p>Restore homes, livelihoods, learning, and essential services in ways that reduce future risk.</p>'],
                    ['heading' => 'Strengthen', 'body' => '<p>Invest in local leadership, safer infrastructure, and lessons that improve the next response.</p>'],
                ],
            ]],
            ['uuid' => '69300000-0000-4000-8000-000000000003', 'type' => 'gallery', 'label' => 'Response in Pictures', 'content' => [
                'eyebrow' => 'In the field', 'heading' => 'Communities standing strong together', 'items' => [
                    ['heading' => 'Flood response', 'body' => '', 'image' => self::MEDIA . 'thfdurayx9wml9cgtcxn0fsrfotkts3wjr5z7rha-ed3e83810510.jpg', 'url' => ''],
                    ['heading' => 'Emergency food support', 'body' => '', 'image' => self::MEDIA . 'rsz-edited-size-630-e2949cd0a7-398-px-e13-1-f7f7e3a09ec6.jpg', 'url' => ''],
                    ['heading' => 'Safe water access', 'body' => '', 'image' => self::MEDIA . 'rsz-img-20220519-wa0018-63c5d60ed3f2.jpg', 'url' => ''],
                    ['heading' => 'Community recovery', 'body' => '', 'image' => self::MEDIA . 'rsz-1630x398-px-disaster-resilience-section-ca5ad2a7568b.jpg', 'url' => ''],
                ],
            ]],
            ['uuid' => '69300000-0000-4000-8000-000000000004', 'type' => 'cta', 'label' => 'Support Disaster Response', 'content' => [
                'eyebrow' => 'Stand with affected families', 'heading' => 'Help relief arrive and recovery last',
                'body' => 'Your contribution supports urgent essentials and longer-term community recovery.',
                'primary_label' => 'Support disaster response', 'primary_url' => '/donate',
            ]],
        ]);
    }

    private function seedSchoolBlocks(Page $schoolCampus): void
    {
        $this->syncBlocks($schoolCampus, [
            ['uuid' => '69400000-0000-4000-8000-000000000000', 'type' => 'hero', 'label' => 'Ignite School Campus Hero', 'content' => [
                'variant' => 'campus',
                'eyebrow' => 'Visit Ignite School',
                'heading' => 'Ignite School, Bawnia Campus',
                'body' => 'Bawnia, Dhaka',
                'primary_label' => 'Sponsor a child',
                'primary_url' => '/sponsor-child',
                'secondary_label' => 'Arrange a visit',
                'secondary_url' => '/contact-us',
                'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg',
                'overlay_opacity' => 50,
                'autoplay' => false,
                'pause_on_hover' => true,
            ]],
            ['uuid' => '69400000-0000-4000-8000-000000000001', 'type' => 'media_text', 'label' => 'Ignite School Introduction', 'content' => [
                'variant' => 'campus-intro',
                'eyebrow' => 'Ignite School — education for all',
                'heading' => 'Partner with Ignite School to shape bright futures',
                'body' => '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports <strong>nearly 120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>',
                'image' => self::MEDIA . 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg',
                'image_alt' => 'Ignite School learners holding their books in a classroom',
                'image_position' => 'left',
            ]],
            ['uuid' => '69400000-0000-4000-8000-000000000002', 'type' => 'stats', 'label' => 'Ignite School Impact', 'content' => [
                'variant' => 'campus-stats',
                'eyebrow' => 'Learning that keeps growing',
                'heading' => 'Ignite School, Bawnia Campus — At a Glance',
                'animation_enabled' => false,
                'items' => [
                    ['value' => 'Nearly 120', 'label' => 'Learners supported', 'icon' => 'child'],
                    ['value' => '35', 'label' => 'Children at launch', 'icon' => 'child'],
                    ['value' => '2016', 'label' => 'School founded', 'icon' => 'school'],
                ],
            ]],
            ['uuid' => '69400000-0000-4000-8000-000000000003', 'type' => 'cards', 'label' => 'School Initiatives', 'content' => [
                'variant' => 'initiatives',
                'content_source' => 'manual',
                'eyebrow' => 'Learning beyond the classroom',
                'heading' => 'Our Initiatives',
                'body' => 'The school removes practical barriers so children can attend, participate, and thrive.',
                'items' => [
                    ['heading' => 'Inclusive education', 'body' => 'Free, structured learning from Playgroup through Class Five in a welcoming classroom.', 'icon' => 'school', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners studying together', 'url' => ''],
                    ['heading' => 'Books and materials', 'body' => 'Books, school supplies, and learning materials help every child participate consistently.', 'icon' => 'report', 'image' => self::MEDIA . 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg', 'image_alt' => 'Ignite School learners holding colourful books', 'url' => ''],
                    ['heading' => 'Nutrition support', 'body' => 'Nutritious meals help learners stay healthy, focused, and ready for the school day.', 'icon' => 'heart', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Ignite School learners together in their classroom', 'url' => ''],
                    ['heading' => 'Healthcare support', 'body' => 'Preventive care, referrals, and practical wellbeing support protect each learner.', 'icon' => 'health', 'image' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg', 'image_alt' => 'Ignite School students in their classroom', 'url' => ''],
                    ['heading' => 'Uniforms and essentials', 'body' => 'Uniforms, bags, and daily essentials help children attend and learn with dignity.', 'icon' => 'report', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners with their school bags', 'url' => ''],
                    ['heading' => 'Creative development', 'body' => 'Play, sports, arts, leadership, and life skills build confidence beyond the classroom.', 'icon' => 'people', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Ignite School learners growing together with confidence', 'url' => ''],
                ],
            ]],
            ['uuid' => '69400000-0000-4000-8000-000000000005', 'type' => 'cards', 'label' => 'Ways to Contribute', 'content' => [
                'variant' => 'contributions',
                'content_source' => 'manual',
                'eyebrow' => 'How you can help',
                'heading' => 'How Can You Contribute?',
                'body' => 'Support can take many forms. Choose the path that best matches your time, resources, or partnership goals.',
                'item_link_label' => 'Take the next step',
                'items' => [
                    ['heading' => 'Sponsor a child', 'body' => "Inclusive education\nNutritious meals\nLearning materials\nHealthcare support", 'icon' => 'child', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners supported through child sponsorship', 'url' => '/sponsor-child', 'link_label' => 'View sponsorship'],
                    ['heading' => 'Donate to education', 'body' => "One-time contribution\nSupport the education cause\nSecure donor form", 'icon' => 'heart', 'image' => self::MEDIA . 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg', 'image_alt' => 'Ignite School learners reading their books', 'url' => '/donate', 'link_label' => 'Make a donation'],
                    ['heading' => 'Volunteer with Ignite', 'body' => "Share time and skills\nManaged registration\nTeam follow-up", 'icon' => 'people', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Ignite School learners welcoming community support', 'url' => '/volunteer/register', 'link_label' => 'Register to volunteer'],
                    ['heading' => 'Visit Ignite School', 'body' => "Contact the Ignite team\nArrange a suitable visit\nLearn about the campus", 'icon' => 'map', 'image' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg', 'image_alt' => 'Ignite School students at the Bawnia campus', 'url' => '/contact-us', 'link_label' => 'Arrange a visit'],
                    ['heading' => 'Partner with the school', 'body' => "Institutional support\nSchool supplies\nDiscuss a tailored plan", 'icon' => 'school', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners who benefit from school partnerships', 'url' => '/contact-us', 'link_label' => 'Discuss a partnership'],
                ],
            ]],
            ['uuid' => '69400000-0000-4000-8000-000000000004', 'type' => 'cta', 'label' => 'Support Ignite School', 'content' => [
                'variant' => 'campus-actions',
                'eyebrow' => 'Help a child learn consistently',
                'heading' => 'Got any other plans?',
                'body' => 'Contact us to arrange a visit or discuss a partnership. You can also make a direct contribution to Ignite’s education work.',
                'primary_label' => 'Contact us to arrange a visit',
                'primary_url' => '/contact-us',
                'secondary_label' => 'Donate to education',
                'secondary_url' => '/donate',
            ]],
            ['uuid' => '69400000-0000-4000-8000-000000000006', 'type' => 'gallery', 'label' => 'Ignite School Gallery', 'content' => [
                'variant' => 'campus-gallery',
                'content_source' => 'manual',
                'eyebrow' => 'Inside the school',
                'heading' => 'Gallery',
                'body' => 'A glimpse of the children and learning community at Ignite School in Bawnia.',
                'items' => [
                    ['heading' => 'Learning together at Ignite School', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Four Ignite School learners seated together in their classroom', 'url' => ''],
                    ['heading' => 'Books open new possibilities', 'image' => self::MEDIA . 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg', 'image_alt' => 'Ignite School learners holding colourful books', 'url' => ''],
                    ['heading' => 'A welcoming classroom', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Four Ignite School learners standing together in a classroom', 'url' => ''],
                    ['heading' => 'Growing with confidence', 'image' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg', 'image_alt' => 'Four Ignite School boys standing together in their classroom', 'url' => ''],
                ],
            ]],
        ]);
    }

    private function seedTestimonials(): void
    {
        $testimonials = [
            [
                'uuid' => '2ddb368d-7e85-4acf-b350-2eab43727288',
                'name' => 'Nurun Nabi',
                'designation' => 'Satkuchia, Feni',
                'photo' => '',
                'testimonial' => 'We lost everything in the flood and had no home to go to. Ignite Global Foundation helped us find shelter, hope, and a fresh start.',
                'order_by' => 30,
            ],
            [
                'uuid' => '22f3b886-eb73-4516-a34e-7609ed875d06',
                'name' => 'Md. Somir',
                'designation' => 'Father of Arafat, Ignite School student',
                'photo' => self::MEDIA . 'testimonials/ekfevqgifptlzx53hhrk8yldizzqaaoklsffr17c.jpg',
                'testimonial' => 'We wanted our son to receive a proper education. With Ignite Global Foundation’s support, that hope is now becoming real.',
                'order_by' => 20,
            ],
            [
                'uuid' => '660ed08a-841b-40b7-8621-b25b3bad5513',
                'name' => 'Trishna',
                'designation' => 'Class 3 student, Ignite School',
                'photo' => self::MEDIA . 'testimonials/ytdvjwog8z0jlnyrzb4daho1dlmxny17y9qpwny8.jpg',
                'testimonial' => 'The Sponsor a Child program supports my education, and now my family and I can see hope for a brighter future.',
                'order_by' => 10,
            ],
        ];

        foreach ($testimonials as $item) {
            $model = Testimonial::withTrashed()->firstOrNew(['uuid' => $item['uuid']]);
            $model->fill(array_merge($item, ['status' => 1, 'language' => 'en']));
            $model->save();
            $this->restore($model);
        }
    }

    private function seedReports(): void
    {
        foreach ([
            ['year' => 2024, 'order' => 20],
            ['year' => 2023, 'order' => 10],
        ] as $report) {
            $filename = 'ignite-global-foundation-annual-report-' . $report['year'] . '.pdf';
            if (!Storage::disk('local')->exists('annual-reports/' . $filename)) {
                continue;
            }

            $model = AnnualReport::withTrashed()->firstOrNew([
                'slug' => 'ignite-foundation-annual-report-' . $report['year'],
                'language' => 'en',
            ]);
            $model->fill([
                'title' => 'Ignite Foundation Annual Report-' . $report['year'],
                'sub_title' => 'Programs, governance, and responsible stewardship for ' . $report['year'] . '.',
                'description' => 'Official Ignite Global Foundation annual report.',
                'notice_type' => 'annual-report',
                'file_type' => 'application/pdf',
                'file_size' => (string) Storage::disk('local')->size('annual-reports/' . $filename),
                'image_path' => $filename,
                'published_at' => $report['year'] . '-12-31 00:00:00',
                'order_by' => $report['order'],
                'status' => 1,
            ]);
            $model->save();
            $this->restore($model);
        }
    }

    private function seedGallery(): void
    {
        $album = Album::withTrashed()->firstOrNew(['uuid' => '65000000-0000-4000-8000-000000000001']);
        $album->fill(['name' => 'Community Programs', 'language' => 'en', 'status' => 1]);
        $album->save();
        $this->restore($album);

        $photos = [
            ['Children learning together', 'welcome-child-68f3788c7174.jpg'],
            ['Community volunteer orientation', 'rsz-volunteer-orientation-5ed54757bfa9.jpg'],
            ['Youth innovation program', 'rsz-kiddovation-516bb86d7edd.jpg'],
            ['Community support gathering', 'rsz-together-for-their-tomorrow-01a8ed105cdf.jpg'],
            ['Disaster resilience in action', 'rsz-1630x398-px-disaster-resilience-section-ca5ad2a7568b.jpg'],
            ['Inclusive education program', 'rsz-edited-size-629-e2949cd0a7-404-px-embark-e11-40844d8249fb.jpg'],
            ['Food support prepared by volunteers', 'rsz-edited-size-630-e2949cd0a7-398-px-e13-1-f7f7e3a09ec6.jpg'],
            ['Healthcare outreach through Project Niramoy', 'rsz-1niramoy-9351aa6a8d80.jpg'],
            ['Safe water access through Project Tripty', 'rsz-img-20220519-wa0018-63c5d60ed3f2.jpg'],
            ['Livelihood support through Project Shabolombi', 'rsz-11a41f4223-aab8-4d1c-9ff4-ed2872e8639e-c01192b9d089.jpg'],
            ['Young people planting for a healthier future', '3fsusaq15mjnh0z2uaaehbuytxo0npv1rt39ll9o-27ecd8ac3985.jpg'],
            ['Emergency response with local volunteers', 'thfdurayx9wml9cgtcxn0fsrfotkts3wjr5z7rha-ed3e83810510.jpg'],
            ['Children participating in Ignite School', 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg'],
            ['Volunteer-led community service', 'ignite-volunteer-ba9879f082a3.png'],
            ['Education for every learner', 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg'],
            ['Building brighter futures together', '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg'],
        ];

        foreach ($photos as $index => [$name, $filename]) {
            $gallery = Gallery::withTrashed()->firstOrNew([
                'uuid' => '66000000-0000-4000-8000-' . str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT),
            ]);
            $gallery->fill([
                'name' => $name,
                'type' => 'gallery',
                'description' => $name,
                'language' => 'en',
                'url' => self::MEDIA . $filename,
                'path' => $filename,
                'album_id' => $album->id,
                'order_by' => 100 - $index,
                'grid_column' => 1,
                'grid_row' => 1,
                'status' => 1,
            ]);
            $gallery->save();
            $this->restore($gallery);
        }
    }

    private function seedNavigation(): void
    {
        PageMenu::query()->where('type', 'main')->where('language', 'en')->update(['status' => 0]);

        $roots = [
            ['Home', 'frontend.home', null, []],
            ['About Us', 'custom', '#', [
                ['Who We Are', 'frontend.about', null],
                ["Founder's Letter", 'frontend.page', "founder's-letter"],
                ['Awards & Recognition', 'frontend.category', 'awards-&-recognition'],
                ['Photo Gallery', 'frontend.gallery', null],
                ['Annual Reports', 'frontend.annual_report.index', null],
                ['Contact Us', 'frontend.contactUs', null],
            ]],
            ['Our Work', 'custom', '#', [
                ['Program Overview', 'frontend.category', 'our-causes'],
                ['Inclusive Education', 'frontend.page', 'education'],
                ['Visit Ignite School', 'frontend.category', 'visit-ignite-school'],
                ['Youth Development', 'frontend.page', 'youth-development'],
                ['Disaster Resilience', 'frontend.page', 'disaster-response-and-resilience'],
                ['Current Projects', 'frontend.project', 'current-project'],
                ['Completed Projects', 'frontend.project', 'completed-project'],
            ]],
            ['Get Involved', 'custom', '#', [
                ['Volunteer', 'frontend.volunteer_registration.index', null],
                ['Careers', 'frontend.category', 'career'],
                ['Sponsor a Child', 'frontend.sponsor_child', null],
            ]],
            ['News & Stories', 'custom', '#', [
                ['Stories', 'frontend.category', 'stories'],
                ['Events & News', 'frontend.events', null],
            ]],
            ['Donate', 'custom', '#', [
                ['Make a Donation', 'frontend.donate.index', null],
                ['Give Zakat', 'frontend.zakat', null],
            ]],
        ];

        foreach ($roots as $rootIndex => [$name, $link, $slug, $children]) {
            $root = $this->menu(
                '67000000-0000-4000-8000-' . str_pad((string) ($rootIndex + 1), 12, '0', STR_PAD_LEFT),
                $name,
                $link,
                $slug,
                null,
                $rootIndex
            );
            foreach ($children as $childIndex => [$childName, $childLink, $childSlug]) {
                $this->menu(
                    '68000000-' . str_pad((string) ($rootIndex + 1), 4, '0', STR_PAD_LEFT) . '-4000-8000-' . str_pad((string) ($childIndex + 1), 12, '0', STR_PAD_LEFT),
                    $childName,
                    $childLink,
                    $childSlug,
                    $root->id,
                    $childIndex
                );
            }
        }
    }

    private function seedTeam(): void
    {
        $members = [
            ['Muhammad Jahirul Islam', 'Founder - Chairman', self::MEDIA . 'founder-ea5ae7f8a69f.png', 70],
            ['Monmoy Jahan Ali', 'Vice Chairman', '', 60],
            ['Md. Rafeu Riyan', 'General Secretary', '', 50],
            ['Israt Jahan', 'Treasurer', '', 40],
            ['Md. Fazle Munim', 'Executive Member', '', 30],
            ['Md. Tajdin Hassan', 'Executive Member', '', 20],
            ['Josinta Zinia', 'Executive Member', '', 10],
        ];

        foreach ($members as [$name, $designation, $image, $order]) {
            $member = LatestNews::withTrashed()->firstOrNew([
                'name' => $name,
                'type' => 'our-members',
                'language' => 'en',
            ]);
            $member->fill([
                'description' => $designation,
                'path' => $image,
                'image' => $image,
                'url' => '',
                'order_by' => $order,
                'status' => 1,
            ]);
            $member->save();
            $this->restore($member);
        }
    }

    private function syncBlocks(Page $page, array $blocks): void
    {
        foreach ($blocks as $index => $attributes) {
            $block = PageBlock::withTrashed()->firstOrNew(['uuid' => $attributes['uuid']]);
            $block->fill([
                'page_id' => $page->id,
                'type' => $attributes['type'],
                'label' => $attributes['label'],
                'content' => $attributes['content'],
                'settings' => [],
                'sort_order' => $index + 1,
                'is_enabled' => true,
                'show_on_desktop' => true,
                'show_on_mobile' => true,
            ]);
            $block->save();
            $this->restore($block);
        }
    }

    private function seedHomepage(): void
    {
        $homeCategory = $this->category('home', 'Homepage', 'Editable public homepage sections.', '61000000-0000-4000-8000-000000000010');
        $home = $this->page($homeCategory, [
            'uuid' => '62000000-0000-4000-8000-000000000100',
            'slug' => 'home',
            'name' => 'Ignite Global Foundation',
            'sub_title' => 'Community-led change through education, youth leadership, livelihoods, and humanitarian action.',
            'description' => '',
            'thumbnail' => self::MEDIA . 'ywuogg10l98ly4qdoa3ujrb2bzr9218wp43dqcka-0d6907c3637e.webp',
            'order_by' => 0,
        ]);

        SeoMetadata::query()->firstOrCreate([
            'seoable_type' => Page::class,
            'seoable_id' => $home->id,
            'locale' => 'en',
        ], [
            'title' => 'Ignite Global Foundation | Building Lasting Change',
            'description' => 'Ignite Global Foundation works with communities in Bangladesh to expand opportunity through sustainable, locally led programs.',
            'og_image' => self::MEDIA . 'ywuogg10l98ly4qdoa3ujrb2bzr9218wp43dqcka-0d6907c3637e.webp',
            'twitter_card' => 'summary_large_image',
            'twitter_image' => self::MEDIA . 'ywuogg10l98ly4qdoa3ujrb2bzr9218wp43dqcka-0d6907c3637e.webp',
            'robots_index' => true,
            'robots_follow' => true,
            'exclude_from_sitemap' => false,
        ]);

        // `/category/home` is an implementation archive containing only the
        // canonical homepage. Keep it followable for safety, but do not publish
        // the duplicate shell as a search result or sitemap entry.
        SeoMetadata::withTrashed()->firstOrCreate([
            'seoable_type' => Category::class,
            'seoable_id' => $homeCategory->id,
            'locale' => 'en',
        ], [
            'robots_index' => false,
            'robots_follow' => true,
            'exclude_from_sitemap' => true,
        ]);

        $slides = [
            ['Igniting change', 'Empowering lives.', 'From classrooms to communities, our programs help people build a stronger future.', 'ywuogg10l98ly4qdoa3ujrb2bzr9218wp43dqcka-0d6907c3637e.webp', '/category/our-causes'],
            ['Empowering through education', 'Every child deserves a chance to learn.', 'Join us in breaking barriers through free, inclusive, quality education.', '9me3alpg8medhhf0jbids6pkbcuva3wqauewpza9-4ec17f01da4a.webp', '/page/education'],
            ['Youth leading change', 'From volunteers to visionaries.', 'Ignite is building a generation of leaders who serve with purpose.', 'xzz5dafv1xrvdkoj2xlr9lzuprmkljgzw2dpoo1y-f7a315ee992f.webp', '/page/youth-development'],
            ['Standing strong in crisis', 'Rapid relief. Sustainable recovery.', 'From floods to fires, community-led humanitarian action helps no one get left behind.', 'oj97m6tfjvumxrbtgdokbtqxrnumxcioewcjpbnj-e949aa78b452.webp', '/page/disaster-response-and-resilience'],
            ['Inclusive education', 'Learning spaces where every child thrives.', 'Every learner deserves equal opportunity regardless of ability, background, or need.', 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg', '/page/education'],
            ['Youth development', 'Empowering youth, igniting change.', 'Young leaders gain the skills and opportunities to create meaningful impact.', 'dms2sp0pfxgane9lzjpco3enlkyd4xjeygndfbym-24b5036254cd.jpg', '/page/youth-development'],
            ['Disaster resilience', 'Standing strong, rebuilding lives.', 'Preparedness, response, recovery, and support help communities rebuild with dignity.', 'e4nfvn5wvxl5vi5fj4p40uwcia7yl3qu11hdzn2t-e37f5490f23c.jpg', '/page/disaster-response-and-resilience'],
            ['Ignite School', 'Education for all.', 'Partner with Ignite School to help shape brighter futures.', '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', '/sponsor-child'],
        ];
        $heroSlides = array_map(fn ($slide) => [
            'eyebrow' => $slide[0],
            'heading' => $slide[1],
            'body' => $slide[2],
            'primary_label' => 'Donate now',
            'primary_url' => '/donate',
            'secondary_label' => 'Learn more',
            'secondary_url' => $slide[4],
            'report_label' => '',
            'report_url' => '',
            'image' => self::MEDIA . $slide[3],
            'overlay_opacity' => 62,
        ], $slides);

        $blocks = [
            ['44444444-4444-4444-8444-000000000001', 'hero', 'Homepage Hero', [
                'eyebrow' => $heroSlides[0]['eyebrow'], 'heading' => $heroSlides[0]['heading'], 'body' => $heroSlides[0]['body'],
                'primary_label' => 'Donate now', 'primary_url' => '/donate', 'secondary_label' => 'Learn more', 'secondary_url' => '/category/our-causes',
                'image' => $heroSlides[0]['image'], 'overlay_opacity' => 62, 'autoplay' => true, 'interval' => 6500, 'pause_on_hover' => true,
                'slides' => $heroSlides,
            ]],
            ['44444444-4444-4444-8444-000000000002', 'stats', 'Verified Impact Metrics', [
                'heading' => '', 'items' => [
                    ['value' => '23,000+', 'label' => 'Volunteers', 'icon' => 'people'],
                    ['value' => '8', 'label' => 'Divisions covered', 'icon' => 'map'],
                    ['value' => '850,820+', 'label' => 'People helped', 'icon' => 'heart'],
                    ['value' => '400+', 'label' => 'Children served', 'icon' => 'school'],
                ],
            ]],
            ['44444444-4444-4444-8444-000000000003', 'media_text', 'Who We Are', [
                'eyebrow' => 'Welcome to our charity', 'heading' => 'Change lasts when communities lead it.',
                'body' => '<p>Ignite Global Foundation works alongside marginalized communities through education, youth development, livelihoods, healthcare, nutrition, and humanitarian action.</p>',
                'image' => self::MEDIA . 'welcome-bg-f7b67abb8b86.webp', 'image_alt' => 'Children taking part in an Ignite learning program', 'image_position' => 'left',
                'link_label' => 'About us', 'link_url' => '/about-us',
            ]],
            ['44444444-4444-4444-8444-000000000004', 'causes', 'Our Programs', ['eyebrow' => 'Our causes', 'heading' => 'Programs designed with communities', 'limit' => 3]],
            ['44444444-4444-4444-8444-000000000005', 'cta', 'Featured Campaign', [
                'variant' => 'campaign', 'eyebrow' => 'Take action', 'heading' => 'Help communities build lasting opportunity',
                'body' => 'Choose a secure one-time contribution and direct your support where it is needed most.',
                'primary_label' => 'Donate now', 'primary_url' => '/donate',
            ]],
            ['44444444-4444-4444-8444-000000000006', 'cards', 'Featured Projects', [
                'variant' => 'projects', 'eyebrow' => 'Field work', 'heading' => 'Featured projects',
                'body' => 'Current initiatives with measurable, community-owned outcomes.', 'view_all_label' => 'View all projects', 'view_all_url' => '/projects',
                'content_source' => 'projects', 'tag_slug' => 'current-project', 'selection_mode' => 'automatic',
                'selected_items' => [], 'sort' => 'featured', 'limit' => 3, 'item_link_label' => 'Read more',
                'empty_state' => 'Published projects will appear here automatically.',
                'items' => [
                    ['status' => 'Current', 'location' => 'Tangail', 'heading' => 'Project ANKUR', 'body' => 'Climate-resilient ginger farming for vulnerable rural families.', 'image' => self::MEDIA . 'rsz-edited-size-629-e2949cd0a7-404-px-embark-e11-40844d8249fb.jpg', 'image_alt' => 'Climate-resilient community agriculture', 'url' => '/page/project-ankur'],
                    ['status' => 'Current', 'location' => 'Bangladesh', 'heading' => 'Project Niramoy', 'body' => 'Preventive care, health education, medicine, and referrals.', 'image' => self::MEDIA . 'rsz-1niramoy-9351aa6a8d80.jpg', 'image_alt' => 'Project Niramoy community health outreach', 'url' => '/page/project-niramoy'],
                    ['status' => 'Current', 'location' => 'Bangladesh', 'heading' => 'Project ONNO', 'body' => 'Food security and emergency meals for families facing hardship.', 'image' => self::MEDIA . 'rsz-edited-size-630-e2949cd0a7-398-px-e13-1-f7f7e3a09ec6.jpg', 'image_alt' => 'Volunteers preparing food assistance', 'url' => '/page/project-onno'],
                ],
            ]],
            ['44444444-4444-4444-8444-000000000007', 'events', 'Events and News', ['eyebrow' => 'Stay involved', 'heading' => 'Events & latest news', 'limit' => 3]],
            ['44444444-4444-4444-8444-000000000013', 'testimonials', 'Community Stories', ['eyebrow' => 'Voices from our community', 'heading' => 'Stories of change', 'limit' => 5]],
            ['44444444-4444-4444-8444-000000000014', 'cards', 'Awards and Recognition', [
                'variant' => 'awards', 'eyebrow' => 'Recognition', 'heading' => 'Awards & recognition', 'view_all_label' => 'View all', 'view_all_url' => '/category/awards-&-recognition',
                'content_source' => 'category', 'category_slug' => 'awards-&-recognition', 'selection_mode' => 'automatic',
                'selected_items' => [], 'sort' => 'featured', 'limit' => 3, 'item_link_label' => 'Learn more',
                'empty_state' => 'Published awards will appear here automatically.',
                'items' => [
                    ['heading' => 'The Diana Award', 'body' => 'Recognition for youth development and education.', 'image' => self::MEDIA . '350-x-200-the-diana-award-7f5b12c77802.jpg', 'image_alt' => 'The Diana Award', 'url' => '/page/the-diana-award'],
                    ['heading' => 'UN Best Volunteer Award', 'body' => 'Recognition for volunteer-led community development.', 'image' => self::MEDIA . 'un-award-350-x-200-4818229de147.jpg', 'image_alt' => 'UN Best Volunteer Award', 'url' => '/page/youth-development-award'],
                    ['heading' => 'ILA Global 30 Under 30', 'body' => 'Recognition for education and community empowerment.', 'image' => self::MEDIA . 'ila350-x-200-a509373e4740.jpg', 'image_alt' => 'ILA Global 30 Under 30 recognition', 'url' => '/page/best-volunteer-award'],
                ],
            ]],
            ['44444444-4444-4444-8444-000000000009', 'rich_text', 'Accountability', [
                'eyebrow' => 'Open by design', 'heading' => 'Our commitment to accountability',
                'body' => '<p>Responsible stewardship, transparent reporting, safeguarding, and clear ways to raise concerns are central to our work.</p>',
                'items' => [
                    ['icon' => 'report', 'heading' => 'Annual reports', 'url' => '/annual-report'],
                    ['icon' => 'financials', 'heading' => 'Financials', 'url' => '/annual-report'],
                    ['icon' => 'security', 'heading' => 'Safeguarding', 'url' => '/page/safeguarding'],
                    ['icon' => 'policy', 'heading' => 'Policies', 'url' => '/page/privacy-policy'],
                ],
            ]],
            ['44444444-4444-4444-8444-000000000011', 'cta', 'Volunteer Call to Action', [
                'variant' => 'volunteer', 'eyebrow' => 'Take part', 'heading' => 'Join our mission',
                'body' => 'Volunteer your time, sponsor a child, support a program, or partner with our team.',
                'primary_label' => 'Become a volunteer', 'primary_url' => '/volunteer/register',
                'secondary_label' => 'Sponsor a child', 'secondary_url' => '/sponsor-child',
            ]],
            ['44444444-4444-4444-8444-000000000015', 'cards', 'Ways to Help', [
                'eyebrow' => 'Choose your next step', 'heading' => 'Turn care into practical action',
                'body' => 'There is more than one way to stand beside a community.',
                'items' => [
                    ['icon' => 'people', 'heading' => 'Volunteer', 'body' => 'Bring your time, skills, and energy to a program or community campaign.', 'url' => '/volunteer/register', 'link_label' => 'Join the team'],
                    ['icon' => 'child', 'heading' => 'Sponsor a child', 'body' => 'Help make education, nutrition, health support, and essentials dependable.', 'url' => '/sponsor-child', 'link_label' => 'Start sponsoring'],
                    ['icon' => 'heart', 'heading' => 'Make a donation', 'body' => 'Choose a secure one-time or monthly contribution to support urgent priorities.', 'url' => '/donate', 'link_label' => 'Give securely'],
                ],
            ]],
            ['44444444-4444-4444-8444-000000000012', 'newsletter', 'Newsletter', [
                'heading' => 'Stay informed', 'body' => 'Receive field updates, upcoming events, and thoughtful ways to help.', 'button_label' => 'Subscribe',
            ]],
        ];

        $managed = [];
        foreach ($blocks as $index => [$uuid, $type, $label, $content]) {
            $managed[] = $uuid;
            $block = PageBlock::withTrashed()->firstOrNew(['uuid' => $uuid]);
            $block->fill([
                'page_id' => $home->id, 'type' => $type, 'label' => $label, 'content' => $content, 'settings' => [],
                'sort_order' => $index + 1, 'is_enabled' => true, 'show_on_desktop' => true, 'show_on_mobile' => true,
            ]);
            $block->save();
            $this->restore($block);
        }

        PageBlock::query()->where('page_id', $home->id)->whereNotIn('uuid', $managed)->update(['is_enabled' => false]);
    }

    private function seedBundledAssets(): void
    {
        foreach (glob(database_path('seeders/assets/annual-reports/*.pdf')) ?: [] as $source) {
            Storage::disk('local')->put('annual-reports/' . basename($source), file_get_contents($source));
        }

        foreach (glob(database_path('seeders/assets/testimonials/*.{jpg,jpeg,png,webp}'), GLOB_BRACE) ?: [] as $source) {
            if (!is_file($source) || filesize($source) < 1) {
                continue;
            }
            $filename = strtolower(basename($source));
            $path = 'media/ignite-live/testimonials/' . $filename;
            $body = file_get_contents($source);
            Storage::disk('public')->put($path, $body);
            $dimensions = @getimagesizefromstring($body) ?: [null, null, null, null, null, null, null, 'mime' => 'image/jpeg'];
            $asset = MediaAsset::withTrashed()->firstOrNew(['disk' => 'public', 'path' => $path]);
            if (!$asset->uuid) {
                $asset->uuid = (string) Str::uuid();
            }
            $asset->fill([
                'original_name' => basename($source), 'mime_type' => $dimensions['mime'] ?? 'image/jpeg',
                'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)), 'bytes' => strlen($body),
                'width' => $dimensions[0], 'height' => $dimensions[1], 'alt_text' => 'Ignite community member',
                'caption' => 'First-party Ignite Global Foundation testimonial photograph', 'locale' => '*',
            ]);
            $asset->save();
            $this->restore($asset);
        }
    }

    private function completeImportedMediaMetadata(): void
    {
        MediaAsset::query()
            ->where('path', 'like', 'media/ignite-live/%')
            ->where(function ($query) {
                $query->whereNull('alt_text')->orWhere('alt_text', '');
            })
            ->get()
            ->each(function (MediaAsset $asset) {
                $stem = Str::of(pathinfo((string) $asset->original_name, PATHINFO_FILENAME))
                    ->replaceMatches('/[_-]+/', ' ')
                    ->replaceMatches('/\b(?:rsz|img|image|edited|size|copy)\b/i', ' ')
                    ->replaceMatches('/\s+/', ' ')
                    ->trim();
                $asset->alt_text = $stem->isEmpty() || preg_match('/^[a-z0-9]{18,}$/i', (string) $stem)
                    ? 'Ignite Global Foundation programme photograph'
                    : Str::of((string) $stem)->headline()->limit(150, '')->toString();
                $asset->save();
            });
    }

    private function category(string $slug, string $name, string $description, string $uuid): Category
    {
        $category = Category::withTrashed()->firstOrNew(['slug' => $slug, 'language' => 'en']);
        $category->fill([
            'uuid' => $category->uuid ?: $uuid,
            'name' => $name,
            'description' => $description,
            'type' => 'category-pages',
            'name_enabled' => 1,
            'meta_title' => $name . ' | Ignite Global Foundation',
            'meta_keyword' => $name . ', Ignite Global Foundation',
            'meta_description' => Str::limit(strip_tags($description), 160, ''),
            'status' => 1,
        ]);
        $category->save();
        $this->restore($category);

        return $category;
    }

    private function page(Category $category, array $attributes): Page
    {
        $isHome = $attributes['slug'] === 'home';
        $page = Page::withTrashed()->firstOrNew(['slug' => $attributes['slug'], 'language' => 'en']);
        $page->fill(array_merge($attributes, [
            'uuid' => $page->uuid ?: $attributes['uuid'],
            'category_id' => $category->id,
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => today(),
            'last_published_at' => now(),
            'meta_title' => $isHome
                ? 'Ignite Global Foundation | Building Lasting Change'
                : $attributes['name'] . ' | Ignite Global Foundation',
            'meta_keyword' => $attributes['name'] . ', Ignite Global Foundation',
            'meta_description' => Str::limit(strip_tags($attributes['sub_title'] ?: $attributes['description']), 160, ''),
        ]));
        $page->save();
        $this->restore($page);

        return $page;
    }

    private function tag(string $slug, string $name, string $uuid): Tag
    {
        $tag = Tag::withTrashed()->firstOrNew(['slug' => $slug]);
        $tag->fill(['uuid' => $tag->uuid ?: $uuid, 'name' => $name, 'status' => 1]);
        $tag->save();
        $this->restore($tag);

        return $tag;
    }

    private function menu(string $uuid, string $name, string $link, ?string $slug, ?int $parentId, int $order): PageMenu
    {
        $menu = PageMenu::withTrashed()->firstOrNew(['uuid' => $uuid]);
        $menu->fill([
            'name' => $name, 'type' => 'main', 'link' => $link, 'slug' => $slug, 'parent_id' => $parentId,
            'language' => 'en', 'order_by' => $order, 'status' => 1,
        ]);
        $menu->save();
        $this->restore($menu);

        return $menu;
    }

    private function restore(Model $model): void
    {
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }
    }
}
