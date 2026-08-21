<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MEDIA = '/storage/media/ignite-live/';

    public function up(): void
    {
        if (!Schema::hasTable('page_blocks')) {
            return;
        }

        $this->updateBlock('69400000-0000-4000-8000-000000000000', function (array $content): array {
            if (($content['body'] ?? '') === 'Bawnia, Dhaka — free, inclusive education where every child can learn, belong, and grow.') {
                $content['body'] = 'Bawnia, Dhaka';
            }

            return $content;
        });

        $this->updateBlock('69400000-0000-4000-8000-000000000001', function (array $content): array {
            $content += ['variant' => 'campus-intro'];
            $oldBody = '<p>Ignite School began in 2016 with 35 children. Today, the Bawnia campus supports nearly 120 learners, including children with additional needs, through free and inclusive education.</p><p>Every child is welcomed into a safe learning community where dignity, creativity, and practical support make regular participation possible.</p>';
            $intermediateBody = '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports nearly <strong>120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>';
            if (in_array(($content['body'] ?? ''), [$oldBody, $intermediateBody], true)) {
                $content['body'] = '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports <strong>nearly 120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>';
            }

            return $content;
        });

        $this->updateBlock('69400000-0000-4000-8000-000000000002', function (array $content): array {
            $content += ['variant' => 'campus-stats'];
            if (($content['heading'] ?? '') === 'A school community built around every learner') {
                $content['heading'] = 'Ignite School, Bawnia Campus — At a Glance';
            }
            $items = is_array($content['items'] ?? null) ? $content['items'] : [];
            $items = array_values(array_filter($items, static function ($item): bool {
                return $item !== ['value' => 'Playgroup–5', 'label' => 'Classes supported', 'icon' => 'people'];
            }));
            foreach ($items as $index => $item) {
                if ($item === ['value' => '120+', 'label' => 'Current learners', 'icon' => 'child']) {
                    $items[$index] = ['value' => 'Nearly 120', 'label' => 'Learners supported', 'icon' => 'child'];
                }
            }
            $content['items'] = $items;

            return $content;
        });

        $this->updateBlock('69400000-0000-4000-8000-000000000003', function (array $content): array {
            if (($content['heading'] ?? '') === 'Our initiatives at Ignite School') {
                $content['heading'] = 'Our Initiatives';
            }
            $items = is_array($content['items'] ?? null) ? $content['items'] : [];
            if ($items === $this->legacyInitiativeItems()) {
                $content['items'] = $this->initiativeItems();
            }

            return $content;
        });

        $this->updateBlock('69400000-0000-4000-8000-000000000005', function (array $content): array {
            if (($content['heading'] ?? '') === 'Choose a meaningful way to contribute') {
                $content['heading'] = 'How Can You Contribute?';
            }
            $items = is_array($content['items'] ?? null) ? $content['items'] : [];
            if ($items === $this->legacyContributionItems()) {
                $content['items'] = $this->contributionItems();
            }

            return $content;
        });

        $this->updateBlock('69400000-0000-4000-8000-000000000004', function (array $content): array {
            if (($content['heading'] ?? '') === 'Have another plan for supporting the school?') {
                $content['heading'] = 'Got any other plans?';
            }

            return $content;
        });

        $this->updateBlock('69400000-0000-4000-8000-000000000006', function (array $content): array {
            if (($content['heading'] ?? '') === 'Learning, friendship, and everyday progress') {
                $content['heading'] = 'Gallery';
            }

            return $content;
        });

        $this->moveCampusActionsBeforeGallery();
    }

    public function down(): void
    {
        // The migration upgrades editor-owned presentation data in place.
        // A destructive rollback could overwrite later editorial changes.
    }

    private function updateBlock(string $uuid, callable $mutate): void
    {
        foreach (DB::table('page_blocks')->where('uuid', $uuid)->get() as $block) {
            $content = json_decode((string) $block->content, true);
            $content = is_array($content) ? $content : [];
            $updated = $mutate($content);
            if ($updated === $content) {
                continue;
            }
            DB::table('page_blocks')->where('id', $block->id)->update([
                'content' => json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    private function moveCampusActionsBeforeGallery(): void
    {
        $uuids = [
            '69400000-0000-4000-8000-000000000000',
            '69400000-0000-4000-8000-000000000001',
            '69400000-0000-4000-8000-000000000002',
            '69400000-0000-4000-8000-000000000003',
            '69400000-0000-4000-8000-000000000005',
            '69400000-0000-4000-8000-000000000006',
            '69400000-0000-4000-8000-000000000004',
        ];
        $blocksQuery = DB::table('page_blocks')->whereIn('uuid', $uuids);
        if (Schema::hasColumn('page_blocks', 'deleted_at')) {
            $blocksQuery->whereNull('deleted_at');
        }
        $blocks = $blocksQuery->get(['id', 'page_id', 'uuid', 'sort_order'])->groupBy('page_id');

        foreach ($blocks as $pageBlocks) {
            if ($pageBlocks->count() !== 7) {
                continue;
            }
            $allPageBlocks = DB::table('page_blocks')->where('page_id', $pageBlocks->first()->page_id);
            if (Schema::hasColumn('page_blocks', 'deleted_at')) {
                $allPageBlocks->whereNull('deleted_at');
            }
            $current = $allPageBlocks
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('uuid')
                ->map(static fn ($uuid): string => (string) $uuid)
                ->all();
            if ($current !== $uuids) {
                continue;
            }
            $desired = array_merge(array_slice($uuids, 0, 5), [$uuids[6], $uuids[5]]);
            foreach ($desired as $index => $uuid) {
                DB::table('page_blocks')
                    ->where('page_id', $pageBlocks->first()->page_id)
                    ->where('uuid', $uuid)
                    ->update(['sort_order' => $index + 1, 'updated_at' => now()]);
            }
        }
    }

    private function initiativeItems(): array
    {
        return [
            ['heading' => 'Inclusive education', 'body' => 'Free, structured learning from Playgroup through Class Five in a welcoming classroom.', 'icon' => 'school', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners studying together', 'url' => ''],
            ['heading' => 'Books and materials', 'body' => 'Books, school supplies, and learning materials help every child participate consistently.', 'icon' => 'report', 'image' => self::MEDIA . 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg', 'image_alt' => 'Ignite School learners holding colourful books', 'url' => ''],
            ['heading' => 'Nutrition support', 'body' => 'Nutritious meals help learners stay healthy, focused, and ready for the school day.', 'icon' => 'heart', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Ignite School learners together in their classroom', 'url' => ''],
            ['heading' => 'Healthcare support', 'body' => 'Preventive care, referrals, and practical wellbeing support protect each learner.', 'icon' => 'health', 'image' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg', 'image_alt' => 'Ignite School students in their classroom', 'url' => ''],
            ['heading' => 'Uniforms and essentials', 'body' => 'Uniforms, bags, and daily essentials help children attend and learn with dignity.', 'icon' => 'report', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners with their school bags', 'url' => ''],
            ['heading' => 'Creative development', 'body' => 'Play, sports, arts, leadership, and life skills build confidence beyond the classroom.', 'icon' => 'people', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Ignite School learners growing together with confidence', 'url' => ''],
        ];
    }

    private function legacyInitiativeItems(): array
    {
        return [
            ['heading' => 'Free education', 'body' => 'Inclusive teaching, books, school supplies, and structured learning from Playgroup through Class Five.', 'icon' => 'school', 'url' => ''],
            ['heading' => 'Nutrition and health', 'body' => 'Nutritious meals, preventive care, referrals, and practical wellbeing support.', 'icon' => 'health', 'url' => ''],
            ['heading' => 'Uniforms and essentials', 'body' => 'Uniforms, bags, learning materials, and essentials that help children participate with dignity.', 'icon' => 'report', 'url' => ''],
            ['heading' => 'Creative development', 'body' => 'Play, sports, arts, leadership, and life-skills activities that build confidence beyond the classroom.', 'icon' => 'heart', 'url' => ''],
        ];
    }

    private function contributionItems(): array
    {
        return [
            ['heading' => 'Sponsor a child', 'body' => "Inclusive education\nNutritious meals\nLearning materials\nHealthcare support", 'icon' => 'child', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners supported through child sponsorship', 'url' => '/sponsor-child', 'link_label' => 'View sponsorship'],
            ['heading' => 'Donate to education', 'body' => "One-time contribution\nSupport the education cause\nSecure donor form", 'icon' => 'heart', 'image' => self::MEDIA . 'p0fz2nhfbm0ki2u81kjb9lbaifkokgfn0cx8jua4-3df14b756c14.jpg', 'image_alt' => 'Ignite School learners reading their books', 'url' => '/donate', 'link_label' => 'Make a donation'],
            ['heading' => 'Volunteer with Ignite', 'body' => "Share time and skills\nManaged registration\nTeam follow-up", 'icon' => 'people', 'image' => self::MEDIA . 'welcome-child-68f3788c7174.jpg', 'image_alt' => 'Ignite School learners welcoming community support', 'url' => '/volunteer/register', 'link_label' => 'Register to volunteer'],
            ['heading' => 'Visit Ignite School', 'body' => "Contact the Ignite team\nArrange a suitable visit\nLearn about the campus", 'icon' => 'map', 'image' => self::MEDIA . 'fzybmfnokijodrkucte3yo1bt4741x7ygzllbyzm-05ae3890f6ad.jpg', 'image_alt' => 'Ignite School students at the Bawnia campus', 'url' => '/contact-us', 'link_label' => 'Arrange a visit'],
            ['heading' => 'Partner with the school', 'body' => "Institutional support\nSchool supplies\nDiscuss a tailored plan", 'icon' => 'school', 'image' => self::MEDIA . '53ie3y0pybysjxrhi7z46geyzazsjdu2euwiqijd-cf3e267a7b09.jpg', 'image_alt' => 'Ignite School learners who benefit from school partnerships', 'url' => '/contact-us', 'link_label' => 'Discuss a partnership'],
        ];
    }

    private function legacyContributionItems(): array
    {
        return [
            ['heading' => 'Sponsor a child', 'body' => 'Help make education, nutrition, healthcare, uniforms, and learning materials more dependable for one learner.', 'icon' => 'child', 'url' => '/sponsor-child', 'link_label' => 'View sponsorship'],
            ['heading' => 'Donate to education', 'body' => 'Make a one-time contribution to Ignite’s education work and ongoing school support.', 'icon' => 'heart', 'url' => '/donate', 'link_label' => 'Make a donation'],
            ['heading' => 'Volunteer with Ignite', 'body' => 'Offer your time and skills through Ignite’s managed volunteer registration process.', 'icon' => 'people', 'url' => '/volunteer/register', 'link_label' => 'Register to volunteer'],
            ['heading' => 'Visit or partner', 'body' => 'Contact the Ignite team to discuss a school visit, institutional support, or another partnership idea.', 'icon' => 'map', 'url' => '/contact-us', 'link_label' => 'Contact the team'],
        ];
    }
};
